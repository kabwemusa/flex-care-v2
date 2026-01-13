<?php

namespace Modules\Medical\Services;

use Carbon\Carbon;
use Modules\Medical\Models\CorporateGroup;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Models\DiscountRule;

class GroupQuoteService
{
    protected PremiumService $premiumService;
    protected DiscountService $discountService;

    public function __construct(
        PremiumService $premiumService,
        DiscountService $discountService
    ) {
        $this->premiumService = $premiumService;
        $this->discountService = $discountService;
    }

    /**
     * Generate comprehensive group quote with tier and volume discount information.
     *
     * @param string $groupId
     * @param string $planId
     * @param array $membersData
     * @param array $options
     * @return array
     */
    public function generateGroupQuote(
        string $groupId,
        string $planId,
        array $membersData,
        array $options = []
    ): array {
        // Load related models
        $group = CorporateGroup::findOrFail($groupId);
        $plan = Plan::with(['scheme', 'rateCard.tiers', 'rateCard.entries'])->findOrFail($planId);
        $rateCard = $plan->rateCard;

        if (!$rateCard) {
            throw new \Exception('No rate card found for selected plan');
        }

        // Process members and calculate ages
        $processedMembers = $this->processMembers($membersData);
        $memberCount = count($processedMembers);

        // Get member summary (principals, spouses, children, etc.)
        $memberSummary = $this->getMemberSummary($processedMembers);

        // Calculate base premiums using PremiumService
        $premiumCalculation = $this->premiumService->calculatePremium(
            $rateCard->id,
            $processedMembers,
            $options['billing_frequency'] ?? 'monthly',
            $options['addons'] ?? []
        );

        // Get tier information
        $tierInfo = $this->getTierInformation($rateCard, $memberCount, $premiumCalculation);

        // Get volume discount information
        $volumeDiscounts = $this->getVolumeDiscounts($plan, $memberCount, $premiumCalculation['base_premium']);

        // Apply discounts
        $discountAmount = $this->discountService->calculateDiscounts(
            $premiumCalculation['total_premium'],
            $plan->id,
            null, // policy_id (not created yet)
            $memberCount,
            $options['discount_codes'] ?? []
        );

        // Calculate final premium breakdown
        $premiumBreakdown = $this->calculatePremiumBreakdown(
            $premiumCalculation,
            $tierInfo,
            $volumeDiscounts,
            $discountAmount
        );

        return [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'plan_info' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'scheme_name' => $plan->scheme->name,
                'rate_card_id' => $rateCard->id,
                'rate_card_name' => $rateCard->name,
                'premium_basis' => $rateCard->premium_basis,
            ],
            'member_summary' => $memberSummary,
            'tier_info' => $tierInfo,
            'volume_discounts' => $volumeDiscounts,
            'premium_breakdown' => $premiumBreakdown,
            'member_breakdown' => $premiumCalculation['member_breakdown'],
            'addon_breakdown' => $premiumCalculation['addon_breakdown'] ?? [],
            'billing_frequency' => $options['billing_frequency'] ?? 'monthly',
            'currency' => 'USD',
            'generated_at' => Carbon::now()->toIso8601String(),
            'valid_until' => Carbon::now()->addDays(30)->toIso8601String(),
        ];
    }

    /**
     * Process members data and calculate ages.
     */
    protected function processMembers(array $membersData): array
    {
        return array_map(function ($member) {
            $dob = Carbon::parse($member['date_of_birth']);
            $age = $dob->age;

            return [
                'date_of_birth' => $member['date_of_birth'],
                'age' => $age,
                'member_type' => $member['member_type'],
                'gender' => $member['gender'] ?? 'M',
            ];
        }, $membersData);
    }

    /**
     * Get member summary breakdown by type.
     */
    protected function getMemberSummary(array $members): array
    {
        $summary = [
            'total' => count($members),
            'principals' => 0,
            'spouses' => 0,
            'children' => 0,
            'parents' => 0,
            'other' => 0,
        ];

        foreach ($members as $member) {
            $type = strtolower($member['member_type']);
            if ($type === 'principal') {
                $summary['principals']++;
            } elseif ($type === 'spouse') {
                $summary['spouses']++;
            } elseif ($type === 'child') {
                $summary['children']++;
            } elseif ($type === 'parent') {
                $summary['parents']++;
            } else {
                $summary['other']++;
            }
        }

        return $summary;
    }

    /**
     * Get tier information including current tier and next tier benefits.
     */
    protected function getTierInformation(RateCard $rateCard, int $memberCount, array $premiumCalc): ?array
    {
        if ($rateCard->premium_basis !== 'tiered') {
            return null;
        }

        $tiers = $rateCard->tiers()
            ->orderBy('min_members')
            ->get();

        if ($tiers->isEmpty()) {
            return null;
        }

        // Find current tier
        $currentTier = null;
        $currentTierNumber = 0;

        foreach ($tiers as $index => $tier) {
            if ($memberCount >= $tier->min_members &&
                ($tier->max_members === null || $memberCount <= $tier->max_members)) {
                $currentTier = $tier;
                $currentTierNumber = $index + 1;
                break;
            }
        }

        if (!$currentTier) {
            // Use first tier if member count is below minimum
            $currentTier = $tiers->first();
            $currentTierNumber = 1;
        }

        // Find next tier
        $nextTier = null;
        $nextTierNumber = null;
        $membersNeeded = null;
        $potentialSavings = null;

        foreach ($tiers as $index => $tier) {
            if ($tier->min_members > $memberCount) {
                $nextTier = $tier;
                $nextTierNumber = $index + 1;
                $membersNeeded = $tier->min_members - $memberCount;

                // Calculate potential savings
                $currentTotalPremium = $premiumCalc['base_premium'];
                $nextTierPremium = $this->estimateTierPremium($nextTier, $tier->min_members);
                $potentialSavings = max(0, $currentTotalPremium - $nextTierPremium);

                break;
            }
        }

        return [
            'current_tier' => [
                'tier_number' => $currentTierNumber,
                'min_members' => $currentTier->min_members,
                'max_members' => $currentTier->max_members,
                'tier_premium' => $currentTier->tier_premium,
                'extra_member_premium' => $currentTier->extra_member_premium,
            ],
            'next_tier' => $nextTier ? [
                'tier_number' => $nextTierNumber,
                'min_members' => $nextTier->min_members,
                'max_members' => $nextTier->max_members,
                'members_needed' => $membersNeeded,
                'potential_savings' => round($potentialSavings, 2),
                'tier_premium' => $nextTier->tier_premium,
            ] : null,
        ];
    }

    /**
     * Estimate tier premium for a given member count.
     */
    protected function estimateTierPremium($tier, int $memberCount): float
    {
        $basePremium = $tier->tier_premium;
        $extraMembers = max(0, $memberCount - $tier->min_members);
        $extraPremium = $extraMembers * $tier->extra_member_premium;

        return $basePremium + $extraPremium;
    }

    /**
     * Get applicable volume discounts for the group.
     */
    protected function getVolumeDiscounts(Plan $plan, int $memberCount, float $basePremium): array
    {
        $discounts = DiscountRule::where('is_active', true)
            ->where('plan_id', $plan->id)
            ->orWhereNull('plan_id')
            ->get();

        $applicableDiscounts = [];

        foreach ($discounts as $discount) {
            $triggerRules = $discount->trigger_rules ?? [];

            // Check if discount applies to this member count
            $minGroupSize = $triggerRules['min_group_size'] ?? $triggerRules['min_members'] ?? 0;

            if ($memberCount >= $minGroupSize) {
                $discountAmount = $discount->discount_type === 'percentage'
                    ? ($basePremium * $discount->discount_value / 100)
                    : $discount->discount_value;

                $applicableDiscounts[] = [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'description' => $discount->description,
                    'discount_type' => $discount->discount_type,
                    'discount_value' => $discount->discount_value,
                    'discount_amount' => round($discountAmount, 2),
                    'triggered_by' => "member_count >= {$minGroupSize}",
                    'can_stack' => $discount->can_stack,
                ];
            }
        }

        return $applicableDiscounts;
    }

    /**
     * Calculate final premium breakdown.
     */
    protected function calculatePremiumBreakdown(
        array $premiumCalc,
        ?array $tierInfo,
        array $volumeDiscounts,
        float $discountAmount
    ): array {
        $basePremium = $premiumCalc['base_premium'];
        $addonPremium = $premiumCalc['addon_premium'] ?? 0;

        // Calculate tier savings (if applicable)
        $tierSavings = 0;
        if ($tierInfo && isset($tierInfo['current_tier'])) {
            // Tier savings already factored into base premium from PremiumService
            // This is informational only
            $tierSavings = 0;
        }

        // Calculate total volume discount
        $volumeDiscountTotal = array_sum(array_column($volumeDiscounts, 'discount_amount'));

        $subtotal = $basePremium + $addonPremium;
        $totalDiscounts = $discountAmount + $volumeDiscountTotal;
        $totalPremium = max(0, $subtotal - $totalDiscounts);

        return [
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'subtotal' => round($subtotal, 2),
            'tier_savings' => round($tierSavings, 2),
            'volume_discounts' => round($volumeDiscountTotal, 2),
            'other_discounts' => round($discountAmount, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'total_premium' => round($totalPremium, 2),
            'per_member_average' => round($totalPremium / max(1, count($premiumCalc['member_breakdown'])), 2),
        ];
    }
}
