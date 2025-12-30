<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\PlanBenefit;
use Modules\Medical\Models\PlanExclusion;
use Modules\Medical\Models\MedPlanWaitingPeriod;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class BenefitService
{
    /**
     * Check if a member can claim a benefit.
     */
    public function checkEligibility(
        string $planId,
        string $benefitId,
        string $memberType,
        int $memberAge,
        ?Carbon $coverStartDate = null,
        ?float $claimAmount = null,
        float $usedAmount = 0
    ): array {
        $planBenefit = PlanBenefit::where('plan_id', $planId)
            ->where('benefit_id', $benefitId)
            ->with('benefit')
            ->first();

        if (!$planBenefit) {
            return [
                'eligible' => false,
                'reason' => 'Benefit not found in plan',
            ];
        }

        // Check if covered
        if (!$planBenefit->is_covered) {
            return [
                'eligible' => false,
                'reason' => 'Benefit is not covered',
            ];
        }

        // Check member type applicability
        if (!$planBenefit->benefit->isApplicableToMemberType($memberType)) {
            return [
                'eligible' => false,
                'reason' => 'Benefit not applicable to this member type',
            ];
        }

        // Check exclusions
        $exclusion = $this->checkExclusion($planId, $benefitId, $coverStartDate);
        if ($exclusion['excluded']) {
            return [
                'eligible' => false,
                'reason' => $exclusion['reason'],
            ];
        }

        // Check waiting period
        if ($coverStartDate) {
            $waiting = $this->checkWaitingPeriod($planBenefit, $coverStartDate);
            if (!$waiting['elapsed']) {
                return [
                    'eligible' => false,
                    'reason' => "Waiting period: {$waiting['days_remaining']} days remaining",
                    'waiting_end_date' => $waiting['end_date'],
                ];
            }
        }

        // Check limits
        $limit = $this->checkLimit($planBenefit, $memberType, $memberAge, $claimAmount, $usedAmount);

        return [
            'eligible' => $limit['within_limit'],
            'reason' => $limit['within_limit'] ? 'Eligible' : $limit['message'],
            'limit' => $limit['limit'],
            'used' => $limit['used'],
            'remaining' => $limit['remaining'],
        ];
    }

    /**
     * Check if benefit is excluded.
     */
    public function checkExclusion(string $planId, string $benefitId, ?Carbon $coverStartDate = null): array
    {
        $exclusions = PlanExclusion::where('plan_id', $planId)
            ->where('is_active', true)
            ->where(function ($q) use ($benefitId) {
                $q->whereNull('benefit_id')->orWhere('benefit_id', $benefitId);
            })
            ->get();

        foreach ($exclusions as $exclusion) {
            // Time-limited exclusion
            if ($exclusion->is_time_limited && $coverStartDate) {
                if (!$exclusion->hasExclusionPeriodElapsed($coverStartDate)) {
                    return [
                        'excluded' => true,
                        'reason' => "Excluded: {$exclusion->name} (time-limited)",
                    ];
                }
                continue;
            }

            // Absolute exclusion
            if ($exclusion->exclusion_type === MedicalConstants::EXCLUSION_TYPE_ABSOLUTE) {
                return [
                    'excluded' => true,
                    'reason' => "Excluded: {$exclusion->name}",
                ];
            }
        }

        return ['excluded' => false, 'reason' => null];
    }

    /**
     * Check waiting period.
     */
    public function checkWaitingPeriod(PlanBenefit $planBenefit, Carbon $coverStartDate): array
    {
        $waitingDays = $planBenefit->getEffectiveWaitingDays();

        if ($waitingDays <= 0) {
            return ['elapsed' => true, 'days_remaining' => 0];
        }

        $endDate = $coverStartDate->copy()->addDays($waitingDays);

        if (now()->gte($endDate)) {
            return ['elapsed' => true, 'days_remaining' => 0, 'end_date' => $endDate->toDateString()];
        }

        return [
            'elapsed' => false,
            'days_remaining' => now()->diffInDays($endDate, false),
            'end_date' => $endDate->toDateString(),
        ];
    }

    /**
     * Check benefit limit.
     */
    public function checkLimit(
        PlanBenefit $planBenefit,
        string $memberType,
        int $memberAge,
        ?float $claimAmount,
        float $usedAmount
    ): array {
        // Unlimited benefit
        if ($planBenefit->effective_limit_type === MedicalConstants::UNLIMITED) {
            return [
                'within_limit' => true,
                'limit' => null,
                'used' => $usedAmount,
                'remaining' => null,
                'message' => 'Unlimited',
            ];
        }

        // Get effective limit for member
        $limit = $planBenefit->getEffectiveLimitAmount($memberType, $memberAge);
        $remaining = max(0, ($limit ?? 0) - $usedAmount);

        // Check per-claim limit
        if ($claimAmount !== null && $planBenefit->per_claim_limit !== null) {
            if ($claimAmount > $planBenefit->per_claim_limit) {
                return [
                    'within_limit' => false,
                    'limit' => $limit,
                    'used' => $usedAmount,
                    'remaining' => $remaining,
                    'message' => "Exceeds per-claim limit of {$planBenefit->per_claim_limit}",
                ];
            }
        }

        // Check overall limit
        $withinLimit = $claimAmount === null || $claimAmount <= $remaining;

        return [
            'within_limit' => $withinLimit,
            'limit' => $limit,
            'used' => $usedAmount,
            'remaining' => $remaining,
            'message' => $withinLimit ? 'Within limit' : "Exceeds remaining limit of {$remaining}",
        ];
    }

    /**
     * Get benefit schedule for a plan.
     */
    public function getBenefitSchedule(string $planId, ?string $memberType = null): array
    {
        $planBenefits = PlanBenefit::where('plan_id', $planId)
            ->where('is_visible', true)
            ->with(['benefit.category', 'memberLimits'])
            ->whereHas('benefit', fn($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order')
            ->get();

        $schedule = [];

        foreach ($planBenefits as $pb) {
            $category = $pb->benefit->category->name ?? 'Other';

            if (!isset($schedule[$category])) {
                $schedule[$category] = [];
            }

            $limit = $memberType
                ? $pb->getEffectiveLimitAmount($memberType, 0)
                : $pb->limit_amount;

            $schedule[$category][] = [
                'benefit_id' => $pb->benefit_id,
                'name' => $pb->benefit->name,
                'is_covered' => $pb->is_covered,
                'limit' => $limit,
                'display_value' => $pb->formatted_display_value,
                'waiting_days' => $pb->getEffectiveWaitingDays(),
                'requires_preauth' => $pb->benefit->requires_preauth,
            ];
        }

        return $schedule;
    }

    /**
     * Get utilization summary for a member.
     */
    public function getUtilization(string $planId, array $claimsData): array
    {
        $planBenefits = PlanBenefit::where('plan_id', $planId)
            ->where('is_covered', true)
            ->with('benefit')
            ->get();

        $utilization = [];

        foreach ($planBenefits as $pb) {
            $benefitClaims = array_filter($claimsData, fn($c) => ($c['benefit_id'] ?? null) === $pb->benefit_id);
            $usedAmount = array_sum(array_column($benefitClaims, 'amount'));
            $claimCount = count($benefitClaims);

            $isUnlimited = $pb->effective_limit_type === MedicalConstants::UNLIMITED;
            $limit = $pb->limit_amount;
            $remaining = $isUnlimited ? null : max(0, ($limit ?? 0) - $usedAmount);
            $percentage = (!$isUnlimited && $limit > 0) ? round(($usedAmount / $limit) * 100, 2) : 0;

            $utilization[] = [
                'benefit_id' => $pb->benefit_id,
                'benefit_name' => $pb->benefit->name,
                'limit' => $limit,
                'used' => $usedAmount,
                'remaining' => $remaining,
                'claim_count' => $claimCount,
                'utilization_percent' => $percentage,
                'is_exhausted' => !$isUnlimited && $remaining <= 0,
            ];
        }

        return $utilization;
    }
}