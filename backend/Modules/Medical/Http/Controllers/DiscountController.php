<?php

namespace Modules\Medical\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Traits\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Http\Requests\ApplyPromoCodeRequest;
use Modules\Medical\Http\Requests\DiscountRuleRequest;
use Modules\Medical\Http\Requests\PromoCodeRequest;
use Modules\Medical\Http\Requests\SimulateDiscountRequest;
use Modules\Medical\Http\Requests\ValidatePromoCodeRequest;
use Modules\Medical\Http\Resources\DiscountRuleResource;
use Modules\Medical\Http\Resources\PromoCodeResource;
use Modules\Medical\Models\DiscountRule;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Services\DiscountService;

class DiscountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DiscountService $discountService,
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
        $query = DiscountRule::with(['scheme', 'plan']);

        $query->when(request('search'), function ($q, $search) {
            $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                   ->orWhere('code', 'like', "%{$search}%"));
        })
        ->when(request('adjustment_type'), fn ($q, $v) => $q->where('adjustment_type', $v))
        ->when(request('scheme_id'), fn ($q, $v) => $q->where('scheme_id', $v))
        ->when(request('plan_id'), fn ($q, $v) => $q->where('plan_id', $v))
        ->when(request('active_only'), fn ($q) => $q->active()->effective())
        ->orderByDesc('priority');

        $rules = $query->paginate(min((int) request('per_page', 20), 100));

        return $this->paginated(DiscountRuleResource::collection($rules), 'Discount rules retrieved');
    }

    /**
     * Create discount rule.
     * POST /v1/medical/discount-rules
     */
    public function store(DiscountRuleRequest $request): JsonResponse
    {
        $rule = DiscountRule::create($request->validated());
        $rule->load(['scheme', 'plan']);

        return $this->success(new DiscountRuleResource($rule), 'Discount rule created', 201);
    }

    /**
     * Show discount rule.
     * GET /v1/medical/discount-rules/{id}
     */
    public function show(string $id): JsonResponse
    {
        $rule = DiscountRule::with(['scheme', 'plan', 'promoCodes'])->findOrFail($id);

        return $this->success(new DiscountRuleResource($rule), 'Discount rule retrieved');
    }

    /**
     * Update discount rule.
     * PUT /v1/medical/discount-rules/{id}
     */
    public function update(DiscountRuleRequest $request, string $id): JsonResponse
    {
        $rule = DB::transaction(function () use ($request, $id) {
            $rule = DiscountRule::findOrFail($id);
            $rule->update($request->validated());

            return $rule->fresh(['scheme', 'plan']);
        });

        return $this->success(new DiscountRuleResource($rule), 'Discount rule updated');
    }

    /**
     * Delete discount rule.
     * DELETE /v1/medical/discount-rules/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $rule = DiscountRule::withCount('promoCodes')->findOrFail($id);

        if ($rule->promo_codes_count > 0) {
            throw new BusinessException('Cannot delete a rule that has promo codes. Remove the promo codes first.');
        }

        $rule->delete();

        return $this->success(null, 'Discount rule deleted');
    }

    /**
     * Get applicable discounts for a plan.
     * GET /v1/medical/plans/{planId}/discounts
     */
    public function forPlan(string $planId): JsonResponse
    {
        $plan      = Plan::findOrFail($planId);
        $discounts = $this->discountService->getApplicableDiscounts($plan, request('context', []));

        return $this->success(
            DiscountRuleResource::collection(collect($discounts)),
            'Applicable discounts retrieved'
        );
    }

    /**
     * Simulate discount calculation.
     * POST /v1/medical/discounts/simulate
     */
    public function simulate(SimulateDiscountRequest $request): JsonResponse
    {
        $rules  = DiscountRule::whereIn('id', $request->validated('discount_rule_ids'))->get();
        $result = $this->discountService->calculateDiscounts($request->validated('premium'), $rules->all());

        return $this->success($result, 'Discount simulated');
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
        $query = PromoCode::with('discountRule');

        $query->when(request('search'), function ($q, $search) {
            $q->where(fn ($q) => $q->where('code', 'like', "%{$search}%")
                                   ->orWhere('name', 'like', "%{$search}%"));
        })
        ->when(request('discount_rule_id'), fn ($q, $v) => $q->where('discount_rule_id', $v))
        ->when(request('active_only'), fn ($q) => $q->usable())
        ->latest();

        $codes = $query->paginate(min((int) request('per_page', 20), 100));

        return $this->paginated(PromoCodeResource::collection($codes), 'Promo codes retrieved');
    }

    /**
     * Create promo code.
     * POST /v1/medical/promo-codes
     *
     * Uniqueness is enforced by the database unique index on `med_promo_codes.code`.
     * A UniqueConstraintViolationException is caught and surfaced as a 422 instead of a
     * check-then-insert pattern which is vulnerable to race conditions.
     */
    public function storePromoCode(PromoCodeRequest $request): JsonResponse
    {
        try {
            $promoCode = PromoCode::create([
                ...$request->validated(),
                'code' => strtoupper($request->validated('code')),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException('A promo code with that code already exists.');
        }

        $promoCode->load('discountRule');

        return $this->success(new PromoCodeResource($promoCode), 'Promo code created', 201);
    }

    /**
     * Show promo code.
     * GET /v1/medical/promo-codes/{id}
     */
    public function showPromoCode(string $id): JsonResponse
    {
        $promoCode = PromoCode::with('discountRule')->findOrFail($id);

        return $this->success(new PromoCodeResource($promoCode), 'Promo code retrieved');
    }

    /**
     * Update promo code.
     * PUT /v1/medical/promo-codes/{id}
     */
    public function updatePromoCode(PromoCodeRequest $request, string $id): JsonResponse
    {
        $promoCode = DB::transaction(function () use ($request, $id) {
            $promoCode = PromoCode::findOrFail($id);

            if ($request->has('code') && $promoCode->current_uses > 0) {
                throw new BusinessException('Cannot change the code after it has been used.');
            }

            $promoCode->update($request->validated());

            return $promoCode->fresh('discountRule');
        });

        return $this->success(new PromoCodeResource($promoCode), 'Promo code updated');
    }

    /**
     * Delete promo code.
     * DELETE /v1/medical/promo-codes/{id}
     */
    public function destroyPromoCode(string $id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);

        if ($promoCode->current_uses > 0) {
            throw new BusinessException('Cannot delete a promo code that has already been used.');
        }

        $promoCode->delete();

        return $this->success(null, 'Promo code deleted');
    }

    /**
     * Validate a promo code (public endpoint).
     * POST /v1/medical/promo-codes/validate
     */
    public function validatePromoCode(ValidatePromoCodeRequest $request): JsonResponse
    {
        $result = $this->discountService->validatePromoCode(
            $request->validated('code'),
            $request->validated('scheme_id'),
            $request->validated('plan_id')
        );

        if (!$result['valid']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([
            'valid'          => true,
            'discount_value' => $result['discount_rule']->formatted_value,
            'discount_name'  => $result['discount_rule']->name,
        ], 'Promo code is valid');
    }

    /**
     * Apply promo code to premium.
     * POST /v1/medical/promo-codes/apply
     */
    public function applyPromoCode(ApplyPromoCodeRequest $request): JsonResponse
    {
        $result = $this->discountService->applyPromoCode(
            $request->validated('code'),
            $request->validated('premium'),
            $request->validated('plan_id')
        );

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result, 'Promo code applied');
    }
}
