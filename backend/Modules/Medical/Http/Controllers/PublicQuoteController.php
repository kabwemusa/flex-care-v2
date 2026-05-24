<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\Addon;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Http\Requests\PublicQuoteRequest;
use Modules\Medical\Services\PremiumService;
use Modules\Medical\Services\DiscountService;
use App\Traits\ApiResponse;
use Throwable;

/**
 * Controller for public-facing quote endpoints.
 * Validates that plans/addons are active and visible before generating quotes.
 */
class PublicQuoteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PremiumService $premiumCalculator,
        protected DiscountService $discountService
    ) {}

    /**
     * Generate a public quote for a plan.
     * POST /v1/medical/public/quotes
     *
     * Mandatory addons for the plan are auto-injected regardless of user selection.
     */
    public function generate(PublicQuoteRequest $request): JsonResponse
    {
        try {
            $plan = Plan::query()
                ->where('is_active', true)
                ->where('is_visible', true)
                ->effective()
                ->with(['scheme', 'rateCards'])
                ->find($request->plan_id);

            if (!$plan) {
                return $this->error('Plan not available', 404);
            }

            $rateCard = $plan->rateCards
                ->where('is_active', true)
                ->sortByDesc('effective_from')
                ->filter(function ($rc) {
                    $now = now();
                    return (!$rc->effective_from || $rc->effective_from <= $now) &&
                           (!$rc->effective_to || $rc->effective_to >= $now);
                })
                ->first();

            if (!$rateCard) {
                return $this->error('Plan pricing not available', 422);
            }

            // Auto-inject mandatory addons for this plan
            $userAddonIds = $request->addon_ids ?? [];
            $mandatoryAddonIds = PlanAddon::where('plan_id', $plan->id)
                ->where('is_active', true)
                ->mandatory()
                ->pluck('addon_id')
                ->toArray();

            $addonIds = array_values(array_unique(array_merge($userAddonIds, $mandatoryAddonIds)));

            // Validate all addon IDs are active
            if (!empty($addonIds)) {
                $validAddonCount = Addon::whereIn('id', $addonIds)
                    ->where('is_active', true)
                    ->effective()
                    ->count();

                if ($validAddonCount !== count($addonIds)) {
                    return $this->error('One or more addons are not available', 422);
                }
            }

            // Calculate premium (pass plan_id for addon context)
            $premiumResult = $this->premiumCalculator->calculateQuote(
                $rateCard,
                $request->members,
                $addonIds,
                $plan->id
            );

            if (!$premiumResult['success']) {
                return $this->error('Unable to calculate premium', 422);
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

            // Apply tax/levy on the net premium (after all discounts and promos)
            $taxRate     = (float)  config('medical.tax_rate', 0.05);
            $taxName     = (string) config('medical.tax_name', 'VAT');
            $netPremium  = round($premium, 2);
            $taxAmount   = round($netPremium * $taxRate, 2);
            $grossPremium = round($netPremium + $taxAmount, 2);

            // Enhance member data with individual premiums
            $membersWithPremiums = [];
            foreach ($request->members as $index => $memberData) {
                $memberBreakdown = collect($premiumResult['breakdown']['members'] ?? [])->firstWhere('index', $index);
                $membersWithPremiums[] = [
                    'member_type' => $memberData['member_type'],
                    'age' => $memberData['age'],
                    'gender' => $memberData['gender'] ?? null,
                    'premium' => $memberBreakdown['amount'] ?? 0,
                ];
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
                'premium_basis'  => $premiumResult['premium_basis'],
                'members'        => $membersWithPremiums,
                'base_premium'   => $premiumResult['base_premium'],
                'addon_premium'  => $premiumResult['addon_premium'],
                'addons'         => $premiumResult['breakdown']['addons'] ?? [],
                'discounts'      => $discountResult['discounts'] ?? [],
                'total_discount' => $discountResult['total_discount'] ?? 0,
                'promo_discount' => $promoResult['discount_amount'] ?? 0,
                'loadings'       => [],
                'total_loading'  => 0,
                'net_premium'    => $netPremium,
                'tax_name'       => $taxName,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'final_premium'  => $grossPremium,
                'currency'       => $rateCard->currency,
                'frequency'      => $rateCard->premium_frequency,
                'quote_date'     => now()->toIso8601String(),
                'valid_until'    => now()->addDays(config('medical.quote_validity_days', 30))->toIso8601String(),
            ], 'Quote generated');
        } catch (Throwable $e) {
            return $this->error('Unable to generate quote', 500);
        }
    }

    /**
     * Compare quotes across multiple plans.
     * POST /v1/medical/public/quotes/compare
     *
     * Each plan's mandatory addons are auto-injected.
     */
    public function compare(): JsonResponse
    {
        try {
            $planIds = request('plan_ids', []);
            $members = request('members', []);
            $addonIds = request('addon_ids', []);

            if (!is_array($planIds) || count($planIds) < 1 || count($planIds) > 5) {
                return $this->error('Select 1 to 5 plans to compare', 422);
            }

            if (!is_array($members) || count($members) < 1 || count($members) > 20) {
                return $this->error('Provide 1 to 20 members', 422);
            }

            $plans = Plan::query()
                ->whereIn('id', $planIds)
                ->where('is_active', true)
                ->where('is_visible', true)
                ->effective()
                ->with(['rateCards'])
                ->get();

            if ($plans->isEmpty()) {
                return $this->error('No valid plans found', 422);
            }

            // Validate user-provided addons
            if (!empty($addonIds)) {
                $validAddonCount = Addon::whereIn('id', $addonIds)
                    ->where('is_active', true)
                    ->effective()
                    ->count();

                if ($validAddonCount !== count($addonIds)) {
                    $addonIds = [];
                }
            }

            $quotes = [];

            foreach ($plans as $plan) {
                $rateCard = $plan->rateCards
                    ->where('is_active', true)
                    ->sortByDesc('effective_from')
                    ->filter(function ($rc) {
                        $now = now();
                        return (!$rc->effective_from || $rc->effective_from <= $now) &&
                               (!$rc->effective_to || $rc->effective_to >= $now);
                    })
                    ->first();

                if (!$rateCard) {
                    $quotes[] = [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'success' => false,
                        'error' => 'Pricing not available',
                    ];
                    continue;
                }

                // Auto-inject per-plan mandatory addons
                $planMandatoryAddonIds = PlanAddon::where('plan_id', $plan->id)
                    ->where('is_active', true)
                    ->mandatory()
                    ->pluck('addon_id')
                    ->toArray();

                $planAddonIds = array_values(array_unique(array_merge($addonIds, $planMandatoryAddonIds)));

                $result = $this->premiumCalculator->calculateQuote(
                    $rateCard,
                    $members,
                    $planAddonIds,
                    $plan->id
                );

                if (!$result['success']) {
                    $quotes[] = [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'success' => false,
                        'error' => 'Unable to calculate',
                    ];
                    continue;
                }

                $quotes[] = [
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'plan_name' => $plan->name,
                    'tier_level' => $plan->tier_level,
                    'success' => true,
                    'total_premium' => $result['total_premium'],
                    'currency' => $rateCard->currency,
                    'frequency' => $rateCard->premium_frequency,
                ];
            }

            usort($quotes, function ($a, $b) {
                if (!($a['success'] ?? false)) return 1;
                if (!($b['success'] ?? false)) return -1;
                return ($a['total_premium'] ?? PHP_INT_MAX) <=> ($b['total_premium'] ?? PHP_INT_MAX);
            });

            return $this->success([
                'quotes' => $quotes,
                'members_count' => count($members),
                'lowest_premium' => collect($quotes)->where('success', true)->min('total_premium'),
            ], 'Quotes compared');
        } catch (Throwable $e) {
            return $this->error('Unable to compare quotes', 500);
        }
    }
}
