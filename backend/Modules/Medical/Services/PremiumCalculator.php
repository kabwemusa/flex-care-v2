<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Constants\MedicalConstants;

class PremiumCalculator
{
    /**
     * Calculate and update policy premiums.
     */
    public function calculate(Policy $policy): void
    {
        $breakdown = $this->calculateWithBreakdown($policy);

        $policy->base_premium = $breakdown['base_premium'];
        $policy->addon_premium = $breakdown['addon_premium'];
        $policy->loading_amount = $breakdown['loading_amount'];
        // Discount is usually set separately via promo codes
        $policy->total_premium = $breakdown['total_premium'];
        $policy->tax_amount = $breakdown['tax_amount'];
        $policy->gross_premium = $breakdown['gross_premium'];
        
        $policy->save();
    }

    /**
     * Calculate premiums with detailed breakdown.
     */
    public function calculateWithBreakdown(Policy $policy): array
    {
        $policy->load(['members.activeLoadings', 'policyAddons.addon', 'rateCard.entries', 'plan']);

        $basePremium = 0;
        $loadingAmount = 0;
        $memberBreakdown = [];

        // Calculate member premiums
        foreach ($policy->members as $member) {
            $memberPremium = $this->calculateMemberBasePremium($member, $policy);
            $memberLoading = $member->activeLoadings->sum('loading_amount');

            $basePremium += $memberPremium;
            $loadingAmount += $memberLoading;

            $memberBreakdown[] = [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'name' => $member->full_name,
                'member_type' => $member->member_type,
                'age' => $member->age,
                'base_premium' => $memberPremium,
                'loading_amount' => $memberLoading,
                'total' => $memberPremium + $memberLoading,
            ];
        }

        // Calculate addon premiums
        $addonPremium = 0;
        $addonBreakdown = [];
        
        foreach ($policy->policyAddons as $policyAddon) {
            if ($policyAddon->is_active) {
                $premium = $policyAddon->premium;
                $addonPremium += $premium;

                $addonBreakdown[] = [
                    'addon_id' => $policyAddon->addon_id,
                    'addon_name' => $policyAddon->addon?->name,
                    'premium' => $premium,
                ];
            }
        }

        // Calculate totals
        $discountAmount = $policy->discount_amount ?? 0;
        $totalPremium = $basePremium + $addonPremium + $loadingAmount - $discountAmount;
        
        // Tax calculation (configurable - example: 16% VAT)
        $taxRate = config('medical.tax_rate', 0); // Set to 0 if no tax on insurance
        $taxAmount = round($totalPremium * $taxRate, 2);
        
        $grossPremium = $totalPremium + $taxAmount;

        return [
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'loading_amount' => round($loadingAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'total_premium' => round($totalPremium, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'gross_premium' => round($grossPremium, 2),
            'member_count' => $policy->members->count(),
            'member_breakdown' => $memberBreakdown,
            'addon_breakdown' => $addonBreakdown,
            'billing_frequency' => $policy->billing_frequency,
            'per_period_amount' => round($grossPremium, 2),
            'annual_amount' => $this->annualize($grossPremium, $policy->billing_frequency),
        ];
    }

    /**
     * Calculate base premium for a single member.
     */
    public function calculateMemberBasePremium(Member $member, ?Policy $policy = null): float
    {
        $policy = $policy ?? $member->policy;
        $rateCard = $policy->rateCard;

        if (!$rateCard) {
            return 0;
        }

        $rateCard->load(['entries', 'tiers']);

        // Find applicable rate entry based on member type and age
        $entry = $this->findApplicableRateEntry($rateCard, $member);

        if (!$entry) {
            return 0;
        }

        $basePremium = (float) $entry->premium;

        // Apply tier multiplier if applicable
        $tier = $this->findApplicableTier($rateCard, $policy);
        if ($tier) {
            $basePremium *= $tier->multiplier;
        }

        return round($basePremium, 2);
    }

    /**
     * Calculate and update member premium.
     */
    public function calculateMemberPremium(Member $member): void
    {
        $basePremium = $this->calculateMemberBasePremium($member);
        $loadingAmount = $member->activeLoadings()->sum('loading_amount');

        $member->premium = $basePremium;
        $member->loading_amount = $loadingAmount;
        $member->save();
    }

    /**
     * Find applicable rate entry for a member.
     */
    protected function findApplicableRateEntry(RateCard $rateCard, Member $member): ?object
    {
        $entries = $rateCard->entries;
        $age = $member->age;
        $memberType = $member->member_type;

        // First try to find exact member type match with age band
        $entry = $entries->first(function ($e) use ($age, $memberType) {
            $ageMatch = $age >= $e->age_from && $age <= $e->age_to;
            $typeMatch = empty($e->member_type) || $e->member_type === $memberType;
            return $ageMatch && $typeMatch;
        });

        if ($entry) {
            return $entry;
        }

        // Fallback: find by age only
        return $entries->first(function ($e) use ($age) {
            return $age >= $e->age_from && $age <= $e->age_to;
        });
    }

    /**
     * Find applicable tier based on group size or other criteria.
     */
    protected function findApplicableTier(RateCard $rateCard, Policy $policy): ?object
    {
        if ($rateCard->tiers->isEmpty()) {
            return null;
        }

        $memberCount = $policy->member_count;

        return $rateCard->tiers->first(function ($tier) use ($memberCount) {
            return $memberCount >= $tier->min_members 
                && ($tier->max_members === null || $memberCount <= $tier->max_members);
        });
    }

    /**
     * Convert premium to annual amount.
     */
    protected function annualize(float $amount, string $frequency): float
    {
        return match($frequency) {
            MedicalConstants::BILLING_MONTHLY => $amount * 12,
            MedicalConstants::BILLING_QUARTERLY => $amount * 4,
            MedicalConstants::BILLING_SEMI_ANNUAL => $amount * 2,
            MedicalConstants::BILLING_ANNUAL => $amount,
            default => $amount,
        };
    }

    /**
     * Quote premium for a potential policy (without creating).
     */
    public function quote(array $data): array
    {
        // Create a temporary policy object for calculation
        $policy = new Policy($data);
        $policy->id = 'temp';

        // Load rate card
        if (!empty($data['rate_card_id'])) {
            $policy->setRelation('rateCard', RateCard::with(['entries', 'tiers'])->find($data['rate_card_id']));
        }

        // Create temporary members
        $members = collect();
        if (!empty($data['members'])) {
            foreach ($data['members'] as $memberData) {
                $member = new Member($memberData);
                $members->push($member);
            }
        }
        $policy->setRelation('members', $members);

        // Calculate
        $basePremium = 0;
        foreach ($members as $member) {
            $basePremium += $this->calculateMemberBasePremium($member, $policy);
        }

        // Addons
        $addonPremium = 0;
        if (!empty($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                $addonPremium += $addon['premium'] ?? 0;
            }
        }

        $totalPremium = $basePremium + $addonPremium;
        $taxAmount = round($totalPremium * config('medical.tax_rate', 0), 2);
        $grossPremium = $totalPremium + $taxAmount;

        return [
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'total_premium' => round($totalPremium, 2),
            'tax_amount' => $taxAmount,
            'gross_premium' => round($grossPremium, 2),
            'member_count' => $members->count(),
            'billing_frequency' => $data['billing_frequency'] ?? MedicalConstants::BILLING_MONTHLY,
        ];
    }
}