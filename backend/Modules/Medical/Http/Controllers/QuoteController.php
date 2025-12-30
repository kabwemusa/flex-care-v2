<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Http\Requests\QuoteRequest;
use Modules\Medical\Services\PremiumCalculatorService;
use Modules\Medical\Services\DiscountService;
use Modules\Medical\Services\LoadingService;
use App\Traits\ApiResponse;

class QuoteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PremiumCalculatorService $premiumCalculator,
        protected DiscountService $discountService,
        protected LoadingService $loadingService
    ) {}

    /**
     * Generate a quote for a plan.
     * POST /v1/medical/quotes
     */
    public function generate(QuoteRequest $request): JsonResponse
    {
        $plan = Plan::with(['rateCards' => fn($q) => $q->active()->effective()])
            ->findOrFail($request->plan_id);

        $rateCard = $plan->active_rate_card;

        if (!$rateCard) {
            return $this->error('No active rate card for this plan', 422);
        }

        // Calculate base premium
        $premiumResult = $this->premiumCalculator->calculateTotalPremium(
            $rateCard,
            $request->members,
            $request->addon_ids ?? []
        );

        if (!$premiumResult['success']) {
            return $this->error($premiumResult['message'], 422);
        }

        $premium = $premiumResult['total_premium'];

        // Apply discounts if context provided
        $discountResult = null;
        if ($context = $request->discount_context) {
            $applicableDiscounts = $this->discountService->getApplicableDiscounts($plan, $context);
            if (!empty($applicableDiscounts)) {
                $discountResult = $this->discountService->calculateDiscounts($premium, $applicableDiscounts);
                $premium = $discountResult['final_premium'];
            }
        }

        // Apply promo code if provided
        $promoResult = null;
        if ($promoCode = $request->promo_code) {
            $promoResult = $this->discountService->applyPromoCode($promoCode, $premium, $plan->id);
            if ($promoResult['success']) {
                $premium = $promoResult['final_premium'];
            }
        }

        // Apply medical loadings if conditions provided
        $loadingResult = null;
        if ($conditions = $request->medical_conditions) {
            $loadingResult = $this->loadingService->calculateLoadings(
                $premium,
                $conditions,
                $request->cover_start_date ? \Carbon\Carbon::parse($request->cover_start_date) : null
            );
            $premium = $loadingResult['final_premium'];
        }

        return $this->success([
            'plan' => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
            ],
            'rate_card' => [
                'id' => $rateCard->id,
                'code' => $rateCard->code,
                'version' => $rateCard->version,
            ],
            'members' => $premiumResult['members'] ?? $request->members,
            'base_premium' => $premiumResult['base_premium'],
            'addon_premium' => $premiumResult['addon_premium'],
            'addons' => $premiumResult['addons'],
            'discounts' => $discountResult['discounts'] ?? [],
            'total_discount' => $discountResult['total_discount'] ?? 0,
            'promo_discount' => $promoResult['discount_amount'] ?? 0,
            'loadings' => $loadingResult['loadings'] ?? [],
            'total_loading' => $loadingResult['total_loading'] ?? 0,
            'final_premium' => round($premium, 2),
            'currency' => $rateCard->currency,
            'frequency' => $rateCard->premium_frequency,
            'quote_date' => now()->toIso8601String(),
            'valid_until' => now()->addDays(30)->toIso8601String(),
        ], 'Quote generated');
    }

    /**
     * Compare quotes across multiple plans.
     * POST /v1/medical/quotes/compare
     */
    public function compare(): JsonResponse
    {
        $validated = request()->validate([
            'plan_ids' => 'required|array|min:1|max:5',
            'plan_ids.*' => 'exists:med_plans,id',
            'members' => 'required|array|min:1',
            'members.*.member_type' => 'required|string',
            'members.*.age' => 'required|integer|min:0|max:100',
            'members.*.gender' => 'nullable|string|in:M,F',
            'addon_ids' => 'nullable|array',
        ]);

        $quotes = [];

        foreach ($validated['plan_ids'] as $planId) {
            $plan = Plan::with(['rateCards' => fn($q) => $q->active()->effective()])
                ->find($planId);

            if (!$plan) {
                $quotes[] = [
                    'plan_id' => $planId,
                    'success' => false,
                    'error' => 'Plan not found',
                ];
                continue;
            }

            $rateCard = $plan->active_rate_card;

            if (!$rateCard) {
                $quotes[] = [
                    'plan_id' => $planId,
                    'plan_name' => $plan->name,
                    'success' => false,
                    'error' => 'No active rate card',
                ];
                continue;
            }

            $result = $this->premiumCalculator->calculateTotalPremium(
                $rateCard,
                $validated['members'],
                $validated['addon_ids'] ?? []
            );

            if (!$result['success']) {
                $quotes[] = [
                    'plan_id' => $planId,
                    'plan_name' => $plan->name,
                    'success' => false,
                    'error' => $result['message'],
                ];
                continue;
            }

            $quotes[] = [
                'plan_id' => $planId,
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'tier_level' => $plan->tier_level,
                'success' => true,
                'total_premium' => $result['total_premium'],
                'currency' => $rateCard->currency,
                'frequency' => $rateCard->premium_frequency,
            ];
        }

        // Sort by premium (lowest first)
        usort($quotes, function ($a, $b) {
            if (!($a['success'] ?? false)) return 1;
            if (!($b['success'] ?? false)) return -1;
            return ($a['total_premium'] ?? PHP_INT_MAX) <=> ($b['total_premium'] ?? PHP_INT_MAX);
        });

        return $this->success([
            'quotes' => $quotes,
            'members_count' => count($validated['members']),
            'lowest_premium' => collect($quotes)->where('success', true)->min('total_premium'),
        ], 'Quotes compared');
    }

    /**
     * Quick quote for single member.
     * GET /v1/medical/plans/{planId}/quick-quote
     */
    public function quickQuote(string $planId): JsonResponse
    {
        $validated = request()->validate([
            'age' => 'required|integer|min:0|max:100',
            'member_type' => 'required|string',
            'gender' => 'nullable|string|in:M,F',
        ]);

        $plan = Plan::findOrFail($planId);
        $rateCard = $plan->active_rate_card;

        if (!$rateCard) {
            return $this->error('No active rate card', 422);
        }

        $result = $this->premiumCalculator->calculateMemberPremium(
            $rateCard,
            $validated['age'],
            $validated['member_type'],
            $validated['gender'] ?? null
        );

        if (!$result['success']) {
            return $this->error($result['message'], 422);
        }

        return $this->success([
            'plan_name' => $plan->name,
            'premium' => $result['premium'],
            'currency' => $rateCard->currency,
            'frequency' => $rateCard->premium_frequency,
        ], 'Quick quote generated');
    }

    /**
     * Convert premium to different frequency.
     * POST /v1/medical/quotes/convert-frequency
     */
    public function convertFrequency(): JsonResponse
    {
        $validated = request()->validate([
            'premium' => 'required|numeric|min:0',
            'from_frequency' => 'required|string|in:monthly,quarterly,semi_annual,annual',
            'to_frequency' => 'required|string|in:monthly,quarterly,semi_annual,annual',
        ]);

        $converted = $this->premiumCalculator->convertFrequency(
            $validated['premium'],
            $validated['from_frequency'],
            $validated['to_frequency']
        );

        return $this->success([
            'original_premium' => $validated['premium'],
            'original_frequency' => $validated['from_frequency'],
            'converted_premium' => $converted,
            'converted_frequency' => $validated['to_frequency'],
        ], 'Premium converted');
    }
}