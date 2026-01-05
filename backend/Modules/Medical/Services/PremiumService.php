<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Models\Addon;
use Modules\Medical\Models\AddonRate;
use Modules\Medical\Constants\MedicalConstants;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PremiumService
{
    // =========================================================================
    // 1. APPLICATION (SAVED DATA) CALCULATION
    // =========================================================================

    /**
     * Calculate and update premium for a saved Application.
     */
    public function calculateApplicationPremium(Application $application): array
    {
        // Eager load everything needed for calculation
        $application->load([
            'rateCard.entries',
            'rateCard.tiers',
            'activeMembers', // Scope: active members only
            'activeAddons.addon.rates',
            'plan'
        ]);

        $rateCard = $application->rateCard;
        if (!$rateCard) {
            return ['success' => false, 'message' => 'No rate card assigned to application'];
        }

        // 1. Calculate Base Premium (Members)
        $basePremium = 0;
        $loadingAmount = 0;
        $memberBreakdown = [];

        // Determine if we use Tiered (Family) pricing or Per-Member pricing
        // Note: Logic here assumes Per-Member accumulation. 
        // If your RateCard is strictly Tiered (flat family fee), logic needs to check RateCard::is_tiered
        
        foreach ($application->activeMembers as $member) {
            $memberResult = $this->calculateApplicationMemberPremium($member, $rateCard);
            
            if ($memberResult['success']) {
                $basePremium += $memberResult['base_premium'];
                $loadingAmount += $memberResult['loading_amount'];
                
                $memberBreakdown[] = [
                    'member_id' => $member->id,
                    'name' => $member->full_name,
                    'type' => $member->member_type,
                    'age' => $member->age_at_inception ?? $member->age,
                    'base' => $memberResult['base_premium'],
                    'loading' => $memberResult['loading_amount'],
                    'total' => $memberResult['total_premium'],
                ];
            }
        }

        // 2. Calculate Addons
        $addonPremium = 0;
        $addonBreakdown = [];

        foreach ($application->activeAddons as $appAddon) {
            $addon = $appAddon->addon;
            if (!$addon) continue;

            $addonResult = $this->calculateAddonPremium(
                $addon,
                $application->plan_id,
                $basePremium,
                $application->activeMembers->count()
            );

            if ($addonResult['success']) {
                $premium = $addonResult['premium'];
                $addonPremium += $premium;

                // Update the pivot record
                $appAddon->premium = $premium;
                $appAddon->save();

                $addonBreakdown[] = [
                    'addon_name' => $addon->name,
                    'premium' => $premium,
                ];
            }
        }

        // 3. Final Totals
        $discountAmount = (float) $application->discount_amount; // Calculated by DiscountService previously
        $totalPremium = $basePremium + $addonPremium + $loadingAmount - $discountAmount;
        
        // Tax
        $taxRate = config('medical.tax_rate', 0.0); // e.g., 0.16 for VAT
        $taxAmount = round($totalPremium * $taxRate, 2);
        $grossPremium = $totalPremium + $taxAmount;

        // 4. Save to DB
        $application->update([
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'loading_amount' => round($loadingAmount, 2),
            'total_premium' => round($totalPremium, 2),
            'tax_amount' => $taxAmount,
            'gross_premium' => round($grossPremium, 2),
        ]);

        return [
            'success' => true,
            'gross_premium' => $grossPremium,
            'currency' => $application->currency,
            'annual_amount' => $this->annualize($grossPremium, $application->billing_frequency),
            'breakdown' => [
                'members' => $memberBreakdown,
                'addons' => $addonBreakdown
            ]
        ];
    }

    /**
     * Calculate and update premium for an active Policy.
     */
    public function calculatePolicyPremium(Policy $policy): array
    {
        $policy->load([
            'rateCard.entries',
            'rateCard.tiers',
            'members', // Note: Policy usually calculates for ALL active members
            'policyAddons.addon',
            'plan'
        ]);

        $rateCard = $policy->rateCard;
        if (!$rateCard) {
            return ['success' => false, 'message' => 'No rate card assigned'];
        }

        // 1. Calculate Members
        $basePremium = 0;
        $loadingAmount = 0;
        $activeMemberCount = 0;

        foreach ($policy->members as $member) {
            // Skip terminated members for premium calculation if you only want active billing
            if ($member->status === \Modules\Medical\Constants\MedicalConstants::MEMBER_STATUS_TERMINATED) {
                continue;
            }

            $activeMemberCount++;
            
            // Calculate individual member premium
            $memberResult = $this->calculatePolicyMemberPremium($member, $policy);
            
            $basePremium += $memberResult;
            
            // Loadings are stored in the member_loadings table or summed on the member model
            // Assuming the Member model has a 'loading_amount' field updated by calculatePolicyMemberPremium
            $loadingAmount += $member->loading_amount;
        }

        // 2. Calculate Addons
        $addonPremium = 0;
        foreach ($policy->policyAddons as $policyAddon) {
            if (!$policyAddon->is_active) continue;

            $addon = $policyAddon->addon;
            // Calculate addon price (similar logic to applications)
            // Note: For policies, usually the price is fixed at inception/renewal, 
            // but we recalculate here to ensure consistency if members change.
            $res = $this->calculateAddonPremium(
                $addon, 
                $policy->plan_id, 
                $basePremium, 
                $activeMemberCount
            );
            
            if ($res['success']) {
                $policyAddon->premium = $res['premium'];
                $policyAddon->save();
                $addonPremium += $res['premium'];
            }
        }

        // 3. Totals
        $discountAmount = (float) $policy->discount_amount;
        $totalPremium = $basePremium + $addonPremium + $loadingAmount - $discountAmount;
        
        $taxRate = config('medical.tax_rate', 0.0);
        $taxAmount = round($totalPremium * $taxRate, 2);
        $grossPremium = $totalPremium + $taxAmount;

        // 4. Update Policy
        $policy->update([
            'member_count' => $activeMemberCount,
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'loading_amount' => round($loadingAmount, 2),
            'total_premium' => round($totalPremium, 2),
            'tax_amount' => $taxAmount,
            'gross_premium' => round($grossPremium, 2),
        ]);

        return [
            'success' => true,
            'gross_premium' => $grossPremium
        ];
    }

    /**
     * Calculate premium for a single Policy Member.
     */
    public function calculatePolicyMemberPremium(Member $member, ?Policy $policy = null): float
    {
        $policy = $policy ?? $member->policy;
        $rateCard = $policy->rateCard;

        if (!$rateCard) return 0.0;

        // 1. Base Rate
        // Use age_at_inception if set (standard insurance practice), otherwise current age
        $age = $member->age_at_inception ?? $member->age;
        
        $entry = $this->findRateEntry($rateCard, $age, $member->member_type, $member->gender);

        $base = 0.0;
        if ($entry) {
            $base = (float) $entry->base_premium;
        }

        // 2. Loadings
        // In Policy context, loadings are usually materialized in `med_member_loadings` table
        // We sum active loadings attached to this member
        $loadingAmount = $member->activeLoadings()
            ->sum('loading_amount');

        // 3. Update Member Record
        $total = $base + $loadingAmount;
        
        $member->update([
            'base_premium' => $base,
            'loading_amount' => $loadingAmount,
            'total_premium' => $total
        ]);

        return $base;
    }

    /**
     * Calculate single member premium for Application (DB Model).
     */
    public function calculateApplicationMemberPremium(ApplicationMember $member, RateCard $rateCard): array
    {
        $age = $member->age_at_inception ?? $member->age;
        
        // Lookup Rate
        $entry = $this->findRateEntry($rateCard, $age, $member->member_type, $member->gender);

        if (!$entry) {
            return ['success' => false, 'message' => "No rate found for {$member->member_type} (Age: $age)"];
        }

        $basePremium = (float) $entry->base_premium; // Assuming 'base_premium' column in rate_card_entries table

        // Apply Loadings (Underwriting)
        $loadingAmount = $this->calculateMemberLoadingAmount($member, $basePremium);
        $total = $basePremium + $loadingAmount;

        // Save to DB
        $member->update([
            'base_premium' => $basePremium,
            'loading_amount' => $loadingAmount,
            'total_premium' => $total
        ]);

        return [
            'success' => true,
            'base_premium' => $basePremium,
            'loading_amount' => $loadingAmount,
            'total_premium' => $total
        ];
    }

    // =========================================================================
    // 2. STANDALONE QUOTE (PUBLIC CALCULATOR)
    // =========================================================================

    /**
     * Generate a quote from raw data (No DB persistence).
     * Used by RateCardController::calculate
     */
    public function calculateQuote(RateCard $rateCard, array $membersData, array $addonIds = []): array
    {
        $rateCard->load(['entries', 'tiers']);
        
        $basePremium = 0;
        $memberBreakdown = [];
        $memberCount = count($membersData);

        // 1. Calculate Member Base
        foreach ($membersData as $index => $data) {
            $age = $data['age'];
            $type = $data['member_type'];
            $gender = $data['gender'] ?? null;

            $entry = $this->findRateEntry($rateCard, $age, $type, $gender);

            if ($entry) {
                $amount = (float) $entry->base_premium;
                $basePremium += $amount;
                
                $memberBreakdown[] = [
                    'index' => $index,
                    'type' => $type,
                    'age' => $age,
                    'amount' => $amount
                ];
            }
        }

        // 2. Handle Tiers (If Rate Card is Tiered)
        // If tiered, we might override the per-member sum with a flat family fee
        if ($rateCard->is_tiered) {
            $tier = $this->findApplicableTier($rateCard, $memberCount);
            if ($tier) {
                // Logic: Either override base, or multiply. 
                // Assuming 'tier_premium' is a flat fee for the group based on your models
                if ($tier->tier_premium > 0) {
                    $basePremium = $tier->tier_premium;
                    // Check for extra members logic if defined
                    if ($tier->max_members && $memberCount > $tier->max_members && $tier->extra_member_premium > 0) {
                        $extras = $memberCount - $tier->max_members;
                        $basePremium += ($extras * $tier->extra_member_premium);
                    }
                }
            }
        }

        // 3. Calculate Addons
        $addonPremium = 0;
        $addonBreakdown = [];

        if (!empty($addonIds)) {
            $addons = Addon::whereIn('id', $addonIds)->get();
            foreach ($addons as $addon) {
                $res = $this->calculateAddonPremium($addon, $rateCard->plan_id, $basePremium, $memberCount);
                if ($res['success']) {
                    $addonPremium += $res['premium'];
                    $addonBreakdown[] = [
                        'name' => $addon->name,
                        'amount' => $res['premium']
                    ];
                }
            }
        }

        $total = $basePremium + $addonPremium;

        return [
            'success' => true,
            'currency' => $rateCard->currency,
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'total_premium' => round($total, 2),
            'breakdown' => [
                'members' => $memberBreakdown,
                'addons' => $addonBreakdown
            ]
        ];
    }

    // =========================================================================
    // 3. SHARED CALCULATION LOGIC
    // =========================================================================

    /**
     * Calculate specific addon price.
     */
    public function calculateAddonPremium(Addon $addon, string $planId, float $basePremium, int $memberCount): array
    {
        // 1. Check if included in plan
        $planAddon = PlanAddon::where('plan_id', $planId)
            ->where('addon_id', $addon->id)
            ->first();

        if ($planAddon && $planAddon->is_included) {
            return ['success' => true, 'premium' => 0, 'is_included' => true];
        }

        // 2. Find active rate
        $rate = AddonRate::where('addon_id', $addon->id)
            ->where('plan_id', $planId)
            ->active()
            ->effective()
            ->orderByDesc('effective_from')
            ->first();

        if (!$rate) {
            // Fallback to global rate if exists? 
            // For now, fail safely.
            return ['success' => false, 'message' => 'No rate found'];
        }

        $amount = 0;
        switch ($rate->pricing_type) {
            case 'fixed':
                $amount = $rate->amount;
                break;
            case 'per_member':
                $amount = $rate->amount * $memberCount;
                break;
            case 'percentage':
                $amount = $basePremium * ($rate->percentage / 100);
                break;
        }

        return ['success' => true, 'premium' => round($amount, 2), 'is_included' => false];
    }

    /**
     * Calculate Loadings from JSON data on Member model.
     */
    protected function calculateMemberLoadingAmount(ApplicationMember|Member $member, float $basePremium): float
    {
        // 'applied_loadings' should be cast to array in the model
        $loadings = $member->applied_loadings ?? [];
        if (empty($loadings)) return 0;

        $total = 0;
        foreach ($loadings as $loading) {
            $val = (float)($loading['value'] ?? 0);
            $type = $loading['type'] ?? 'fixed';

            if ($type === 'percentage') {
                $total += $basePremium * ($val / 100);
            } else {
                $total += $val;
            }
        }
        return round($total, 2);
    }

    // =========================================================================
    // 4. HELPERS
    // =========================================================================

    /**
     * Find specific rate card entry.
     */
    protected function findRateEntry(RateCard $rateCard, int $age, string $type, ?string $gender): ?object
    {
        // Prioritize exact match (Member Type + Gender + Age)
        return $rateCard->entries->first(function ($e) use ($age, $type, $gender, $rateCard) {
            // Check Age
            // Use 'min_age' and 'max_age' as per standard RateCardEntry model
            $ageMatch = $age >= $e->min_age && $age <= $e->max_age;
            
            // Check Type (if entry defines it, otherwise generic)
            // If rate card doesn't distinguish types, ignore this check
            $typeMatch = empty($e->member_type) || $e->member_type === $type;

            // Check Gender
            $genderMatch = $rateCard->is_unisex || empty($e->gender) || $e->gender === $gender;

            return $ageMatch && $typeMatch && $genderMatch;
        });
    }

    protected function findApplicableTier(RateCard $rateCard, int $count): ?object
    {
        return $rateCard->tiers->first(function ($t) use ($count) {
            return $count >= $t->min_members && 
                   ($t->max_members === null || $count <= $t->max_members);
        });
    }

    public function annualize(float $amount, string $frequency): float
    {
        return match($frequency) {
            MedicalConstants::BILLING_MONTHLY => $amount * 12,
            MedicalConstants::BILLING_QUARTERLY => $amount * 4,
            MedicalConstants::BILLING_SEMI_ANNUAL => $amount * 2,
            default => $amount,
        };
    }

    /**
     * Convert an annual amount to a specific billing period amount.
     * e.g. 1200 Annual -> 100 Monthly
     * * @param float $annualAmount The total annual premium
     * @param string $frequency The billing frequency (monthly, quarterly, etc)
     */
    public function periodize(float $annualAmount, string $frequency): float
    {
        // Prevent division by zero if amount is 0
        if ($annualAmount <= 0) {
            return 0.0;
        }

        return match($frequency) {
            MedicalConstants::BILLING_MONTHLY => round($annualAmount / 12, 2),
            MedicalConstants::BILLING_QUARTERLY => round($annualAmount / 4, 2),
            MedicalConstants::BILLING_SEMI_ANNUAL => round($annualAmount / 2, 2),
            MedicalConstants::BILLING_ANNUAL => round($annualAmount, 2),
            // Default to monthly if frequency is unknown or invalid
            default => round($annualAmount / 12, 2),
        };
    }
}