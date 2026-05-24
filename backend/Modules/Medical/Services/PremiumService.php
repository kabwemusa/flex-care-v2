<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Models\Addon;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\RateCard;

class PremiumService
{
    /** Cache TTL in seconds — rate cards and plan addons change infrequently. */
    private const RATE_CARD_TTL = 3600;   // 1 hour
    private const PLAN_ADDON_TTL = 3600;  // 1 hour
    private const LOADING_RULE_TTL = 900; // 15 minutes


    // =========================================================================
    // 1. APPLICATION (SAVED DATA) CALCULATION
    // =========================================================================

    /**
     * Calculate and update premium for a saved Application.
     */
    public function calculateApplicationPremium(Application $application): array
    {
        $application->load([
            'rateCard.entries',
            'rateCard.tiers',
            'activeMembers',
            'activeAddons.addon',
            'plan'
        ]);

        $rateCard = $application->rateCard;
        if (!$rateCard) {
            return ['success' => false, 'message' => 'No rate card assigned to application'];
        }

        // 1. Calculate Base Premium branching on effective premium_basis
        $basePremium = 0;
        $loadingAmount = 0;
        $memberBreakdown = [];
        $memberCount = $application->activeMembers->count();
        $effectiveBasis = $this->resolveEffectivePremiumBasis($rateCard);

        switch ($effectiveBasis) {
            case MedicalConstants::PREMIUM_BASIS_TIERED:
                $tieredResult = $this->calculateTieredPremium($rateCard, $memberCount);
                $basePremium = $tieredResult['base_premium'];

                // Distribute base evenly for loading calculation context, then sum loadings
                $perMemberBase = $memberCount > 0 ? round($basePremium / $memberCount, 2) : 0;
                foreach ($application->activeMembers as $member) {
                    $memberLoading = $this->calculateMemberLoadingAmount($member, $perMemberBase);
                    $loadingAmount += $memberLoading;

                    $member->forceFill([
                        'base_premium' => $perMemberBase,
                        'loading_amount' => $memberLoading,
                        'total_premium' => $perMemberBase + $memberLoading,
                    ])->saveQuietly();

                    $memberBreakdown[] = [
                        'member_id' => $member->id,
                        'name' => $member->full_name,
                        'type' => $member->member_type,
                        'age' => $member->age_at_inception ?? $member->age,
                        'base' => $perMemberBase,
                        'loading' => $memberLoading,
                        'total' => $perMemberBase + $memberLoading,
                    ];
                }
                break;

            case MedicalConstants::PREMIUM_BASIS_PER_FAMILY:
                $familyResult = $this->calculatePerFamilyPremium($rateCard);
                $basePremium = $familyResult['base_premium'];

                // Sum member loadings (use full family premium as loading context)
                foreach ($application->activeMembers as $member) {
                    $memberLoading = $this->calculateMemberLoadingAmount($member, $basePremium);
                    $loadingAmount += $memberLoading;

                    $member->forceFill([
                        'loading_amount' => $memberLoading,
                        'total_premium' => $memberLoading,
                    ])->saveQuietly();

                    if ($memberLoading > 0) {
                        $memberBreakdown[] = [
                            'member_id' => $member->id,
                            'name' => $member->full_name,
                            'type' => $member->member_type,
                            'age' => $member->age_at_inception ?? $member->age,
                            'base' => 0,
                            'loading' => $memberLoading,
                            'total' => $memberLoading,
                        ];
                    }
                }
                break;

            default: // per_member
                foreach ($application->activeMembers as $member) {
                    $memberResult = $this->calculateApplicationMemberPremium($member, $rateCard, true);

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
                break;
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
                $memberCount
            );

            if ($addonResult['success']) {
                $premium = $addonResult['premium'];
                $addonPremium += $premium;

                $appAddon->premium = $premium;
                $appAddon->saveQuietly();

                $addonBreakdown[] = [
                    'addon_name' => $addon->name,
                    'premium' => $premium,
                ];
            }
        }

        // 3. Final Totals
        $discountAmount = (float) $application->discount_amount;
        $totalPremium = $basePremium + $addonPremium + $loadingAmount - $discountAmount;

        $taxRate = config('medical.tax_rate', 0.05);
        $taxAmount = round($totalPremium * $taxRate, 2);
        $grossPremium = $totalPremium + $taxAmount;

        // 4. Save to DB (use direct update to avoid re-triggering hooks)
        $application->forceFill([
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'loading_amount' => round($loadingAmount, 2),
            'total_premium' => round($totalPremium, 2),
            'tax_amount' => $taxAmount,
            'gross_premium' => round($grossPremium, 2),
        ])->saveQuietly();

        // Update member counts without re-triggering premium hooks
        $application->forceFill([
            'member_count' => $memberCount,
            'principal_count' => $application->activeMembers->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL)->count(),
            'dependent_count' => $application->activeMembers->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL)->count(),
        ])->saveQuietly();

        return [
            'success' => true,
            'gross_premium' => $grossPremium,
            'currency' => $application->currency,
            'annual_amount' => $this->annualize($grossPremium, $application->billing_frequency),
            'premium_basis' => $effectiveBasis,
            'breakdown' => [
                'members' => $memberBreakdown,
                'addons' => $addonBreakdown,
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
            'members',
            'policyAddons.addon',
            'plan'
        ]);

        $rateCard = $policy->rateCard;
        if (!$rateCard) {
            return ['success' => false, 'message' => 'No rate card assigned'];
        }

        // 1. Calculate Members based on premium_basis
        $basePremium = 0;
        $loadingAmount = 0;
        $activeMemberCount = 0;

        // Count active members first (needed for tiered)
        foreach ($policy->members as $member) {
            if ($member->status !== MedicalConstants::MEMBER_STATUS_TERMINATED) {
                $activeMemberCount++;
            }
        }

        $effectiveBasis = $this->resolveEffectivePremiumBasis($rateCard);

        switch ($effectiveBasis) {
            case MedicalConstants::PREMIUM_BASIS_TIERED:
                // Tiered: flat family fee, skip per-member calculation
                $tieredResult = $this->calculateTieredPremium($rateCard, $activeMemberCount);
                $basePremium = $tieredResult['base_premium'];
                break;

            case MedicalConstants::PREMIUM_BASIS_PER_FAMILY:
                // Per-family: single flat rate
                $familyResult = $this->calculatePerFamilyPremium($rateCard);
                $basePremium = $familyResult['base_premium'];
                break;

            default: // per_member
                // Per-member: calculate each active member individually
                foreach ($policy->members as $member) {
                    if ($member->status === MedicalConstants::MEMBER_STATUS_TERMINATED) {
                        continue;
                    }

                    $memberResult = $this->calculatePolicyMemberPremium($member, $policy);
                    $basePremium += $memberResult;
                    $loadingAmount += $member->loading_amount;
                }
                break;
        }

        // 2. Calculate Addons
        $addonPremium = 0;
        foreach ($policy->policyAddons as $policyAddon) {
            if (!$policyAddon->is_active) continue;

            $addon = $policyAddon->addon;
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

        $taxRate = config('medical.tax_rate', 0.05);
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
     * Applies member_type_factors from the rate card.
     */
    public function calculatePolicyMemberPremium(Member $member, ?Policy $policy = null): float
    {
        $policy = $policy ?? $member->policy;
        $rateCard = $policy->rateCard;

        if (!$rateCard) return 0.0;

        $age = $member->age_at_inception ?? $member->age;

        $entry = $this->findRateEntry($rateCard, $age, $member->member_type, $member->gender);

        $base = 0.0;
        if ($entry) {
            $rawBase = (float) $entry->base_premium;
            $factor = $rateCard->getMemberTypeFactor($member->member_type);
            $base = round($rawBase * $factor, 2);
        }

        // Loadings from med_member_loadings table
        $loadingAmount = $member->activeLoadings()
            ->sum('loading_amount');

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
     * Applies member_type_factors from the rate card.
     *
     * @param bool $quiet  When true, uses saveQuietly() to prevent cascading hook recalculations
     *                     (used during batch calculation in calculateApplicationPremium).
     */
    public function calculateApplicationMemberPremium(ApplicationMember $member, RateCard $rateCard, bool $quiet = false): array
    {
        $age = $member->age_at_inception ?? $member->age;

        $entry = $this->findRateEntry($rateCard, $age, $member->member_type, $member->gender);

        if (!$entry) {
            // Tiered/per_family rate cards don't require per-member entries
            if ($rateCard->premium_basis !== MedicalConstants::PREMIUM_BASIS_PER_MEMBER) {
                $basePremium = 0.0;
            } else {
                return ['success' => false, 'message' => "No rate found for {$member->member_type} (Age: $age)"];
            }
        } else {
            $rawPremium = (float) $entry->base_premium;
            $factor = $rateCard->getMemberTypeFactor($member->member_type);
            $basePremium = round($rawPremium * $factor, 2);
        }

        // Apply Loadings (Underwriting)
        $loadingAmount = $this->calculateMemberLoadingAmount($member, $basePremium);
        $total = $basePremium + $loadingAmount;

        $member->forceFill([
            'base_premium' => $basePremium,
            'loading_amount' => $loadingAmount,
            'total_premium' => $total,
        ]);

        $quiet ? $member->saveQuietly() : $member->save();

        return [
            'success' => true,
            'base_premium' => $basePremium,
            'loading_amount' => $loadingAmount,
            'total_premium' => $total,
        ];
    }

    // =========================================================================
    // 2. STANDALONE QUOTE (PUBLIC CALCULATOR)
    // =========================================================================

    /**
     * Generate a quote from raw data (No DB persistence).
     * Used by PublicQuoteController and RateCardController::calculate.
     *
     * @param RateCard $rateCard   The rate card to price against
     * @param array    $membersData Array of member data (age, member_type, gender)
     * @param array    $addonIds   User-selected addon IDs
     * @param string|null $planId  Plan ID for mandatory addon injection and addon pricing
     */
    public function calculateQuote(RateCard $rateCard, array $membersData, array $addonIds = [], ?string $planId = null): array
    {
        // Use cached rate card so repeated public quote requests don't hit the DB.
        $rateCard = $this->getCachedRateCard($rateCard->id);

        $memberCount = count($membersData);
        $effectivePlanId = $planId ?? $rateCard->plan_id;

        // 1. Calculate Base Premium using effective premium_basis strategy
        $effectiveBasis = $this->resolveEffectivePremiumBasis($rateCard);
        $baseResult = match ($effectiveBasis) {
            MedicalConstants::PREMIUM_BASIS_TIERED => $this->calculateTieredPremium($rateCard, $memberCount),
            MedicalConstants::PREMIUM_BASIS_PER_FAMILY => $this->calculatePerFamilyPremium($rateCard),
            default => $this->calculatePerMemberPremium($rateCard, $membersData),
        };

        $basePremium = $baseResult['base_premium'];
        $memberBreakdown = $baseResult['member_breakdown'];

        // 2. Auto-inject mandatory addons for the plan
        if ($effectivePlanId) {
            $mandatoryAddonIds = PlanAddon::where('plan_id', $effectivePlanId)
                ->where('is_active', true)
                ->mandatory()
                ->pluck('addon_id')
                ->toArray();

            $addonIds = array_values(array_unique(array_merge($addonIds, $mandatoryAddonIds)));
        }

        // 3. Calculate Addons
        $addonPremium = 0;
        $addonBreakdown = [];

        if (!empty($addonIds)) {
            $addons = Addon::whereIn('id', $addonIds)->get();
            foreach ($addons as $addon) {
                $res = $this->calculateAddonPremium($addon, $effectivePlanId, $basePremium, $memberCount);
                if ($res['success']) {
                    $addonPremium += $res['premium'];
                    $addonBreakdown[] = [
                        'name' => $addon->name,
                        'amount' => $res['premium'],
                        'is_included' => $res['is_included'] ?? false,
                    ];
                }
            }
        }

        $total = $basePremium + $addonPremium;

        return [
            'success' => true,
            'currency' => $rateCard->currency,
            'premium_basis' => $effectiveBasis,
            'base_premium' => round($basePremium, 2),
            'addon_premium' => round($addonPremium, 2),
            'total_premium' => round($total, 2),
            'breakdown' => [
                'members' => $memberBreakdown,
                'addons' => $addonBreakdown,
            ],
        ];
    }

    // =========================================================================
    // 3. PREMIUM BASIS STRATEGIES
    // =========================================================================

    /**
     * Per-member age-banded calculation with member_type_factors applied.
     * Each member is rated individually based on their age band.
     */
    protected function calculatePerMemberPremium(RateCard $rateCard, array $membersData): array
    {
        $basePremium = 0;
        $memberBreakdown = [];

        foreach ($membersData as $index => $data) {
            $age = $data['age'];
            $type = $data['member_type'];
            $gender = $data['gender'] ?? null;

            $entry = $this->findRateEntry($rateCard, $age, $type, $gender);

            if ($entry) {
                $rawPremium = (float) $entry->base_premium;
                $factor = $rateCard->getMemberTypeFactor($type);
                $amount = round($rawPremium * $factor, 2);
            } else {
                $amount = 0;
            }

            $basePremium += $amount;
            $memberBreakdown[] = [
                'index' => $index,
                'type' => $type,
                'age' => $age,
                'amount' => $amount,
            ];
        }

        return [
            'base_premium' => $basePremium,
            'member_breakdown' => $memberBreakdown,
        ];
    }

    /**
     * Tiered (family-size) flat premium.
     * No per-member age-band lookup; premium determined by member count.
     */
    protected function calculateTieredPremium(RateCard $rateCard, int $memberCount): array
    {
        $basePremium = 0;
        $tier = $this->findApplicableTier($rateCard, $memberCount);

        if ($tier && $tier->tier_premium > 0) {
            $basePremium = (float) $tier->tier_premium;
            if ($tier->max_members && $memberCount > $tier->max_members && $tier->extra_member_premium > 0) {
                $extras = $memberCount - $tier->max_members;
                $basePremium += ($extras * (float) $tier->extra_member_premium);
            }
        }

        return [
            'base_premium' => $basePremium,
            'member_breakdown' => [],
        ];
    }

    /**
     * Per-family flat premium.
     * Single rate regardless of member count or ages.
     * Uses the first rate card entry's base_premium as the flat family rate.
     */
    protected function calculatePerFamilyPremium(RateCard $rateCard): array
    {
        $entry = $rateCard->entries->first();
        $basePremium = $entry ? (float) $entry->base_premium : 0;

        return [
            'base_premium' => $basePremium,
            'member_breakdown' => [],
        ];
    }

    // =========================================================================
    // 4. SHARED CALCULATION LOGIC
    // =========================================================================

    /**
     * Calculate specific addon price using simplified addon pricing.
     */
    public function calculateAddonPremium(Addon $addon, string $planId, float $basePremium, int $memberCount): array
    {
        // 1. Check if included in plan (no additional charge) — cached to avoid a DB hit per addon.
        $planAddon = $this->getCachedPlanAddon($planId, $addon->id);

        if ($planAddon && $planAddon->is_included) {
            return ['success' => true, 'premium' => 0, 'is_included' => true];
        }

        // 2. Check if addon is active and effective
        if (!$addon->is_active || !$addon->is_effective) {
            return ['success' => false, 'message' => 'Addon is not active or effective'];
        }

        // 3. Calculate premium using addon's built-in method
        $amount = $addon->calculatePremium($memberCount, $basePremium);

        return ['success' => true, 'premium' => round($amount, 2), 'is_included' => false];
    }

    /**
     * Calculate Loadings from JSON data on Member model.
     */
    protected function calculateMemberLoadingAmount(ApplicationMember|Member $member, float $basePremium): float
    {
        $loadings = $member->applied_loadings ?? [];
        if (empty($loadings)) return 0;

        $total = 0;

        foreach ($loadings as $loading) {
            if (isset($loading['loading_amount'])) {
                $total += (float) $loading['loading_amount'];
                continue;
            }

            if (!empty($loading['loading_rule_id'])) {
                $rule = $this->getCachedLoadingRule($loading['loading_rule_id']);
                if ($rule) {
                    $total += $rule->calculateLoading($basePremium);
                    continue;
                }
            }

            $loadingType = $loading['loading_type'] ?? $loading['type'] ?? 'fixed';
            $value = (float) ($loading['value'] ?? $loading['loading_value'] ?? 0);

            if ($loadingType === 'percentage') {
                $total += round($basePremium * ($value / 100), 2);
            } else {
                $total += $value;
            }
        }

        return round($total, 2);
    }

    // =========================================================================
    // 5. HELPERS
    // =========================================================================

    /**
     * Resolve the effective premium basis for a rate card.
     *
     * If the configured premium_basis doesn't match what's actually available
     * (e.g. tiered but no tiers configured, or per_member but no entries),
     * fall back intelligently to avoid silent zero premiums.
     */
    protected function resolveEffectivePremiumBasis(RateCard $rateCard): string
    {
        $configured = $rateCard->premium_basis;
        $hasEntries = $rateCard->entries->isNotEmpty();
        $hasTiers   = $rateCard->tiers->isNotEmpty();

        // Configured strategy has data → use it as-is
        if ($configured === MedicalConstants::PREMIUM_BASIS_TIERED && $hasTiers) {
            return $configured;
        }
        if ($configured === MedicalConstants::PREMIUM_BASIS_PER_FAMILY && $hasEntries) {
            return $configured;
        }
        if ($configured === MedicalConstants::PREMIUM_BASIS_PER_MEMBER && $hasEntries) {
            return $configured;
        }

        // Configured strategy has NO data → fall back to what's available
        if ($hasTiers) {
            return MedicalConstants::PREMIUM_BASIS_TIERED;
        }
        if ($hasEntries) {
            return MedicalConstants::PREMIUM_BASIS_PER_MEMBER;
        }

        // Nothing configured at all — return the original (will produce zero)
        return $configured;
    }

    /**
     * Find specific rate card entry matching age band, member type, and gender.
     */
    protected function findRateEntry(RateCard $rateCard, int $age, string $type, ?string $gender): ?object
    {
        return $rateCard->entries->first(function ($e) use ($age, $type, $gender, $rateCard) {
            $ageMatch = $age >= $e->min_age && $age <= $e->max_age;
            $typeMatch = empty($e->member_type) || $e->member_type === $type;
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
     */
    public function periodize(float $annualAmount, string $frequency): float
    {
        if ($annualAmount <= 0) {
            return 0.0;
        }

        return match($frequency) {
            MedicalConstants::BILLING_MONTHLY => round($annualAmount / 12, 2),
            MedicalConstants::BILLING_QUARTERLY => round($annualAmount / 4, 2),
            MedicalConstants::BILLING_SEMI_ANNUAL => round($annualAmount / 2, 2),
            MedicalConstants::BILLING_ANNUAL => round($annualAmount, 2),
            default => round($annualAmount / 12, 2),
        };
    }

    /**
     * Convert premium from one frequency to another.
     */
    public function convertFrequency(float $premium, string $fromFrequency, string $toFrequency): float
    {
        if ($fromFrequency === $toFrequency) {
            return round($premium, 2);
        }

        $annualPremium = $this->annualize($premium, $fromFrequency);

        return $this->periodize($annualPremium, $toFrequency);
    }

    // =========================================================================
    // 6. CACHE HELPERS
    // =========================================================================

    /**
     * Return a RateCard with its entries and tiers, served from cache.
     *
     * Cache is tagged so that when a RateCard is updated the entire tag can
     * be flushed via PremiumService::bustRateCardCache($id).
     */
    protected function getCachedRateCard(string $rateCardId): RateCard
    {
        return $this->taggedRemember(
            ['rate-cards'],
            "rate_card:{$rateCardId}",
            self::RATE_CARD_TTL,
            fn () => RateCard::with(['entries', 'tiers'])->findOrFail($rateCardId)
        );
    }

    /**
     * Return a PlanAddon relationship row, served from cache.
     *
     * Null is stored when the relationship doesn't exist so the DB isn't
     * hit on every addon calculation for optional addons.
     */
    protected function getCachedPlanAddon(string $planId, string $addonId): ?PlanAddon
    {
        return $this->taggedRemember(
            ['plan-addons'],
            "plan_addon:{$planId}:{$addonId}",
            self::PLAN_ADDON_TTL,
            fn () => PlanAddon::where('plan_id', $planId)->where('addon_id', $addonId)->first()
        );
    }

    /**
     * Return a LoadingRule by ID, served from cache.
     */
    protected function getCachedLoadingRule(string $ruleId): ?\Modules\Medical\Models\LoadingRule
    {
        return $this->taggedRemember(
            ['loading-rules'],
            "loading_rule:{$ruleId}",
            self::LOADING_RULE_TTL,
            fn () => \Modules\Medical\Models\LoadingRule::find($ruleId)
        );
    }

    /**
     * Bust the cached rate card after an update.
     * Call this from RateCardObserver or RateCardController after save/delete.
     */
    public static function bustRateCardCache(string $rateCardId): void
    {
        try {
            Cache::tags(['rate-cards'])->forget("rate_card:{$rateCardId}");
        } catch (\BadMethodCallException) {
            Cache::forget("rate_card:{$rateCardId}");
        }
    }

    /**
     * Bust all cached plan-addon relationships.
     * Call this from PlanAddonObserver after save/delete.
     */
    public static function bustPlanAddonCache(): void
    {
        try {
            Cache::tags(['plan-addons'])->flush();
        } catch (\BadMethodCallException) {
            // Tag-based flush is not available on this cache driver.
            // Individual keys will expire naturally per PLAN_ADDON_TTL.
        }
    }

    /**
     * Cache::remember() with tag support.
     *
     * Falls back to untagged cache when the configured driver does not support
     * tagging (e.g. file, database, array). This keeps the service working in
     * any environment while Redis/Memcached get full tag-based invalidation.
     */
    private function taggedRemember(array $tags, string $key, int $ttl, \Closure $callback): mixed
    {
        try {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        } catch (\BadMethodCallException) {
            return Cache::remember($key, $ttl, $callback);
        }
    }
}
