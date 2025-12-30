<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Benefit;
use Modules\Medical\Models\BenefitCategory;
use Modules\Medical\Models\PlanBenefit;
use Modules\Medical\Http\Requests\BenefitRequest;
use Modules\Medical\Http\Requests\PlanBenefitRequest;
use Modules\Medical\Http\Resources\BenefitResource;
use Modules\Medical\Http\Resources\BenefitCategoryResource;
use Modules\Medical\Http\Resources\PlanBenefitResource;
use Modules\Medical\Services\BenefitService;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class BenefitController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BenefitService $benefitService
    ) {}

    // =========================================================================
    // BENEFIT CATEGORIES
    // =========================================================================

    /**
     * List benefit categories.
     * GET /v1/medical/benefit-categories
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = BenefitCategory::withCount('benefits')
                ->ordered()
                ->get();

            return $this->success(
                BenefitCategoryResource::collection($categories),
                'Categories retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve categories', 500);
        }
    }

    /**
     * Create benefit category.
     * POST /v1/medical/benefit-categories
     */
    public function storeCategory(): JsonResponse
    {
        try {
            $category = DB::transaction(function () {
                $validated = request()->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'icon' => 'nullable|string|max:50',
                    'color' => 'nullable|string|max:20',
                    'sort_order' => 'nullable|integer',
                ]);

                return BenefitCategory::create($validated);
            });

            return $this->success(
                new BenefitCategoryResource($category),
                'Category created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // BENEFIT CATALOG
    // =========================================================================

    /**
     * List all benefits (catalog).
     * GET /v1/medical/benefits
     */
    public function index(): JsonResponse
    {
        try {
            $query = Benefit::with('category');

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($categoryId = request('category_id')) {
                $query->where('category_id', $categoryId);
            }

            if ($benefitType = request('benefit_type')) {
                $query->where('benefit_type', $benefitType);
            }

            if (request('root_only', false)) {
                $query->rootLevel();
            }

            if (request('active_only', true)) {
                $query->active();
            }

            $query->ordered();

            $benefits = $query->paginate(request('per_page', 50));

            return $this->success(
                BenefitResource::collection($benefits),
                'Benefits retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefits', 500);
        }
    }

    /**
     * Create a benefit.
     * POST /v1/medical/benefits
     */
    public function store(BenefitRequest $request): JsonResponse
    {
        try {
            $benefit = DB::transaction(function () use ($request) {
                return Benefit::create($request->validated());
            });

            $benefit->load('category');

            return $this->success(
                new BenefitResource($benefit),
                'Benefit created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create benefit', 500);
        }
    }

    /**
     * Show benefit details.
     * GET /v1/medical/benefits/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $benefit = Benefit::with(['category', 'parent', 'children'])
                ->findOrFail($id);

            return $this->success(
                new BenefitResource($benefit),
                'Benefit retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Benefit not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefit details', 500);
        }
    }

    /**
     * Update a benefit.
     * PUT /v1/medical/benefits/{id}
     */
    public function update(BenefitRequest $request, string $id): JsonResponse
    {
        try {
            $benefit = DB::transaction(function () use ($request, $id) {
                $benefit = Benefit::findOrFail($id);
                $benefit->update($request->validated());
                return $benefit->fresh('category');
            });

            return $this->success(
                new BenefitResource($benefit),
                'Benefit updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Benefit not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update benefit', 500);
        }
    }

    /**
     * Delete a benefit.
     * DELETE /v1/medical/benefits/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $benefit = Benefit::withCount(['children', 'planBenefits'])->findOrFail($id);

                if ($benefit->children_count > 0) {
                    throw new Exception('Cannot delete benefit with sub-benefits', 422);
                }

                if ($benefit->plan_benefits_count > 0) {
                    throw new Exception('Cannot delete benefit assigned to plans', 422);
                }

                $benefit->delete();
            });

            return $this->success(null, 'Benefit deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Benefit not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Get benefits tree by category.
     * GET /v1/medical/benefits/tree
     */
    public function tree(): JsonResponse
    {
        try {
            $categories = BenefitCategory::with([
                'benefits' => fn($q) => $q->rootLevel()->active()->ordered()->with('children')
            ])->ordered()->get();

            return $this->success(
                BenefitCategoryResource::collection($categories),
                'Benefit tree retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefit tree', 500);
        }
    }

    // =========================================================================
    // PLAN BENEFITS (Configuration)
    // =========================================================================

    /**
     * Get benefits for a plan.
     * GET /v1/medical/plans/{planId}/benefits
     */
    public function planBenefits(string $planId): JsonResponse
    {
        try {
            $planBenefits = PlanBenefit::where('plan_id', $planId)
                ->with(['benefit.category', 'memberLimits'])
                ->rootLevel()
                ->ordered()
                ->get();

            return $this->success(
                PlanBenefitResource::collection($planBenefits),
                'Plan benefits retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plan benefits', 500);
        }
    }

    /**
     * Add benefit to plan.
     * POST /v1/medical/plans/{planId}/benefits
     */
    public function addToPlan(PlanBenefitRequest $request, string $planId): JsonResponse
    {
        try {
            $planBenefit = DB::transaction(function () use ($request, $planId) {
                // Check if already exists
                $exists = PlanBenefit::where('plan_id', $planId)
                    ->where('benefit_id', $request->benefit_id)
                    ->exists();

                if ($exists) {
                    throw new Exception('Benefit already added to this plan', 422);
                }

                $planBenefit = PlanBenefit::create([
                    'plan_id' => $planId,
                    ...$request->validated(),
                ]);

                // Add member-specific limits if provided
                if ($memberLimits = $request->member_limits) {
                    foreach ($memberLimits as $limit) {
                        $planBenefit->memberLimits()->create($limit);
                    }
                }

                return $planBenefit->load(['benefit.category', 'memberLimits']);
            });

            return $this->success(
                new PlanBenefitResource($planBenefit),
                'Benefit added to plan',
                201
            );
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Update plan benefit configuration.
     * PUT /v1/medical/plan-benefits/{id}
     */
    public function updatePlanBenefit(PlanBenefitRequest $request, string $id): JsonResponse
    {
        try {
            $planBenefit = DB::transaction(function () use ($request, $id) {
                $planBenefit = PlanBenefit::findOrFail($id);
                $planBenefit->update($request->validated());

                // Update member limits if provided
                if ($request->has('member_limits')) {
                    $planBenefit->memberLimits()->delete();
                    foreach ($request->member_limits as $limit) {
                        $planBenefit->memberLimits()->create($limit);
                    }
                }

                return $planBenefit->fresh(['benefit.category', 'memberLimits']);
            });

            return $this->success(
                new PlanBenefitResource($planBenefit),
                'Plan benefit updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan benefit not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update plan benefit', 500);
        }
    }

    /**
     * Remove benefit from plan.
     * DELETE /v1/medical/plan-benefits/{id}
     */
    public function removeFromPlan(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $planBenefit = PlanBenefit::findOrFail($id);

                // Check for sub-benefits
                if ($planBenefit->childPlanBenefits()->exists()) {
                    throw new Exception('Remove sub-benefits first', 422);
                }

                $planBenefit->memberLimits()->delete();
                $planBenefit->delete();
            });

            return $this->success(null, 'Benefit removed from plan');
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan benefit not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Bulk add benefits to plan.
     * POST /v1/medical/plans/{planId}/benefits/bulk
     */
    public function bulkAddToPlan(string $planId): JsonResponse
    {
        try {
            $added = DB::transaction(function () use ($planId) {
                $validated = request()->validate([
                    'benefits' => 'required|array|min:1',
                    'benefits.*.benefit_id' => 'required|exists:med_benefits,id',
                    'benefits.*.limit_amount' => 'nullable|numeric|min:0',
                    'benefits.*.is_covered' => 'nullable|boolean',
                ]);

                $count = 0;
                foreach ($validated['benefits'] as $data) {
                    $exists = PlanBenefit::where('plan_id', $planId)
                        ->where('benefit_id', $data['benefit_id'])
                        ->exists();

                    if (!$exists) {
                        PlanBenefit::create([
                            'plan_id' => $planId,
                            'benefit_id' => $data['benefit_id'],
                            'limit_amount' => $data['limit_amount'] ?? null,
                            'is_covered' => $data['is_covered'] ?? true,
                        ]);
                        $count++;
                    }
                }
                return $count;
            });

            return $this->success(
                ['added_count' => $added],
                "{$added} benefits added to plan"
            );
        } catch (Throwable $e) {
            return $this->error('Failed to bulk add benefits', 500);
        }
    }

    // =========================================================================
    // BENEFIT SCHEDULE & ELIGIBILITY
    // =========================================================================

    /**
     * Get benefit schedule for a plan (formatted for display).
     * GET /v1/medical/plans/{planId}/benefit-schedule
     */
    public function schedule(string $planId): JsonResponse
    {
        try {
            $memberType = request('member_type');
            $schedule = $this->benefitService->getBenefitSchedule($planId, $memberType);

            return $this->success($schedule, 'Benefit schedule retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefit schedule', 500);
        }
    }

    /**
     * Check benefit eligibility.
     * POST /v1/medical/benefits/check-eligibility
     */
    public function checkEligibility(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'plan_id' => 'required|exists:med_plans,id',
                'benefit_id' => 'required|exists:med_benefits,id',
                'member_type' => 'required|string',
                'member_age' => 'required|integer|min:0',
                'cover_start_date' => 'nullable|date',
                'claim_amount' => 'nullable|numeric|min:0',
                'used_amount' => 'nullable|numeric|min:0',
            ]);

            $result = $this->benefitService->checkEligibility(
                $validated['plan_id'],
                $validated['benefit_id'],
                $validated['member_type'],
                $validated['member_age'],
                isset($validated['cover_start_date']) ? \Carbon\Carbon::parse($validated['cover_start_date']) : null,
                $validated['claim_amount'] ?? null,
                $validated['used_amount'] ?? 0
            );

            return $this->success($result, 'Eligibility checked');
        } catch (Throwable $e) {
            return $this->error('Eligibility check failed: ' . $e->getMessage(), 500);
        }
    }
}