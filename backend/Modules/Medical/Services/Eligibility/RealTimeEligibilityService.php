<?php

namespace Modules\Medical\Services\Eligibility;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Benefit;
use Modules\Medical\Models\MemberBenefitUtilization;
use Modules\Medical\DTOs\EligibilityResponse;
use Modules\Medical\DTOs\MemberInfo;
use Modules\Medical\DTOs\PolicyInfo;
use Modules\Medical\DTOs\BenefitBalance;
use Modules\Medical\DTOs\CopayEstimate;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class RealTimeEligibilityService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const CACHE_TTL_MEMBER = 300; // 5 minutes
    private const CACHE_TTL_BENEFITS = 600; // 10 minutes
    private const CACHE_TTL_UTILIZATION = 60; // 1 minute (more dynamic)

    // =========================================================================
    // MAIN ELIGIBILITY CHECK
    // =========================================================================

    /**
     * Check member eligibility - Target: < 500ms
     *
     * @param string $cardNumber Member card number
     * @param array $serviceCodes Optional service/benefit codes to check
     * @param string|null $providerId Provider making the request
     * @param float|null $estimatedAmount Optional estimated amount for copay calculation
     * @return EligibilityResponse
     */
    public function checkEligibility(
        string $cardNumber,
        array $serviceCodes = [],
        ?string $providerId = null,
        ?float $estimatedAmount = null
    ): EligibilityResponse {
        $startTime = microtime(true);

        try {
            // 1. Find member by card number (with caching)
            $member = $this->findMemberByCard($cardNumber);

            if (!$member) {
                return EligibilityResponse::ineligible(
                    'Member not found',
                    'MEMBER_NOT_FOUND',
                    $this->elapsed($startTime)
                );
            }

            // 2. Check member eligibility
            $memberCheck = $this->checkMemberEligibility($member);
            if (!$memberCheck['eligible']) {
                return EligibilityResponse::ineligible(
                    $memberCheck['reason'],
                    $memberCheck['code'],
                    $this->elapsed($startTime)
                );
            }

            // 3. Get policy and check status
            $policy = $member->policy;
            $policyCheck = $this->checkPolicyEligibility($policy);
            if (!$policyCheck['eligible']) {
                return EligibilityResponse::ineligible(
                    $policyCheck['reason'],
                    $policyCheck['code'],
                    $this->elapsed($startTime)
                );
            }

            // 4. Build member and policy info
            $memberInfo = $this->buildMemberInfo($member);
            $policyInfo = $this->buildPolicyInfo($policy);

            // 5. Get benefits with utilization
            $benefits = $this->getBenefitBalances($member, $policy, $serviceCodes);

            // 6. Get active exclusions
            $exclusions = $this->getActiveExclusions($member);

            // 7. Get active waiting periods
            $waitingPeriods = $this->getActiveWaitingPeriods($member, $policy);

            // 8. Calculate copay estimate if amount provided
            $copayEstimate = null;
            if ($estimatedAmount !== null && !empty($benefits)) {
                $copayEstimate = $this->estimateCopay($member, $policy, $benefits, $estimatedAmount);
            }

            return new EligibilityResponse(
                eligible: true,
                member: $memberInfo,
                policy: $policyInfo,
                benefits: $benefits,
                exclusions: $exclusions,
                waitingPeriods: $waitingPeriods,
                copayEstimate: $copayEstimate,
                processingTimeMs: $this->elapsed($startTime),
            );

        } catch (\Exception $e) {
            report($e);
            return EligibilityResponse::ineligible(
                'Eligibility check failed',
                'INTERNAL_ERROR',
                $this->elapsed($startTime)
            );
        }
    }

    /**
     * Get benefit summary for a member (cached)
     */
    public function getBenefitSummary(string $memberId): array
    {
        $startTime = microtime(true);

        $cacheKey = "benefit_summary:{$memberId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_BENEFITS, function () use ($memberId, $startTime) {
            $member = Member::with(['policy.plan'])->find($memberId);

            if (!$member || !$member->policy) {
                return [
                    'success' => false,
                    'error' => 'Member or policy not found',
                ];
            }

            $benefits = $this->getBenefitBalances($member, $member->policy, []);

            // Group by category
            $grouped = [];
            foreach ($benefits as $benefit) {
                $category = $benefit->category;
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [
                        'category' => $category,
                        'category_name' => $benefit->categoryName,
                        'benefits' => [],
                        'total_limit' => 0,
                        'total_used' => 0,
                        'exhausted_count' => 0,
                    ];
                }
                $grouped[$category]['benefits'][] = $benefit->toArray();
                $grouped[$category]['total_limit'] += $benefit->limit ?? 0;
                $grouped[$category]['total_used'] += $benefit->used;
                if ($benefit->isExhausted) {
                    $grouped[$category]['exhausted_count']++;
                }
            }

            return [
                'success' => true,
                'member_id' => $memberId,
                'categories' => array_values($grouped),
                'total_benefits' => count($benefits),
                'processing_time_ms' => $this->elapsed($startTime),
            ];
        });
    }

    /**
     * Estimate copay for proposed services
     */
    public function estimateCopayForServices(
        string $cardNumber,
        array $services,
        ?string $providerId = null
    ): CopayEstimate {
        $member = $this->findMemberByCard($cardNumber);

        if (!$member || !$member->policy) {
            return new CopayEstimate(
                totalAmount: 0,
                copayAmount: 0,
                deductibleAmount: 0,
                payableAmount: 0,
            );
        }

        $policy = $member->policy;
        $plan = $policy->plan;

        $lineEstimates = [];
        $totalAmount = 0;
        $totalCopay = 0;
        $totalDeductible = 0;

        foreach ($services as $service) {
            $amount = (float) ($service['amount'] ?? 0);
            $benefitCode = $service['benefit_code'] ?? null;

            $totalAmount += $amount;

            // Get benefit-specific copay
            $copayConfig = $this->getBenefitCopayConfig($plan, $benefitCode);

            $lineCopay = 0;
            $lineDeductible = 0;

            if ($copayConfig) {
                if ($copayConfig['type'] === 'percentage') {
                    $lineCopay = $amount * ($copayConfig['value'] / 100);
                } elseif ($copayConfig['type'] === 'fixed') {
                    $lineCopay = min($copayConfig['value'], $amount);
                }
            }

            $totalCopay += $lineCopay;
            $totalDeductible += $lineDeductible;

            $lineEstimates[] = [
                'benefit_code' => $benefitCode,
                'amount' => $amount,
                'copay' => round($lineCopay, 2),
                'deductible' => round($lineDeductible, 2),
                'payable' => round($amount - $lineCopay - $lineDeductible, 2),
            ];
        }

        return new CopayEstimate(
            totalAmount: round($totalAmount, 2),
            copayAmount: round($totalCopay, 2),
            deductibleAmount: round($totalDeductible, 2),
            payableAmount: round($totalAmount - $totalCopay - $totalDeductible, 2),
            lineEstimates: $lineEstimates,
        );
    }

    /**
     * Validate card number exists and is active
     */
    public function validateCard(string $cardNumber): array
    {
        $startTime = microtime(true);

        $member = $this->findMemberByCard($cardNumber);

        if (!$member) {
            return [
                'valid' => false,
                'reason' => 'Card number not found',
                'code' => 'CARD_NOT_FOUND',
                'processing_time_ms' => $this->elapsed($startTime),
            ];
        }

        $memberCheck = $this->checkMemberEligibility($member);

        return [
            'valid' => $memberCheck['eligible'],
            'reason' => $memberCheck['reason'] ?? null,
            'code' => $memberCheck['code'] ?? null,
            'member_name' => $member->full_name,
            'member_type' => $member->member_type,
            'card_status' => $member->card_status,
            'processing_time_ms' => $this->elapsed($startTime),
        ];
    }

    // =========================================================================
    // PRIVATE METHODS - MEMBER & POLICY CHECKS
    // =========================================================================

    private function findMemberByCard(string $cardNumber): ?Member
    {
        $cacheKey = "member_by_card:" . md5($cardNumber);

        $memberId = Cache::get($cacheKey);

        if ($memberId) {
            return Member::with([
                'policy.plan.scheme',
                'policy.group',
                'activeExclusions',
            ])->find($memberId);
        }

        $member = Member::with([
            'policy.plan.scheme',
            'policy.group',
            'activeExclusions',
        ])
            ->where('card_number', $cardNumber)
            ->first();

        if ($member) {
            Cache::put($cacheKey, $member->id, self::CACHE_TTL_MEMBER);
        }

        return $member;
    }

    private function checkMemberEligibility(Member $member): array
    {
        // Check member status
        if ($member->status !== MedicalConstants::MEMBER_STATUS_ACTIVE) {
            return [
                'eligible' => false,
                'reason' => "Member is {$member->status_label}",
                'code' => 'MEMBER_NOT_ACTIVE',
            ];
        }

        // Check card status
        if ($member->card_status !== MedicalConstants::CARD_STATUS_ACTIVE) {
            return [
                'eligible' => false,
                'reason' => "Member card is {$member->card_status_label}",
                'code' => 'CARD_NOT_ACTIVE',
            ];
        }

        // Check coverage dates
        if (!$member->has_cover) {
            return [
                'eligible' => false,
                'reason' => 'Member does not have active coverage',
                'code' => 'NO_ACTIVE_COVER',
            ];
        }

        // Check blacklist
        if ($member->is_fraud_blacklist ?? false) {
            return [
                'eligible' => false,
                'reason' => 'Member is not eligible for services',
                'code' => 'MEMBER_BLACKLISTED',
            ];
        }

        return ['eligible' => true];
    }

    private function checkPolicyEligibility(Policy $policy): array
    {
        if ($policy->status !== MedicalConstants::POLICY_STATUS_ACTIVE) {
            return [
                'eligible' => false,
                'reason' => "Policy is {$policy->status_label}",
                'code' => 'POLICY_NOT_ACTIVE',
            ];
        }

        // Check policy dates
        $now = now()->startOfDay();
        if ($policy->inception_date > $now) {
            return [
                'eligible' => false,
                'reason' => 'Policy has not started yet',
                'code' => 'POLICY_NOT_STARTED',
            ];
        }

        if ($policy->expiry_date < $now) {
            return [
                'eligible' => false,
                'reason' => 'Policy has expired',
                'code' => 'POLICY_EXPIRED',
            ];
        }

        return ['eligible' => true];
    }

    // =========================================================================
    // PRIVATE METHODS - BENEFIT BALANCES
    // =========================================================================

    private function getBenefitBalances(Member $member, Policy $policy, array $serviceCodes): array
    {
        $plan = $policy->plan;

        // Get plan benefits with utilization
        $query = DB::table('med_plan_benefits as pb')
            ->join('med_benefits as b', 'pb.benefit_id', '=', 'b.id')
            ->join('med_benefit_categories as bc', 'b.category_id', '=', 'bc.id')
            ->leftJoin('med_member_benefit_utilization as u', function ($join) use ($member) {
                $join->on('u.benefit_id', '=', 'b.id')
                    ->where('u.member_id', '=', $member->id)
                    ->where('u.period_start', '<=', now())
                    ->where('u.period_end', '>=', now());
            })
            ->where('pb.plan_id', $plan->id)
            ->where('pb.is_active', true)
            ->select([
                'b.id as benefit_id',
                'b.code as benefit_code',
                'b.name as benefit_name',
                'b.requires_preauth',
                'bc.code as category_code',
                'bc.name as category_name',
                'pb.id as plan_benefit_id',
                'pb.limit_type',
                'pb.limit_amount',
                'pb.limit_count',
                'pb.limit_days',
                'pb.waiting_period_days',
                DB::raw('COALESCE(u.used_amount, 0) as used_amount'),
                DB::raw('COALESCE(u.used_count, 0) as used_count'),
                DB::raw('COALESCE(u.used_days, 0) as used_days'),
                DB::raw('COALESCE(u.reserved_amount, 0) as reserved_amount'),
                DB::raw('COALESCE(u.reserved_count, 0) as reserved_count'),
                DB::raw('COALESCE(u.reserved_days, 0) as reserved_days'),
                DB::raw('COALESCE(u.is_exhausted, false) as is_exhausted'),
            ]);

        // Filter by service codes if provided
        if (!empty($serviceCodes)) {
            $query->whereIn('b.code', $serviceCodes);
        }

        $results = $query->get();

        $benefits = [];
        foreach ($results as $row) {
            // Calculate limit and available based on limit type
            $limit = null;
            $used = 0;
            $reserved = 0;
            $available = 0;

            switch ($row->limit_type) {
                case 'monetary':
                case 'amount':
                    $limit = (float) $row->limit_amount;
                    $used = (float) $row->used_amount;
                    $reserved = (float) $row->reserved_amount;
                    break;
                case 'count':
                case 'visits':
                    $limit = (float) $row->limit_count;
                    $used = (float) $row->used_count;
                    $reserved = (float) $row->reserved_count;
                    break;
                case 'days':
                    $limit = (float) $row->limit_days;
                    $used = (float) $row->used_days;
                    $reserved = (float) $row->reserved_days;
                    break;
                case 'unlimited':
                    $limit = null;
                    break;
            }

            if ($limit !== null) {
                $available = max(0, $limit - $used - $reserved);
            } else {
                $available = PHP_FLOAT_MAX; // Unlimited
            }

            $utilizationPercent = $limit ? round(($used / $limit) * 100, 2) : 0;

            // Check waiting period
            $inWaitingPeriod = false;
            $waitingDaysRemaining = null;
            if ($row->waiting_period_days && $member->cover_start_date) {
                $waitingEndDate = Carbon::parse($member->cover_start_date)->addDays($row->waiting_period_days);
                if ($waitingEndDate->isFuture()) {
                    $inWaitingPeriod = true;
                    $waitingDaysRemaining = (int) now()->diffInDays($waitingEndDate, false);
                }
            }

            // Get copay config
            $copayConfig = $this->getBenefitCopayConfig($plan, $row->benefit_code);

            $benefits[] = new BenefitBalance(
                benefitId: $row->benefit_id,
                benefitCode: $row->benefit_code,
                benefitName: $row->benefit_name,
                category: $row->category_code,
                categoryName: $row->category_name,
                limitType: $row->limit_type ?? 'monetary',
                limit: $limit,
                used: $used,
                reserved: $reserved,
                available: $limit === null ? PHP_FLOAT_MAX : $available,
                utilizationPercent: $utilizationPercent,
                isExhausted: (bool) $row->is_exhausted || ($limit !== null && $available <= 0),
                requiresPreauth: (bool) $row->requires_preauth,
                copayType: $copayConfig['type'] ?? null,
                copayValue: $copayConfig['value'] ?? null,
                waitingDaysRemaining: $waitingDaysRemaining,
                inWaitingPeriod: $inWaitingPeriod,
                formattedLimit: $this->formatLimit($limit, $row->limit_type),
                formattedUsed: $this->formatLimit($used, $row->limit_type),
                formattedAvailable: $limit === null ? 'Unlimited' : $this->formatLimit($available, $row->limit_type),
            );
        }

        return $benefits;
    }

    private function getActiveExclusions(Member $member): array
    {
        $exclusions = [];

        foreach ($member->activeExclusions ?? [] as $exclusion) {
            $exclusions[] = [
                'type' => $exclusion->exclusion_type,
                'name' => $exclusion->exclusion_name,
                'icd10_codes' => $exclusion->icd10_codes,
                'is_permanent' => $exclusion->is_permanent,
                'end_date' => $exclusion->end_date?->format('Y-m-d'),
            ];
        }

        return $exclusions;
    }

    private function getActiveWaitingPeriods(Member $member, Policy $policy): array
    {
        if (!$member->cover_start_date) {
            return [];
        }

        $plan = $policy->plan;
        $waitingPeriods = [];

        // Get plan waiting periods
        $planWaitingPeriods = DB::table('med_plan_waiting_periods')
            ->where('plan_id', $plan->id)
            ->where('is_active', true)
            ->get();

        foreach ($planWaitingPeriods as $wp) {
            $endDate = Carbon::parse($member->cover_start_date)->addDays($wp->waiting_period_days);

            if ($endDate->isFuture()) {
                $waitingPeriods[] = [
                    'type' => $wp->waiting_type,
                    'days' => $wp->waiting_period_days,
                    'end_date' => $endDate->format('Y-m-d'),
                    'days_remaining' => (int) now()->diffInDays($endDate, false),
                ];
            }
        }

        return $waitingPeriods;
    }

    // =========================================================================
    // PRIVATE METHODS - HELPERS
    // =========================================================================

    private function buildMemberInfo(Member $member): MemberInfo
    {
        return new MemberInfo(
            id: $member->id,
            memberNumber: $member->member_number,
            cardNumber: $member->card_number,
            name: $member->full_name,
            memberType: $member->member_type,
            memberTypeLabel: $member->member_type_label,
            age: $member->age,
            ageBand: $member->age_band,
            gender: $member->gender,
            status: $member->status,
            statusLabel: $member->status_label,
            cardStatus: $member->card_status,
        );
    }

    private function buildPolicyInfo(Policy $policy): PolicyInfo
    {
        $plan = $policy->plan;

        return new PolicyInfo(
            id: $policy->id,
            policyNumber: $policy->policy_number,
            status: $policy->status,
            statusLabel: $policy->status_label,
            planId: $plan->id,
            planName: $plan->name,
            planCode: $plan->code,
            inceptionDate: $policy->inception_date->format('Y-m-d'),
            expiryDate: $policy->expiry_date->format('Y-m-d'),
            schemeName: $plan->scheme?->name,
            groupName: $policy->group?->name,
        );
    }

    private function estimateCopay(Member $member, Policy $policy, array $benefits, float $amount): CopayEstimate
    {
        // Simple copay estimation - can be made more sophisticated
        $totalCopay = 0;
        $totalDeductible = 0;

        // Use the first benefit's copay config if multiple
        if (!empty($benefits)) {
            $benefit = $benefits[0];
            if ($benefit->copayType === 'percentage' && $benefit->copayValue) {
                $totalCopay = $amount * ($benefit->copayValue / 100);
            } elseif ($benefit->copayType === 'fixed' && $benefit->copayValue) {
                $totalCopay = min($benefit->copayValue, $amount);
            }
        }

        return new CopayEstimate(
            totalAmount: round($amount, 2),
            copayAmount: round($totalCopay, 2),
            deductibleAmount: round($totalDeductible, 2),
            payableAmount: round(max(0, $amount - $totalCopay - $totalDeductible), 2),
        );
    }

    private function getBenefitCopayConfig($plan, ?string $benefitCode): ?array
    {
        if (!$benefitCode || !$plan) {
            return null;
        }

        // Check plan benefit for copay config
        $planBenefit = DB::table('med_plan_benefits as pb')
            ->join('med_benefits as b', 'pb.benefit_id', '=', 'b.id')
            ->where('pb.plan_id', $plan->id)
            ->where('b.code', $benefitCode)
            ->select('pb.cost_sharing')
            ->first();

        if ($planBenefit && $planBenefit->cost_sharing) {
            $costSharing = json_decode($planBenefit->cost_sharing, true);
            if (isset($costSharing['copay'])) {
                return $costSharing['copay'];
            }
        }

        return null;
    }

    private function formatLimit(?float $value, ?string $type): ?string
    {
        if ($value === null) {
            return 'Unlimited';
        }

        return match ($type) {
            'monetary', 'amount' => 'K' . number_format($value, 2),
            'count', 'visits' => (int) $value . ' visits',
            'days' => (int) $value . ' days',
            default => number_format($value, 2),
        };
    }

    private function elapsed(float $startTime): int
    {
        return (int) ((microtime(true) - $startTime) * 1000);
    }
}
