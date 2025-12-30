<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\Plan;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Constants\MedicalConstants;

class PremiumCalculatorService
{
    /**
     * Calculate premium for a single member.
     */
    public function calculateMemberPremium(
        RateCard $rateCard,
        int $age,
        string $memberType,
        ?string $gender = null,
        ?string $regionCode = null
    ): array {
        // Get base premium from rate card entry
        $basePremium = $this->getBasePremium($rateCard, $age, $gender, $regionCode);

        if ($basePremium === null) {
            return [
                'success' => false,
                'message' => "No rate entry found for age: {$age}",
            ];
        }

        // Apply member type factor
        $factor = $rateCard->getMemberTypeFactor($memberType);
        $premium = round($basePremium * $factor, 2);

        return [
            'success' => true,
            'base_premium' => $basePremium,
            'member_type_factor' => $factor,
            'premium' => $premium,
        ];
    }

    /**
     * Calculate premium for multiple members (family/group).
     */
    public function calculateFamilyPremium(RateCard $rateCard, array $members): array
    {
        // If tiered pricing, use tier calculation
        if ($rateCard->is_tiered) {
            return $this->calculateTieredPremium($rateCard, count($members));
        }

        $totalPremium = 0;
        $memberBreakdown = [];

        foreach ($members as $member) {
            $result = $this->calculateMemberPremium(
                $rateCard,
                $member['age'],
                $member['member_type'],
                $member['gender'] ?? null,
                $member['region_code'] ?? null
            );

            if (!$result['success']) {
                return $result;
            }

            $totalPremium += $result['premium'];
            $memberBreakdown[] = [
                'member_type' => $member['member_type'],
                'age' => $member['age'],
                'premium' => $result['premium'],
            ];
        }

        return [
            'success' => true,
            'members' => $memberBreakdown,
            'total_premium' => round($totalPremium, 2),
        ];
    }

    /**
     * Calculate tiered premium (flat rate based on family size).
     */
    public function calculateTieredPremium(RateCard $rateCard, int $memberCount): array
    {
        $tier = $rateCard->getTierPremium($memberCount);

        if (!$tier) {
            return [
                'success' => false,
                'message' => "No tier found for member count: {$memberCount}",
            ];
        }

        return [
            'success' => true,
            'tier_name' => $tier->tier_name,
            'member_count' => $memberCount,
            'premium' => $tier->calculatePremium($memberCount),
        ];
    }

    /**
     * Calculate addon premium.
     */
    public function calculateAddonPremium(
        PlanAddon $planAddon,
        float $basePremium = 0,
        int $memberCount = 1,
        ?int $age = null,
        ?string $gender = null
    ): array {
        // Included addons are free
        if ($planAddon->is_included) {
            return [
                'success' => true,
                'premium' => 0,
                'is_included' => true,
            ];
        }

        $rate = $planAddon->addon->getActiveRateForPlan($planAddon->plan_id);

        if (!$rate) {
            return [
                'success' => false,
                'message' => 'No active rate found for addon',
            ];
        }

        $premium = $rate->calculatePremium($memberCount, $basePremium, $age, $gender);

        return [
            'success' => true,
            'premium' => $premium,
            'pricing_type' => $rate->pricing_type,
            'is_included' => false,
        ];
    }

