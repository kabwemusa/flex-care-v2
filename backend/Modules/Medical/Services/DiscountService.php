<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\DiscountRule;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Models\Plan;
use Modules\Medical\Constants\MedicalConstants;

class DiscountService
{
    /**
     * Get applicable automatic discounts for a plan.
     */
    public function getApplicableDiscounts(Plan $plan, array $context = []): array
    {
        $discounts = DiscountRule::active()
            ->effective()
            ->discounts()
            ->automatic()
            ->where(function ($q) use ($plan) {
                $q->whereNull('scheme_id')->orWhere('scheme_id', $plan->scheme_id);
            })
            ->where(function ($q) use ($plan) {
                $q->whereNull('plan_id')->orWhere('plan_id', $plan->id);
            })
            ->orderByDesc('priority')
            ->get()
            ->filter(fn($rule) => $rule->matchesTriggerRules($context));

        return $discounts->values()->all();
    }

    /**
     * Calculate discounts on a premium.
     */
    public function calculateDiscounts(float $premium, array $discountRules): array
    {
        $applied = [];
        $totalDiscount = 0;
        $runningPremium = $premium;

        foreach ($discountRules as $rule) {
            if (!$rule instanceof DiscountRule) {
                $rule = DiscountRule::find($rule);
            }

            if (!$rule || !$rule->canBeUsed()) {
                continue;
            }

            // Check stacking
            if (!$rule->can_stack && !empty($applied)) {
                continue;
            }

            $discountAmount = $rule->calculateAdjustment($runningPremium);

            $runningPremium -= $discountAmount;
            $totalDiscount += $discountAmount;

            $applied[] = [
                'rule_id' => $rule->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'value' => $rule->value,
                'value_type' => $rule->value_type,
                'amount' => $discountAmount,
            ];
        }

        return [
            'original_premium' => $premium,
            'discounts' => $applied,
            'total_discount' => round($totalDiscount, 2),
            'final_premium' => round(max(0, $runningPremium), 2),
        ];
    }

    /**
     * Validate a promo code.
     */
    public function validatePromoCode(
        string $code,
        ?string $schemeId = null,
        ?string $planId = null
    ): array {
        $promo = PromoCode::where('code', strtoupper($code))->first();

        if (!$promo) {
            return ['valid' => false, 'message' => 'Promo code not found'];
        }

        $errors = $promo->validate($schemeId, $planId);

        if (!empty($errors)) {
            return ['valid' => false, 'message' => implode(' ', $errors)];
        }

        return [
            'valid' => true,
            'promo_code' => $promo,
            'discount_rule' => $promo->discountRule,
        ];
    }

    /**
     * Apply promo code to a premium.
     */
    public function applyPromoCode(string $code, float $premium, ?string $planId = null): array
    {
        $validation = $this->validatePromoCode($code, null, $planId);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        $promo = $validation['promo_code'];
        $rule = $validation['discount_rule'];

        $discountAmount = $rule->calculateAdjustment($premium);
        $finalPremium = max(0, $premium - $discountAmount);

        // Increment usage
        $promo->incrementUsage();

        return [
            'success' => true,
            'original_premium' => $premium,
            'discount_amount' => round($discountAmount, 2),
            'final_premium' => round($finalPremium, 2),
            'promo_code' => $code,
            'discount_name' => $rule->name,
        ];
    }
}