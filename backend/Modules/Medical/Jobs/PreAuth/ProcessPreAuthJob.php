<?php

namespace Modules\Medical\Jobs\PreAuth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\MemberBenefitUtilization;
use Modules\Medical\Models\BenefitReservation;
use Modules\Medical\Events\PreAuthStatusUpdated;

class ProcessPreAuthJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Auto-approval criteria
     */
    private const AUTO_APPROVE_CRITERIA = [
        'max_amount' => 5000.00,
        'max_fraud_score' => 30,
    ];

    /**
     * Priorities that can be auto-approved
     */
    private const AUTO_APPROVE_PRIORITIES = ['standard'];

    public function __construct(
        public string $preauthId
    ) {
        $this->onQueue('medical-preauth');
    }

    public function uniqueId(): string
    {
        return $this->preauthId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->preauthId))
                ->dontRelease()
                ->expireAfter(180),
        ];
    }

    public function handle(): void
    {
        $preauth = PreAuthorization::with([
            'member',
            'policy.plan',
            'lines.benefit',
            'provider',
        ])->find($this->preauthId);

        if (!$preauth) {
            Log::warning("ProcessPreAuthJob: Pre-auth not found", ['preauth_id' => $this->preauthId]);
            return;
        }

        if ($preauth->status !== 'pending') {
            Log::info("ProcessPreAuthJob: Pre-auth not pending", [
                'preauth_id' => $this->preauthId,
                'status' => $preauth->status,
            ]);
            return;
        }

        Log::info("ProcessPreAuthJob: Processing", [
            'preauth_id' => $this->preauthId,
            'preauth_number' => $preauth->preauth_number,
        ]);

        try {
            DB::transaction(function () use ($preauth) {
                // 1. Validate eligibility
                $validationResult = $this->validatePreAuth($preauth);
                if (!$validationResult['valid']) {
                    $this->rejectPreAuth($preauth, $validationResult['reason_code'], $validationResult['reason']);
                    return;
                }

                // 2. Quick fraud check
                $fraudResult = $this->performFraudCheck($preauth);
                $preauth->update([
                    'fraud_score' => $fraudResult['score'],
                    'fraud_flags' => $fraudResult['flags'],
                    'fraud_checked_at' => now(),
                ]);

                if ($fraudResult['should_block']) {
                    $this->flagForReview($preauth, $fraudResult['block_reason']);
                    return;
                }

                // 3. Check benefit availability
                $benefitCheck = $this->checkBenefitAvailability($preauth);
                if (!$benefitCheck['available']) {
                    $this->rejectPreAuth($preauth, 'INSUFFICIENT_BENEFIT', $benefitCheck['reason']);
                    return;
                }

                // 4. Determine if can auto-approve
                if ($this->canAutoApprove($preauth, $fraudResult, $benefitCheck)) {
                    $this->autoApprove($preauth, $benefitCheck);
                } else {
                    $this->queueForReview($preauth, 'Requires manual review based on criteria');
                }
            });

            Log::info("ProcessPreAuthJob: Completed", [
                'preauth_id' => $this->preauthId,
                'final_status' => $preauth->fresh()->status,
            ]);

        } catch (\Exception $e) {
            Log::error("ProcessPreAuthJob: Failed", [
                'preauth_id' => $this->preauthId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function validatePreAuth(PreAuthorization $preauth): array
    {
        $member = $preauth->member;
        $policy = $preauth->policy;

        // Check member status
        if ($member->status !== 'active') {
            return [
                'valid' => false,
                'reason' => 'Member is not active',
                'reason_code' => 'MEMBER_NOT_ACTIVE',
            ];
        }

        // Check policy status
        if ($policy->status !== 'active') {
            return [
                'valid' => false,
                'reason' => 'Policy is not active',
                'reason_code' => 'POLICY_NOT_ACTIVE',
            ];
        }

        // Check service date
        $serviceDate = $preauth->requested_service_date;
        if ($serviceDate < $policy->inception_date || $serviceDate > $policy->expiry_date) {
            return [
                'valid' => false,
                'reason' => 'Service date outside policy period',
                'reason_code' => 'SERVICE_DATE_INVALID',
            ];
        }

        return ['valid' => true];
    }

    private function performFraudCheck(PreAuthorization $preauth): array
    {
        $score = 0;
        $flags = [];
        $shouldBlock = false;
        $blockReason = null;

        // Check member blacklist
        if ($preauth->member->is_fraud_blacklist ?? false) {
            $score += 100;
            $flags[] = 'MEMBER_BLACKLISTED';
            $shouldBlock = true;
            $blockReason = 'Member is on fraud blacklist';
        }

        // Check recent pre-auth count
        $recentCount = PreAuthorization::where('member_id', $preauth->member_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->where('id', '!=', $preauth->id)
            ->count();

        if ($recentCount >= 3) {
            $score += 20;
            $flags[] = 'MULTIPLE_PREAUTHS';
        }

        // Check amount vs typical
        if ($preauth->requested_amount > 20000) {
            $score += 15;
            $flags[] = 'HIGH_AMOUNT';
        }

        return [
            'score' => min(100, $score),
            'flags' => $flags,
            'should_block' => $shouldBlock,
            'block_reason' => $blockReason,
        ];
    }

    private function checkBenefitAvailability(PreAuthorization $preauth): array
    {
        $totalAvailable = 0;
        $lineDetails = [];

        foreach ($preauth->lines as $line) {
            if (!$line->benefit_id) {
                // No specific benefit - assume available
                $lineDetails[] = [
                    'line_id' => $line->id,
                    'available' => $line->requested_amount,
                    'can_approve' => $line->requested_amount,
                ];
                $totalAvailable += $line->requested_amount;
                continue;
            }

            $utilization = MemberBenefitUtilization::where('member_id', $preauth->member_id)
                ->where('benefit_id', $line->benefit_id)
                ->where('period_start', '<=', $preauth->requested_service_date)
                ->where('period_end', '>=', $preauth->requested_service_date)
                ->first();

            if (!$utilization) {
                // No utilization record - check plan benefit for limit
                $planBenefit = DB::table('med_plan_benefits')
                    ->where('plan_id', $preauth->policy->plan_id)
                    ->where('benefit_id', $line->benefit_id)
                    ->first();

                if (!$planBenefit) {
                    return [
                        'available' => false,
                        'reason' => "Benefit not covered under plan",
                    ];
                }

                $available = $planBenefit->limit_amount ?? $line->requested_amount;
            } else {
                $available = $utilization->remaining_amount - ($utilization->reserved_amount ?? 0);
            }

            if ($available <= 0) {
                return [
                    'available' => false,
                    'reason' => "Benefit exhausted for {$line->benefit?->name}",
                ];
            }

            $canApprove = min($line->requested_amount, $available);
            $lineDetails[] = [
                'line_id' => $line->id,
                'benefit_id' => $line->benefit_id,
                'available' => $available,
                'requested' => $line->requested_amount,
                'can_approve' => $canApprove,
            ];
            $totalAvailable += $canApprove;
        }

        return [
            'available' => true,
            'total_available' => $totalAvailable,
            'lines' => $lineDetails,
        ];
    }

    private function canAutoApprove(PreAuthorization $preauth, array $fraudResult, array $benefitCheck): bool
    {
        // Check priority
        if (!in_array($preauth->priority, self::AUTO_APPROVE_PRIORITIES)) {
            return false;
        }

        // Check amount
        if ($preauth->requested_amount > self::AUTO_APPROVE_CRITERIA['max_amount']) {
            return false;
        }

        // Check fraud score
        if ($fraudResult['score'] > self::AUTO_APPROVE_CRITERIA['max_fraud_score']) {
            return false;
        }

        // Check if all lines can be fully approved
        foreach ($benefitCheck['lines'] as $line) {
            if ($line['can_approve'] < $line['requested']) {
                return false; // Partial approval needs review
            }
        }

        return true;
    }

    private function autoApprove(PreAuthorization $preauth, array $benefitCheck): void
    {
        $totalApproved = 0;
        $totalReserved = 0;

        foreach ($preauth->lines as $line) {
            $lineCheck = collect($benefitCheck['lines'])->firstWhere('line_id', $line->id);
            $approvedAmount = $lineCheck['can_approve'] ?? $line->requested_amount;

            $line->update([
                'approved_amount' => $approvedAmount,
                'reserved_amount' => $approvedAmount,
                'status' => 'approved',
            ]);

            // Create benefit reservation
            if ($line->benefit_id) {
                $this->createBenefitReservation($preauth, $line, $approvedAmount);
            }

            $totalApproved += $approvedAmount;
            $totalReserved += $approvedAmount;
        }

        $preauth->update([
            'status' => 'auto_approved',
            'approved_amount' => $totalApproved,
            'reserved_amount' => $totalReserved,
            'decision_at' => now(),
            'decision_notes' => 'Auto-approved based on criteria',
        ]);

        $preauth->notes()->create([
            'note_type' => 'system',
            'content' => "Auto-approved. Amount: {$preauth->currency} " . number_format($totalApproved, 2),
            'is_internal' => true,
            'created_by_type' => 'system',
        ]);

        $this->broadcastUpdate($preauth, 'preauth_auto_approved', [
            'approved_amount' => $totalApproved,
        ]);
    }

    private function createBenefitReservation(PreAuthorization $preauth, $line, float $amount): void
    {
        $utilization = MemberBenefitUtilization::where('member_id', $preauth->member_id)
            ->where('benefit_id', $line->benefit_id)
            ->where('period_start', '<=', $preauth->requested_service_date)
            ->where('period_end', '>=', $preauth->requested_service_date)
            ->lockForUpdate()
            ->first();

        if (!$utilization) {
            // Create utilization record if doesn't exist
            $planBenefit = DB::table('med_plan_benefits')
                ->where('plan_id', $preauth->policy->plan_id)
                ->where('benefit_id', $line->benefit_id)
                ->first();

            $utilization = MemberBenefitUtilization::create([
                'member_id' => $preauth->member_id,
                'policy_id' => $preauth->policy_id,
                'benefit_id' => $line->benefit_id,
                'period_type' => 'annual',
                'period_start' => $preauth->policy->inception_date,
                'period_end' => $preauth->policy->expiry_date,
                'limit_type' => $planBenefit->limit_type ?? 'monetary',
                'limit_amount' => $planBenefit->limit_amount ?? null,
                'remaining_amount' => $planBenefit->limit_amount ?? 0,
            ]);
        }

        // Create reservation
        BenefitReservation::create([
            'member_benefit_utilization_id' => $utilization->id,
            'preauthorization_id' => $preauth->id,
            'preauthorization_line_id' => $line->id,
            'reserved_amount' => $amount,
            'reserved_at' => now(),
            'expires_at' => $preauth->expires_at,
            'status' => 'active',
        ]);

        // Update utilization reserved amounts
        $utilization->increment('reserved_amount', $amount);
        $utilization->increment('active_reservations_count');
    }

    private function rejectPreAuth(PreAuthorization $preauth, string $reasonCode, string $reason): void
    {
        $preauth->update([
            'status' => 'rejected',
            'rejection_reason' => $reasonCode,
            'rejection_details' => $reason,
            'decision_at' => now(),
        ]);

        $preauth->lines()->update(['status' => 'rejected']);

        $preauth->notes()->create([
            'note_type' => 'system',
            'content' => "Rejected: {$reason}",
            'is_internal' => true,
            'created_by_type' => 'system',
        ]);

        $this->broadcastUpdate($preauth, 'preauth_rejected', [
            'reason' => $reason,
            'reason_code' => $reasonCode,
        ]);
    }

    private function flagForReview(PreAuthorization $preauth, string $reason): void
    {
        $preauth->update([
            'status' => 'under_review',
            'requires_manual_review' => true,
            'review_reason' => $reason,
        ]);

        $preauth->notes()->create([
            'note_type' => 'system',
            'content' => "Flagged for review: {$reason}",
            'is_internal' => true,
            'created_by_type' => 'system',
        ]);

        $this->broadcastUpdate($preauth, 'preauth_under_review', [
            'reason' => $reason,
        ]);
    }

    private function queueForReview(PreAuthorization $preauth, string $reason): void
    {
        $preauth->update([
            'status' => 'under_review',
            'requires_manual_review' => true,
            'review_reason' => $reason,
        ]);

        $preauth->notes()->create([
            'note_type' => 'system',
            'content' => "Queued for manual review: {$reason}",
            'is_internal' => true,
            'created_by_type' => 'system',
        ]);

        $this->broadcastUpdate($preauth, 'preauth_pending_review', [
            'reason' => $reason,
        ]);
    }

    private function broadcastUpdate(PreAuthorization $preauth, string $event, array $data = []): void
    {
        try {
            event(new PreAuthStatusUpdated(
                preauthId: $preauth->id,
                preauthNumber: $preauth->preauth_number,
                status: $preauth->status,
                details: $data,
                memberId: $preauth->member_id,
                providerId: $preauth->provider_id,
            ));
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast preauth update", [
                'preauth_id' => $preauth->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPreAuthJob: Job failed permanently", [
            'preauth_id' => $this->preauthId,
            'error' => $exception->getMessage(),
        ]);
    }
}
