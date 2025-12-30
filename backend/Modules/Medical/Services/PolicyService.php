<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Constants\MedicalConstants;

class PolicyService
{
    public function __construct(
        protected PremiumCalculator $premiumCalculator
    ) {}

    /**
     * Renew a policy.
     */
    public function renewPolicy(Policy $oldPolicy, array $overrides = []): Policy
    {
        return DB::transaction(function () use ($oldPolicy, $overrides) {
            // Calculate new dates
            $newInceptionDate = $oldPolicy->expiry_date->addDay();
            $termMonths = $overrides['policy_term_months'] ?? $oldPolicy->policy_term_months;
            $newExpiryDate = $newInceptionDate->copy()->addMonths($termMonths)->subDay();

            // Create new policy
            $newPolicy = $oldPolicy->replicate([
                'id', 
                'policy_number', 
                'created_at', 
                'updated_at',
                'cancelled_at',
                'cancellation_reason',
                'cancellation_notes',
                'cancelled_by',
                'renewed_to_policy_id',
            ]);

            $newPolicy->fill([
                'inception_date' => $newInceptionDate,
                'expiry_date' => $newExpiryDate,
                'renewal_date' => $newExpiryDate,
                'status' => MedicalConstants::POLICY_STATUS_DRAFT,
                'underwriting_status' => MedicalConstants::UW_STATUS_PENDING,
                'underwriting_notes' => null,
                'underwritten_by' => null,
                'underwritten_at' => null,
                'previous_policy_id' => $oldPolicy->id,
                'renewal_count' => $oldPolicy->renewal_count + 1,
                'promo_code_id' => null,
                'applied_discounts' => null,
                'applied_loadings' => null,
            ]);

            // Apply overrides
            if (!empty($overrides['plan_id'])) {
                $newPolicy->plan_id = $overrides['plan_id'];
            }
            if (!empty($overrides['rate_card_id'])) {
                $newPolicy->rate_card_id = $overrides['rate_card_id'];
            }
            if (!empty($overrides['billing_frequency'])) {
                $newPolicy->billing_frequency = $overrides['billing_frequency'];
            }

            $newPolicy->save();

            // Copy policy addons
            foreach ($oldPolicy->policyAddons as $addon) {
                $newPolicy->policyAddons()->create([
                    'addon_id' => $addon->addon_id,
                    'addon_rate_id' => $addon->addon_rate_id,
                    'premium' => $addon->premium,
                    'is_active' => $addon->is_active,
                ]);
            }

            // Copy members (active ones only)
            foreach ($oldPolicy->members()->active()->get() as $member) {
                $newMember = $member->replicate([
                    'id',
                    'member_number',
                    'created_at',
                    'updated_at',
                    'terminated_at',
                    'termination_reason',
                    'termination_notes',
                ]);

                $newMember->policy_id = $newPolicy->id;
                $newMember->cover_start_date = $newInceptionDate;
                $newMember->cover_end_date = $newExpiryDate;
                $newMember->waiting_period_end_date = null; // No waiting period on renewal
                $newMember->status = MedicalConstants::MEMBER_STATUS_PENDING;
                $newMember->card_status = MedicalConstants::CARD_STATUS_PENDING;
                $newMember->card_number = null;
                $newMember->card_issued_date = null;
                $newMember->card_expiry_date = null;
                
                $newMember->save();

                // Copy active loadings
                foreach ($member->activeLoadings as $loading) {
                    if ($loading->is_permanent || ($loading->end_date && $loading->end_date > $newInceptionDate)) {
                        $newLoading = $loading->replicate(['id', 'created_at', 'updated_at']);
                        $newLoading->member_id = $newMember->id;
                        $newLoading->save();
                    }
                }

                // Copy active exclusions
                foreach ($member->activeExclusions as $exclusion) {
                    if ($exclusion->is_permanent || ($exclusion->end_date && $exclusion->end_date > $newInceptionDate)) {
                        $newExclusion = $exclusion->replicate(['id', 'created_at', 'updated_at']);
                        $newExclusion->member_id = $newMember->id;
                        $newExclusion->save();
                    }
                }
            }

            // Update old policy
            $oldPolicy->status = MedicalConstants::POLICY_STATUS_RENEWED;
            $oldPolicy->renewed_to_policy_id = $newPolicy->id;
            $oldPolicy->save();

            // Update member counts and calculate premium
            $newPolicy->updateMemberCounts();
            $this->premiumCalculator->calculate($newPolicy);

            return $newPolicy->fresh();
        });
    }

    /**
     * Apply promo code to policy.
     */
    public function applyPromoCode(Policy $policy, string $code): bool
    {
        $promoCode = PromoCode::byCode($code)
            ->usable()
            ->first();

        if (!$promoCode) {
            throw new \Exception('Invalid or expired promo code');
        }

        // Validate eligibility
        if (!$promoCode->isEligibleForScheme($policy->scheme_id)) {
            throw new \Exception('Promo code not valid for this scheme');
        }

        if (!$promoCode->isEligibleForPlan($policy->plan_id)) {
            throw new \Exception('Promo code not valid for this plan');
        }

        // Apply
        $policy->promo_code_id = $promoCode->id;
        
        // Get discount rule and calculate discount
        $discountRule = $promoCode->discountRule;
        if ($discountRule) {
            $discountAmount = $discountRule->calculateAdjustment($policy->base_premium);
            $policy->discount_amount = ($policy->discount_amount ?? 0) + $discountAmount;
            
            // Track applied discounts
            $appliedDiscounts = $policy->applied_discounts ?? [];
            $appliedDiscounts[] = [
                'type' => 'promo_code',
                'code' => $code,
                'discount_rule_id' => $discountRule->id,
                'amount' => $discountAmount,
                'applied_at' => now()->toISOString(),
            ];
            $policy->applied_discounts = $appliedDiscounts;
        }

        $policy->save();

        // Increment usage
        $promoCode->incrementUsage();

        return true;
    }

    /**
     * Generate policy certificate.
     */
    public function generateCertificate(Policy $policy): string
    {
        // This would integrate with a PDF generation service
        // For now, return a placeholder
        return "certificates/policy_{$policy->policy_number}.pdf";
    }

    /**
     * Generate member schedule.
     */
    public function generateMemberSchedule(Policy $policy): string
    {
        // This would integrate with a PDF generation service
        return "schedules/schedule_{$policy->policy_number}.pdf";
    }

    /**
     * Check if policy can be activated.
     */
    public function canActivate(Policy $policy): array
    {
        $issues = [];

        if ($policy->underwriting_status !== MedicalConstants::UW_STATUS_APPROVED) {
            $issues[] = 'Policy must be underwriting approved';
        }

        if ($policy->member_count === 0) {
            $issues[] = 'Policy must have at least one member';
        }

        if ($policy->is_corporate && !$policy->group_id) {
            $issues[] = 'Corporate policy must be linked to a group';
        }

        if (!$policy->is_corporate && !$policy->principal_member_id) {
            $issues[] = 'Individual/Family policy must have a principal member';
        }

        return [
            'can_activate' => empty($issues),
            'issues' => $issues,
        ];
    }
}