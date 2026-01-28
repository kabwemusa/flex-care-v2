<?php

namespace Modules\Medical\Services;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Benefit;
use Modules\Medical\Models\PlanBenefit;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\ClaimLine;
use Modules\Medical\Models\MemberBenefitUtilization;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Service for managing benefit utilization tracking.
 * 
 * Responsibilities:
 * - Initialize utilization records for new members
 * - Check benefit availability before claim approval
 * - Record usage when claims are approved
 * - Provide benefit balance summaries
 */
class BenefitUtilizationService
{
    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    /**
     * Initialize benefit utilization records for a member.
     * Called when a member is added to a policy.
     */
    public function initializeMemberBenefits(Member $member): Collection
    {
        $policy = $member->policy;
        $plan = $policy->plan;

        if (!$plan) {
            return collect();
        }

        $utilizations = collect();

        // Get all covered plan benefits
        $planBenefits = $plan->planBenefits()
            ->covered()
            ->with('benefit')
            ->get();

        foreach ($planBenefits as $planBenefit) {
            // Skip if already exists for this period
            $existing = MemberBenefitUtilization::where('member_id', $member->id)
                ->where('benefit_id', $planBenefit->benefit_id)
                ->where('period_start', $policy->inception_date)
                ->first();

            if ($existing) {
                $utilizations->push($existing);
                continue;
            }

            $utilization = MemberBenefitUtilization::initializeForMember(
                $member,
                $policy,
                $planBenefit
            );

            $utilizations->push($utilization);
        }

        return $utilizations;
    }

    /**
     * Reset utilization for a new policy period (renewal).
     */
    public function resetForNewPeriod(Member $member, Policy $newPolicy): Collection
    {
        return $this->initializeMemberBenefits($member);
    }

    // =========================================================================
    // BENEFIT CHECKING
    // =========================================================================

    /**
     * Check if a member has available benefit for a claim line.
     * Returns eligibility result with details.
     */
    public function checkBenefitEligibility(
        Member $member,
        string $benefitId,
        float $amount,
        ?Carbon $serviceDate = null
    ): array {
        $serviceDate = $serviceDate ?? now();
        
        // Get utilization record for current period
        $utilization = $this->getOrCreateUtilization($member, $benefitId, $serviceDate);

        if (!$utilization) {
            return [
                'eligible' => false,
                'reason' => 'Benefit not found in member\'s plan',
                'reason_code' => MedicalConstants::REJECTION_NOT_COVERED,
                'utilization' => null,
            ];
        }

        // Check if within valid period
        if (!$utilization->is_within_period) {
            return [
                'eligible' => false,
                'reason' => 'Service date is outside benefit period',
                'reason_code' => MedicalConstants::REJECTION_POLICY_INACTIVE,
                'utilization' => $utilization,
            ];
        }

        // Check if exhausted
        if ($utilization->is_exhausted) {
            return [
                'eligible' => false,
                'reason' => 'Benefit limit has been exhausted',
                'reason_code' => MedicalConstants::REJECTION_BENEFIT_EXHAUSTED,
                'utilization' => $utilization,
                'limit' => $utilization->formatted_limit,
                'used' => $utilization->formatted_used,
                'remaining' => $utilization->formatted_remaining,
            ];
        }

        // Check if sufficient balance
        if (!$utilization->hasAvailableBalance($amount)) {
            $available = $utilization->getAvailableBalance();
            return [
                'eligible' => false,
                'reason' => "Insufficient benefit balance. Available: {$utilization->formatted_remaining}",
                'reason_code' => MedicalConstants::REJECTION_BENEFIT_EXHAUSTED,
                'utilization' => $utilization,
                'requested_amount' => $amount,
                'available_amount' => $available,
                'limit' => $utilization->formatted_limit,
                'used' => $utilization->formatted_used,
                'remaining' => $utilization->formatted_remaining,
            ];
        }

        // Check waiting period
        // $waitingResult = $this->checkWaitingPeriod($member, $utilization, $serviceDate);
        // if (!$waitingResult['eligible']) {
        //     return $waitingResult;
        // }

        return [
            'eligible' => true,
            'utilization' => $utilization,
            'limit' => $utilization->formatted_limit,
            'used' => $utilization->formatted_used,
            'remaining' => $utilization->formatted_remaining,
            'available_amount' => $utilization->getAvailableBalance(),
        ];
    }

