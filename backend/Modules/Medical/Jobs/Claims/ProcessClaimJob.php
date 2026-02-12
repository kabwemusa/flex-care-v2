<?php

namespace Modules\Medical\Jobs\Claims;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Claim;
use Modules\Medical\Services\Claims\AutoAdjudicationService;
use Modules\Medical\Services\Fraud\FraudDetectionService;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Events\ClaimStatusUpdated;
use Modules\Medical\Jobs\Fraud\FullFraudAnalysisJob;

class ProcessClaimJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $claimId,
        public int $priority = 5
    ) {
        $this->onQueue($this->determineQueue());
    }

    /**
     * Determine queue based on priority
     */
    private function determineQueue(): string
    {
        return $this->priority <= 2
            ? 'medical-claims-urgent'
            : 'medical-claims-standard';
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->claimId;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->claimId))
                ->dontRelease()
                ->expireAfter(180),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(
        AutoAdjudicationService $autoAdjudication,
        FraudDetectionService $fraudDetection
    ): void {
        $claim = Claim::with([
            'member.activeExclusions',
            'policy.plan',
            'lines.benefit',
            'provider',
        ])->find($this->claimId);

        if (!$claim) {
            Log::warning("ProcessClaimJob: Claim not found", ['claim_id' => $this->claimId]);
            return;
        }

        // Only process submitted claims
        if ($claim->status !== MedicalConstants::CLAIM_STATUS_SUBMITTED) {
            Log::info("ProcessClaimJob: Claim not in submitted status", [
                'claim_id' => $this->claimId,
                'status' => $claim->status,
            ]);
            return;
        }

        Log::info("ProcessClaimJob: Starting processing", [
            'claim_id' => $this->claimId,
            'claim_number' => $claim->claim_number,
        ]);

        try {
            DB::transaction(function () use ($claim, $autoAdjudication, $fraudDetection) {
                // 1. Mark as processing
                $claim->update([
                    'processing_started_at' => now(),
                    'status' => 'processing',
                ]);

                // Broadcast processing started
                $this->broadcastUpdate($claim, 'processing_started');

                // 2. Quick fraud check using FraudDetectionService
                $quickFraudResult = $fraudDetection->quickCheck($claim);
                $fraudResult = [
                    'score' => $quickFraudResult->score,
                    'flags' => $quickFraudResult->flags,
                    'status' => $quickFraudResult->shouldBlock ? 'blocked' : ($quickFraudResult->requiresReview ? 'review' : 'passed'),
                    'should_block' => $quickFraudResult->shouldBlock,
                    'block_reason' => $quickFraudResult->blockReason,
                ];

                $claim->update([
                    'fraud_score' => $fraudResult['score'],
                    'fraud_flags' => $fraudResult['flags'],
                    'fraud_checked_at' => now(),
                    'fraud_check_status' => $fraudResult['status'],
                    'fraud_check_version' => 'v1.0',
                ]);

                // Broadcast fraud check complete
                $this->broadcastUpdate($claim, 'fraud_check_complete', [
                    'score' => $fraudResult['score'],
                    'status' => $fraudResult['status'],
                ]);

                // 3. Check if should block due to fraud
                if ($fraudResult['should_block']) {
                    $this->flagForReview($claim, $fraudResult['block_reason'], true);
                    return;
                }

                // 4. Validate claim
                $validationResult = $this->validateClaim($claim);
                if (!$validationResult['valid']) {
                    $this->rejectClaim($claim, $validationResult['reason_code'], $validationResult['reason']);
                    return;
                }

                // 5. Attempt auto-adjudication
                $adjudicationResult = $autoAdjudication->process($claim);

                // 6. Update claim based on result
                if ($adjudicationResult->canAutoApprove) {
                    $this->approveClaim($claim, $adjudicationResult);
                } else {
                    $this->queueForManualReview($claim, $adjudicationResult);
                }

                // 7. Update processing completion
                $claim->update([
                    'processing_completed_at' => now(),
                    'auto_adjudicated' => $adjudicationResult->canAutoApprove,
                    'auto_adjudication_result' => $adjudicationResult->toArray(),
                    'auto_adjudication_status' => $adjudicationResult->canAutoApprove ? 'passed' : 'pending_review',
                ]);
            });

            // 8. Dispatch full fraud analysis in background (after transaction)
            FullFraudAnalysisJob::dispatch($this->claimId)
                ->onQueue('medical-fraud-analysis')
                ->delay(now()->addSeconds(30));

            Log::info("ProcessClaimJob: Completed", [
                'claim_id' => $this->claimId,
                'claim_number' => $claim->claim_number,
                'final_status' => $claim->fresh()->status,
            ]);

        } catch (\Exception $e) {
            Log::error("ProcessClaimJob: Failed", [
                'claim_id' => $this->claimId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update claim with error
            $claim->update([
                'status' => 'processing_failed',
                'retry_count' => ($claim->retry_count ?? 0) + 1,
                'processing_errors' => array_merge(
                    $claim->processing_errors ?? [],
                    [[
                        'error' => $e->getMessage(),
                        'at' => now()->toIso8601String(),
                        'attempt' => $this->attempts(),
                    ]]
                ),
            ]);

            $this->broadcastUpdate($claim, 'processing_error', [
                'message' => 'Claim processing encountered an error',
                'will_retry' => $this->attempts() < $this->tries,
            ]);

            throw $e;
        }
    }

    /**
     * Validate claim basics
     */
    private function validateClaim(Claim $claim): array
    {
        // Check member eligibility
        if ($claim->member->status !== MedicalConstants::MEMBER_STATUS_ACTIVE) {
            return [
                'valid' => false,
                'reason' => 'Member is not active',
                'reason_code' => 'MEMBER_NOT_ACTIVE',
            ];
        }

        // Check policy status
        if ($claim->policy->status !== MedicalConstants::POLICY_STATUS_ACTIVE) {
            return [
                'valid' => false,
                'reason' => 'Policy is not active',
                'reason_code' => 'POLICY_NOT_ACTIVE',
            ];
        }

        // Check service date within policy period
        $serviceDate = $claim->service_date;
        if ($serviceDate < $claim->policy->inception_date || $serviceDate > $claim->policy->expiry_date) {
            return [
                'valid' => false,
                'reason' => 'Service date outside policy period',
                'reason_code' => 'SERVICE_DATE_INVALID',
            ];
        }

        // Check member coverage dates
        if ($claim->member->cover_start_date && $serviceDate < $claim->member->cover_start_date) {
            return [
                'valid' => false,
                'reason' => 'Service date before member coverage start',
                'reason_code' => 'BEFORE_COVER_START',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Flag claim for manual review
     */
    private function flagForReview(Claim $claim, string $reason, bool $requiresAudit = false): void
    {
        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_PENDING,
            'is_flagged' => true,
            'flag_reason' => $reason,
            'requires_audit' => $requiresAudit,
        ]);

        $claim->notes()->create([
            'note_type' => 'system',
            'content' => "Flagged for manual review: {$reason}",
            'is_internal' => true,
        ]);

        $this->broadcastUpdate($claim, 'claim_flagged', [
            'reason' => $reason,
            'requires_audit' => $requiresAudit,
        ]);
    }

    /**
     * Reject claim
     */
    private function rejectClaim(Claim $claim, string $reasonCode, string $reason): void
    {
        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_REJECTED,
            'rejection_reason' => $reasonCode,
            'rejection_notes' => $reason,
            'rejected_at' => now(),
            'processed_at' => now(),
        ]);

        $claim->notes()->create([
            'note_type' => 'system',
            'content' => "Auto-rejected: {$reason}",
            'is_internal' => true,
        ]);

        $this->broadcastUpdate($claim, 'claim_rejected', [
            'reason' => $reason,
            'reason_code' => $reasonCode,
        ]);
    }

    /**
     * Approve claim after successful auto-adjudication
     */
    private function approveClaim(Claim $claim, $adjudicationResult): void
    {
        $status = $adjudicationResult->status;

        $claim->update([
            'status' => $status,
            'approved_amount' => $adjudicationResult->approvedAmount,
            'copay_amount' => $adjudicationResult->copayAmount,
            'payable_amount' => $adjudicationResult->payableAmount,
            'approved_at' => now(),
            'processed_at' => now(),
        ]);

        $claim->notes()->create([
            'note_type' => 'system',
            'content' => "Auto-approved. Amount: {$claim->currency} " . number_format($adjudicationResult->approvedAmount, 2),
            'is_internal' => true,
        ]);

        // Record benefit usage
        RecordBenefitUsageJob::dispatch($claim->id)->onQueue('medical-claims-standard');

        $this->broadcastUpdate($claim, 'claim_auto_approved', [
            'approved_amount' => $adjudicationResult->approvedAmount,
            'payable_amount' => $adjudicationResult->payableAmount,
        ]);
    }

    /**
     * Queue claim for manual review
     */
    private function queueForManualReview(Claim $claim, $adjudicationResult): void
    {
        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_PENDING,
            'approved_amount' => $adjudicationResult->approvedAmount,
            'copay_amount' => $adjudicationResult->copayAmount,
            'payable_amount' => $adjudicationResult->payableAmount,
        ]);

        $reviewReason = $adjudicationResult->reviewReason ?? 'Requires manual review';

        $claim->notes()->create([
            'note_type' => 'system',
            'content' => "Queued for manual review: {$reviewReason}",
            'is_internal' => true,
        ]);

        $this->broadcastUpdate($claim, 'claim_pending_review', [
            'reason' => $reviewReason,
            'preliminary_amount' => $adjudicationResult->approvedAmount,
        ]);
    }

    /**
     * Broadcast real-time update
     */
    private function broadcastUpdate(Claim $claim, string $event, array $data = []): void
    {
        try {
            event(new ClaimStatusUpdated(
                claimId: $claim->id,
                claimNumber: $claim->claim_number,
                status: $claim->status,
                previousStatus: $claim->getOriginal('status') ?? 'submitted',
                details: $data,
                memberId: $claim->member_id,
                providerId: $claim->provider_id_fk,
            ));
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast claim update", [
                'claim_id' => $claim->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessClaimJob: Job failed permanently", [
            'claim_id' => $this->claimId,
            'error' => $exception->getMessage(),
        ]);

        $claim = Claim::find($this->claimId);
        if ($claim) {
            $claim->update([
                'status' => 'processing_failed',
                'processing_errors' => array_merge(
                    $claim->processing_errors ?? [],
                    [[
                        'error' => 'Job failed permanently: ' . $exception->getMessage(),
                        'at' => now()->toIso8601String(),
                        'final' => true,
                    ]]
                ),
            ]);
        }
    }
}
