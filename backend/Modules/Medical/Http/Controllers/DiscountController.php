<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\DiscountRule;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Models\Plan;
use Modules\Medical\Http\Requests\DiscountRuleRequest;
use Modules\Medical\Http\Requests\PromoCodeRequest;
use Modules\Medical\Http\Resources\DiscountRuleResource;
use Modules\Medical\Http\Resources\PromoCodeResource;
use Modules\Medical\Services\DiscountService;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class DiscountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DiscountService $discountService
    ) {}

    // =========================================================================
    // DISCOUNT RULES
    // =========================================================================

    /**
     * List discount rules.
     * GET /v1/medical/discount-rules
     */
    public function index(): JsonResponse
    {
        try {
            $query = DiscountRule::with(['scheme', 'plan']);

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($adjustmentType = request('adjustment_type')) {
                $query->where('adjustment_type', $adjustmentType);
            }

            if ($schemeId = request('scheme_id')) {
                $query->where('scheme_id', $schemeId);
            }

            if ($planId = request('plan_id')) {
                $query->where('plan_id', $planId);
            }

            if (request('active_only', false)) {
                $query->active()->effective();
            }

            $query->orderByDesc('priority');

            $rules = $query->paginate(request('per_page', 20));

            return $this->success(
                DiscountRuleResource::collection($rules),
                'Discount rules retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve discount rules: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create discount rule.
     * POST /v1/medical/discount-rules
     */
    public function store(DiscountRuleRequest $request): JsonResponse
    {
        try {
            $rule = DB::transaction(function () use ($request) {
                return DiscountRule::create($request->validated());
            });

            $rule->load(['scheme', 'plan']);

            return $this->success(
                new DiscountRuleResource($rule),
                'Discount rule created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create discount rule', 500);
        }
    }

    /**
     * Show discount rule.
     * GET /v1/medical/discount-rules/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $rule = DiscountRule::with(['scheme', 'plan', 'promoCodes'])
                ->findOrFail($id);

            return $this->success(
                new DiscountRuleResource($rule),
                'Discount rule retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Discount rule not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve discount rule', 500);
        }
    }

    /**
     * Update discount rule.
     * PUT /v1/medical/discount-rules/{id}
     */
    public function update(DiscountRuleRequest $request, string $id): JsonResponse
    {
        try {
            $rule = DB::transaction(function () use ($request, $id) {
                $rule = DiscountRule::findOrFail($id);
                $rule->update($request->validated());
                return $rule->fresh(['scheme', 'plan']);
            });

            return $this->success(
                new DiscountRuleResource($rule),
                'Discount rule updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Discount rule not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update discount rule', 500);
        }
    }

    /**
     * Delete discount rule.
     * DELETE /v1/medical/discount-rules/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $rule = DiscountRule::withCount('promoCodes')->findOrFail($id);

                if ($rule->promo_codes_count > 0) {
                    throw new Exception('Cannot delete rule with promo codes. Remove promo codes first.', 422);
                }

                $rule->delete();
            });

            return $this->success(null, 'Discount rule deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Discount rule not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Get applicable discounts for a plan.
     * GET /v1/medical/plans/{planId}/discounts
     */
    public function forPlan(string $planId): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($planId);
            $context = request('context', []);

            $discounts = $this->discountService->getApplicableDiscounts($plan, $context);

            return $this->success(
                DiscountRuleResource::collection(collect($discounts)),
                'Applicable discounts retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve applicable discounts', 500);
        }
    }

    /**
     * Simulate discount calculation.
     * POST /v1/medical/discounts/simulate
     */
    public function simulate(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'premium' => 'required|numeric|min:0',
                'discount_rule_ids' => 'required|array|min:1',
                'discount_rule_ids.*' => 'exists:med_discount_rules,id',
            ]);

            $rules = DiscountRule::whereIn('id', $validated['discount_rule_ids'])->get();
            $result = $this->discountService->calculateDiscounts($validated['premium'], $rules->all());

            return $this->success($result, 'Discount simulated');
        } catch (Throwable $e) {
            return $this->error('Simulation failed: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // PROMO CODES
    // =========================================================================

    /**
     * List promo codes.
     * GET /v1/medical/promo-codes
     */
    public function promoCodes(): JsonResponse
    {
        try {
            $query = PromoCode::with('discountRule');

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            if ($ruleId = request('discount_rule_id')) {
                $query->where('discount_rule_id', $ruleId);
            }

            if (request('active_only', false)) {
                $query->usable();
            }

            $query->latest();

            $codes = $query->paginate(request('per_page', 20));

            return $this->success(
                PromoCodeResource::collection($codes),
                'Promo codes retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve promo codes', 500);
        }
    }

    /**
     * Create promo code.
     * POST /v1/medical/promo-codes
     */
    public function storePromoCode(PromoCodeRequest $request): JsonResponse
    {
        try {
            $promoCode = DB::transaction(function () use ($request) {
                // Check if code already exists
                if (PromoCode::where('code', strtoupper($request->code))->exists()) {
                    throw new Exception('Promo code already exists', 422);
                }

                $code = PromoCode::create([
                    ...$request->validated(),
                    'code' => strtoupper($request->code),
                ]);

                return $code;
            });

            $promoCode->load('discountRule');

            return $this->success(
                new PromoCodeResource($promoCode),
                'Promo code created',
                201
            );
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Show promo code.
     * GET /v1/medical/promo-codes/{id}
     */
    public function showPromoCode(string $id): JsonResponse
    {
        try {
            $promoCode = PromoCode::with('discountRule')->findOrFail($id);

            return $this->success(
                new PromoCodeResource($promoCode),
                'Promo code retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Promo code not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve promo code', 500);
        }
    }

    /**
     * Update promo code.
     * PUT /v1/medical/promo-codes/{id}
     */
    public function updatePromoCode(PromoCodeRequest $request, string $id): JsonResponse
    {
        try {
            $promoCode = DB::transaction(function () use ($request, $id) {
                $promoCode = PromoCode::findOrFail($id);

                // Don't allow changing the code if it's been used
                if ($request->has('code') && $promoCode->current_uses > 0) {
                    throw new Exception('Cannot change code that has been used', 422);
                }

                $promoCode->update($request->validated());
                return $promoCode->fresh('discountRule');
            });

            return $this->success(
                new PromoCodeResource($promoCode),
                'Promo code updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Promo code not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Delete promo code.
     * DELETE /v1/medical/promo-codes/{id}
     */
    public function destroyPromoCode(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $promoCode = PromoCode::findOrFail($id);

                if ($promoCode->current_uses > 0) {
                    throw new Exception('Cannot delete promo code that has been used', 422);
                }

                $promoCode->delete();
            });

            return $this->success(null, 'Promo code deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Promo code not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Validate promo code (public endpoint).
     * POST /v1/medical/promo-codes/validate
     */
    public function validatePromoCode(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'code' => 'required|string',
                'scheme_id' => 'nullable|exists:med_schemes,id',
                'plan_id' => 'nullable|exists:med_plans,id',
            ]);

            $result = $this->discountService->validatePromoCode(
                $validated['code'],
                $validated['scheme_id'] ?? null,
                $validated['plan_id'] ?? null
            );

            if (!$result['valid']) {
                return $this->error($result['message'], 400);
            }

            return $this->success([
                'valid' => true,
                'discount_value' => $result['discount_rule']->formatted_value,
                'discount_name' => $result['discount_rule']->name,
            ], 'Promo code is valid');
        } catch (Throwable $e) {
            return $this->error('Validation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Apply promo code to premium.
     * POST /v1/medical/promo-codes/apply
     */
    public function applyPromoCode(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'code' => 'required|string',
                'premium' => 'required|numeric|min:0',
                'plan_id' => 'nullable|exists:med_plans,id',
            ]);

            $result = $this->discountService->applyPromoCode(
                $validated['code'],
                $validated['premium'],
                $validated['plan_id'] ?? null
            );

            if (!$result['success']) {
                return $this->error($result['message'], 400);
            }

            return $this->success($result, 'Promo code applied');
        } catch (Throwable $e) {
            return $this->error('Failed to apply promo code', 500);
        }
    }
}