    /**
     * Check waiting period for a benefit.
     */
    protected function checkWaitingPeriod(
        Member $member,
        MemberBenefitUtilization $utilization,
        Carbon $serviceDate
    ): array {
        $planBenefit = $utilization->planBenefit;
        
        if (!$planBenefit) {
            return ['eligible' => true];
        }

        $waitingDays = $planBenefit->getEffectiveWaitingDays();
        
        if ($waitingDays <= 0) {
            return ['eligible' => true];
        }

        $memberStartDate = $member->effective_date ?? $member->created_at;
        $waitingEndDate = Carbon::parse($memberStartDate)->addDays($waitingDays);

        if ($serviceDate->lt($waitingEndDate)) {
            // $daysRemaining = $serviceDate->diffInDays($waitingEndDate);
            $daysRemaining = now()->diffInDays($waitingEndDate);
            return [
                'eligible' => false,
                'reason' => "Within waiting period. {$daysRemaining} days remaining until " . $waitingEndDate->format('M d, Y'),
                'reason_code' => MedicalConstants::REJECTION_WAITING_PERIOD,
                'utilization' => $utilization,
                'waiting_end_date' => $waitingEndDate->toDateString(),
                'days_remaining' => $daysRemaining,
            ];
        }

        return ['eligible' => true];
    }

    /**
     * Get or create utilization record for a member and benefit.
     */
    public function getOrCreateUtilization(
        Member $member,
        string $benefitId,
        ?Carbon $serviceDate = null
    ): ?MemberBenefitUtilization {
        $serviceDate = $serviceDate ?? now();
        $policy = $member->policy;

        // Try to find existing utilization for current period
        $utilization = MemberBenefitUtilization::where('member_id', $member->id)
            ->where('benefit_id', $benefitId)
            ->where('period_start', '<=', $serviceDate)
            ->where('period_end', '>=', $serviceDate)
            ->first();

        if ($utilization) {
            return $utilization;
        }

        // Check if benefit is in member's plan
        $plan = $policy->plan;
        if (!$plan) {
            return null;
        }

        $planBenefit = $plan->planBenefits()
            ->where('benefit_id', $benefitId)
            ->covered()
            ->with('benefit')
            ->first();

        if (!$planBenefit) {
            return null;
        }

        // Create new utilization record
        return MemberBenefitUtilization::initializeForMember($member, $policy, $planBenefit);
    }

    // =========================================================================
    // USAGE RECORDING
    // =========================================================================

    /**
     * Record benefit usage when a claim line is approved.
     */
    public function recordClaimLineUsage(
        ClaimLine $line,
        ?string $userId = null
    ): ?MemberBenefitUtilization {
        if (!$line->benefit_id) {
            return null;
        }

        $claim = $line->claim;
        $member = $claim->member;

        $utilization = $this->getOrCreateUtilization(
            $member,
            $line->benefit_id,
            Carbon::parse($claim->service_date)
        );

        if (!$utilization) {
            return null;
        }

        // Determine usage based on limit type
        $amount = $line->approved_amount ?? 0;
        $count = 1; // Each claim line counts as one visit/procedure
        $days = $claim->days_admitted ?? 0;

        // Update claim line with benefit info
        $line->update([
            'benefit_limit' => match ($utilization->limit_type) {
                MedicalConstants::MONETARY => $utilization->limit_amount,
                MedicalConstants::COUNT => $utilization->limit_count,
                MedicalConstants::DAYS => $utilization->limit_days,
                default => null,
            },
            'benefit_used_before' => match ($utilization->limit_type) {
                MedicalConstants::MONETARY => $utilization->used_amount,
                MedicalConstants::COUNT => $utilization->used_count,
                MedicalConstants::DAYS => $utilization->used_days,
                default => null,
            },
        ]);

        // Record usage
        $utilization->recordUsage(
            amount: $amount,
            count: $count,
            days: $days,
            claimId: $claim->id,
            claimLineId: $line->id,
            userId: $userId,
            notes: "Claim {$claim->claim_number} - Line #{$line->line_number}"
        );

        // Update claim line with remaining balance
        $line->update([
            'benefit_remaining' => match ($utilization->limit_type) {
                MedicalConstants::MONETARY => $utilization->remaining_amount,
                MedicalConstants::COUNT => $utilization->remaining_count,
                MedicalConstants::DAYS => $utilization->remaining_days,
                default => null,
            },
        ]);

        return $utilization;
    }