    /**
     * Calculate total premium with addons.
     */
    public function calculateTotalPremium(
        RateCard $rateCard,
        array $members,
        array $addonIds = []
    ): array {
        // Calculate base premium
        $baseResult = $this->calculateFamilyPremium($rateCard, $members);

        if (!$baseResult['success']) {
            return $baseResult;
        }

        $basePremium = $baseResult['total_premium'] ?? $baseResult['premium'];
        $addonTotal = 0;
        $addonBreakdown = [];

        // Calculate addon premiums
        if (!empty($addonIds)) {
            $planAddons = PlanAddon::where('plan_id', $rateCard->plan_id)
                ->whereIn('addon_id', $addonIds)
                ->where('is_active', true)
                ->with('addon')
                ->get();

            foreach ($planAddons as $planAddon) {
                $addonResult = $this->calculateAddonPremium(
                    $planAddon,
                    $basePremium,
                    count($members)
                );

                if ($addonResult['success']) {
                    $addonTotal += $addonResult['premium'];
                    $addonBreakdown[] = [
                        'addon_id' => $planAddon->addon_id,
                        'addon_name' => $planAddon->addon->name,
                        'premium' => $addonResult['premium'],
                        'is_included' => $addonResult['is_included'],
                    ];
                }
            }
        }

        // Add mandatory addons
        $mandatoryAddons = PlanAddon::where('plan_id', $rateCard->plan_id)
            ->where('availability', MedicalConstants::ADDON_AVAILABILITY_MANDATORY)
            ->where('is_active', true)
            ->whereNotIn('addon_id', $addonIds)
            ->with('addon')
            ->get();

        foreach ($mandatoryAddons as $planAddon) {
            $addonResult = $this->calculateAddonPremium(
                $planAddon,
                $basePremium,
                count($members)
            );

            if ($addonResult['success']) {
                $addonTotal += $addonResult['premium'];
                $addonBreakdown[] = [
                    'addon_id' => $planAddon->addon_id,
                    'addon_name' => $planAddon->addon->name,
                    'premium' => $addonResult['premium'],
                    'is_mandatory' => true,
                ];
            }
        }

        return [
            'success' => true,
            'base_premium' => $basePremium,
            'addon_premium' => round($addonTotal, 2),
            'addons' => $addonBreakdown,
            'total_premium' => round($basePremium + $addonTotal, 2),
            'currency' => $rateCard->currency,
            'frequency' => $rateCard->premium_frequency,
        ];
    }

    /**
     * Apply discount to premium.
     */
    public function applyDiscount(float $premium, string $valueType, float $value): float
    {
        if ($valueType === MedicalConstants::VALUE_TYPE_PERCENTAGE) {
            return round($premium * (1 - ($value / 100)), 2);
        }

        return round(max(0, $premium - $value), 2);
    }

    /**
     * Apply loading to premium.
     */
    public function applyLoading(float $premium, string $valueType, float $value): float
    {
        if ($valueType === MedicalConstants::VALUE_TYPE_PERCENTAGE) {
            return round($premium * (1 + ($value / 100)), 2);
        }

        return round($premium + $value, 2);
    }

    /**
     * Convert premium between frequencies.
     */
    public function convertFrequency(float $premium, string $from, string $to): float
    {
        // Convert to annual first
        $annual = match ($from) {
            MedicalConstants::PREMIUM_FREQUENCY_MONTHLY => $premium * 12,
            MedicalConstants::PREMIUM_FREQUENCY_QUARTERLY => $premium * 4,
            MedicalConstants::PREMIUM_FREQUENCY_SEMI_ANNUAL => $premium * 2,
            MedicalConstants::PREMIUM_FREQUENCY_ANNUAL => $premium,
            default => $premium * 12,
        };

        // Convert to target
        return match ($to) {
            MedicalConstants::PREMIUM_FREQUENCY_MONTHLY => round($annual / 12, 2),
            MedicalConstants::PREMIUM_FREQUENCY_QUARTERLY => round($annual / 4, 2),
            MedicalConstants::PREMIUM_FREQUENCY_SEMI_ANNUAL => round($annual / 2, 2),
            MedicalConstants::PREMIUM_FREQUENCY_ANNUAL => round($annual, 2),
            default => round($annual / 12, 2),
        };
    }

    /**
     * Get base premium from rate card.
     */
    protected function getBasePremium(
        RateCard $rateCard,
        int $age,
        ?string $gender,
        ?string $regionCode
    ): ?float {
        return $rateCard->getBasePremium($age, $gender, $regionCode);
    }
}