    /**
     * Record usage for all approved lines in a claim.
     */
    public function recordClaimUsage(Claim $claim, ?string $userId = null): void
    {
        $approvedLines = $claim->lines()
            ->whereIn('status', [
                MedicalConstants::CLAIM_LINE_STATUS_APPROVED,
                MedicalConstants::CLAIM_LINE_STATUS_PARTIALLY_APPROVED,
            ])
            ->whereNotNull('benefit_id')
            ->get();

        foreach ($approvedLines as $line) {
            $this->recordClaimLineUsage($line, $userId);
        }
    }

    /**
     * Reverse usage when a claim is reversed/cancelled.
     */
    public function reverseClaimUsage(Claim $claim, ?string $userId = null): void
    {
        $member = $claim->member;
        $serviceDate = Carbon::parse($claim->service_date);

        $approvedLines = $claim->lines()
            ->whereIn('status', [
                MedicalConstants::CLAIM_LINE_STATUS_APPROVED,
                MedicalConstants::CLAIM_LINE_STATUS_PARTIALLY_APPROVED,
            ])
            ->whereNotNull('benefit_id')
            ->get();

        foreach ($approvedLines as $line) {
            $utilization = $this->getOrCreateUtilization($member, $line->benefit_id, $serviceDate);
            
            if ($utilization) {
                $utilization->reverseUsage(
                    amount: $line->approved_amount ?? 0,
                    count: 1,
                    days: $claim->days_admitted ?? 0,
                    claimId: $claim->id,
                    claimLineId: $line->id,
                    userId: $userId,
                    notes: "Reversed: Claim {$claim->claim_number}"
                );
            }
        }
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Get benefit balances for a member.
     * Ensures all plan benefits are initialized before returning.
     */
    public function getMemberBenefitBalances(
        Member $member,
        ?Carbon $asOfDate = null
    ): Collection {
        $asOfDate = $asOfDate ?? now();

        // First, ensure all plan benefits are initialized for this member
        $this->initializeMemberBenefits($member);

        return MemberBenefitUtilization::where('member_id', $member->id)
            ->where('period_start', '<=', $asOfDate)
            ->where('period_end', '>=', $asOfDate)
            ->with(['benefit.category', 'planBenefit'])
            ->orderBy('benefit_id')
            ->get();
    }

    /**
     * Get benefit balance summary for a member (grouped by category).
     */
    public function getMemberBenefitSummary(Member $member): array
    {
        $utilizations = $this->getMemberBenefitBalances($member);

        $summary = [
            'member_id' => $member->id,
            'member_name' => $member->full_name,
            'policy_number' => $member->policy?->policy_number,
            'plan_name' => $member->policy?->plan?->name,
            'period' => [
                'start' => $utilizations->first()?->period_start?->format('Y-m-d'),
                'end' => $utilizations->first()?->period_end?->format('Y-m-d'),
            ],
            'total_benefits' => $utilizations->count(),
            'exhausted_benefits' => $utilizations->where('is_exhausted', true)->count(),
            'benefits_by_category' => [],
            'benefits' => [],
        ];

        // Group by category
        $grouped = $utilizations->groupBy(fn($u) => $u->benefit?->category?->name ?? 'Uncategorized');

        foreach ($grouped as $categoryName => $categoryUtilizations) {
            $categoryData = [
                'category' => $categoryName,
                'benefits' => [],
            ];

            foreach ($categoryUtilizations as $utilization) {
                $benefitData = [
                    'id' => $utilization->id,
                    'benefit_id' => $utilization->benefit_id,
                    'benefit_name' => $utilization->benefit?->name,
                    'benefit_code' => $utilization->benefit?->code,
                    'limit_type' => $utilization->limit_type,
                    'limit_type_label' => $utilization->limit_type_label,
                    'limit' => $this->formatLimitValue($utilization),
                    'used' => $this->formatUsedValue($utilization),
                    'remaining' => $this->formatRemainingValue($utilization),
                    'utilization_percentage' => $utilization->utilization_percentage,
                    'is_exhausted' => $utilization->is_exhausted,
                    'claims_count' => $utilization->claims_count,
                    'last_claim_at' => $utilization->last_claim_at?->format('Y-m-d H:i:s'),
                ];

                $categoryData['benefits'][] = $benefitData;
                $summary['benefits'][] = $benefitData;
            }

            $summary['benefits_by_category'][$categoryName] = $categoryData;
        }

        return $summary;
    }

    /**
     * Get utilization history for a benefit.
     */
    public function getBenefitUtilizationHistory(
        string $utilizationId,
        int $limit = 50
    ): Collection {
        return MemberBenefitUtilization::findOrFail($utilizationId)
            ->history()
            ->with(['claim:id,claim_number,service_date', 'claimLine:id,line_number,service_description'])
            ->limit($limit)
            ->get();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function formatLimitValue(MemberBenefitUtilization $utilization): array
    {
        return match ($utilization->limit_type) {
            MedicalConstants::MONETARY => [
                'value' => $utilization->limit_amount,
                'formatted' => $utilization->formatted_limit,
                'unit' => 'currency',
            ],
            MedicalConstants::COUNT => [
                'value' => $utilization->limit_count,
                'formatted' => $utilization->formatted_limit,
                'unit' => 'visits',
            ],
            MedicalConstants::DAYS => [
                'value' => $utilization->limit_days,
                'formatted' => $utilization->formatted_limit,
                'unit' => 'days',
            ],
            MedicalConstants::UNLIMITED => [
                'value' => null,
                'formatted' => 'Unlimited',
                'unit' => null,
            ],
            default => [
                'value' => null,
                'formatted' => 'N/A',
                'unit' => null,
            ],
        };
    }

    protected function formatUsedValue(MemberBenefitUtilization $utilization): array
    {
        return match ($utilization->limit_type) {
            MedicalConstants::MONETARY => [
                'value' => $utilization->used_amount,
                'formatted' => $utilization->formatted_used,
                'unit' => 'currency',
            ],
            MedicalConstants::COUNT => [
                'value' => $utilization->used_count,
                'formatted' => $utilization->formatted_used,
                'unit' => 'visits',
            ],
            MedicalConstants::DAYS => [
                'value' => $utilization->used_days,
                'formatted' => $utilization->formatted_used,
                'unit' => 'days',
            ],
            MedicalConstants::UNLIMITED => [
                'value' => $utilization->used_amount,
                'formatted' => 'K' . number_format($utilization->used_amount, 2),
                'unit' => 'currency',
            ],
            default => [
                'value' => 0,
                'formatted' => 'N/A',
                'unit' => null,
            ],
        };
    }

    protected function formatRemainingValue(MemberBenefitUtilization $utilization): array
    {
        return match ($utilization->limit_type) {
            MedicalConstants::MONETARY => [
                'value' => $utilization->remaining_amount,
                'formatted' => $utilization->formatted_remaining,
                'unit' => 'currency',
            ],
            MedicalConstants::COUNT => [
                'value' => $utilization->remaining_count,
                'formatted' => $utilization->formatted_remaining,
                'unit' => 'visits',
            ],
            MedicalConstants::DAYS => [
                'value' => $utilization->remaining_days,
                'formatted' => $utilization->formatted_remaining,
                'unit' => 'days',
            ],
            MedicalConstants::UNLIMITED => [
                'value' => null,
                'formatted' => 'Unlimited',
                'unit' => null,
            ],
            default => [
                'value' => null,
                'formatted' => 'N/A',
                'unit' => null,
            ],
        };
    }
}
