<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Plan;
use Modules\Medical\Http\Requests\PlanRequest;
use Modules\Medical\Http\Resources\PlanResource;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class PlanController extends Controller
{
    use ApiResponse;

    /**
     * List all plans with search & pagination.
     * GET /v1/medical/plans
     */
    public function index(): JsonResponse
    {
        try {
            $query = Plan::query()
                ->with('scheme')
                ->withCount(['planBenefits', 'planAddons']);

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($schemeId = request('scheme_id')) {
                $query->where('scheme_id', $schemeId);
            }

            if ($planType = request('plan_type')) {
                $query->where('plan_type', $planType);
            }

            if (request('active_only', false)) {
                $query->active()->effective();
            }

            $query->ordered();

            $plans = $query->paginate(request('per_page', 20));

            return $this->success(
                PlanResource::collection($plans),
                'Plans retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plans: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new plan.
     * POST /v1/medical/plans
     */
    public function store(PlanRequest $request): JsonResponse
    {
        try {
            $plan = DB::transaction(function () use ($request) {
                $plan = Plan::create($request->validated());
                // Handle initial relationships here if needed in future
                return $plan;
            });

            $plan->load('scheme');

            return $this->success(
                new PlanResource($plan),
                'Plan created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create plan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show plan details.
     * GET /v1/medical/plans/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $plan = Plan::with([
                'scheme',
                'planBenefits.benefit.category',
                'planAddons.addon',
                'rateCards' => fn($q) => $q->latest('effective_from'),
            ])
            ->withCount(['planBenefits', 'planAddons'])
            ->findOrFail($id);

            return $this->success(
                new PlanResource($plan),
                'Plan retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plan details', 500);
        }
    }

    /**
     * Update a plan.
     * PUT /v1/medical/plans/{id}
     */
    public function update(PlanRequest $request, string $id): JsonResponse
    {
        try {
            $plan = DB::transaction(function () use ($request, $id) {
                $plan = Plan::findOrFail($id);
                $plan->update($request->validated());
                return $plan->fresh(['scheme']);
            });

            return $this->success(
                new PlanResource($plan),
                'Plan updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update plan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a plan.
     * DELETE /v1/medical/plans/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $plan = Plan::findOrFail($id);

                // Check if plan has active rate cards
                if ($plan->rateCards()->where('is_active', true)->exists()) {
                    throw new Exception('Cannot delete plan with active rate cards. Deactivate them first.', 422);
                }

                $plan->delete();
            });

            return $this->success(null, 'Plan deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate/deactivate a plan.
     * POST /v1/medical/plans/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);

            // Toggle the is_active status
            $plan->is_active = !$plan->is_active;
            $plan->save();

            $action = $plan->is_active ? 'activated' : 'deactivated';

            return $this->success(
                new PlanResource($plan->fresh(['scheme'])),
                "Plan {$action} successfully"
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update plan status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clone a plan.
     * POST /v1/medical/plans/{id}/clone
     */
    public function clone(string $id): JsonResponse
    {
        try {
            $newPlan = DB::transaction(function () use ($id) {
                $source = Plan::with([
                    'planBenefits.memberLimits',
                    'planAddons',
                    'exclusions',
                    'waitingPeriods',
                ])->findOrFail($id);

                $newPlan = $source->replicate(['id', 'code', 'created_at', 'updated_at']);
                $newPlan->name = $source->name . ' (Copy)';
                $newPlan->is_active = false;
                $newPlan->save();

                // Clone plan benefits and their nested limits
                foreach ($source->planBenefits as $pb) {
                    $newPb = $pb->replicate(['id', 'created_at', 'updated_at']);
                    $newPb->plan_id = $newPlan->id;
                    $newPb->save();

                    foreach ($pb->memberLimits as $limit) {
                        $newLimit = $limit->replicate(['id', 'created_at', 'updated_at']);
                        $newLimit->plan_benefit_id = $newPb->id;
                        $newLimit->save();
                    }
                }

                // Clone plan addons
                foreach ($source->planAddons as $pa) {
                    $newPa = $pa->replicate(['id', 'created_at', 'updated_at']);
                    $newPa->plan_id = $newPlan->id;
                    $newPa->save();
                }

                // Clone exclusions
                foreach ($source->exclusions as $exc) {
                    $newExc = $exc->replicate(['id', 'code', 'created_at', 'updated_at']);
                    $newExc->plan_id = $newPlan->id;
                    $newExc->save();
                }

                // Clone waiting periods
                foreach ($source->waitingPeriods as $wp) {
                    $newWp = $wp->replicate(['id', 'created_at', 'updated_at']);
                    $newWp->plan_id = $newPlan->id;
                    $newWp->save();
                }

                return $newPlan->fresh(['scheme']);
            });

            return $this->success(
                new PlanResource($newPlan),
                'Plan cloned successfully',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Source plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to clone plan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get plans for dropdown.
     * GET /v1/medical/plans/dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $query = Plan::active()->effective()->ordered();

            if ($schemeId = request('scheme_id')) {
                $query->where('scheme_id', $schemeId);
            }

            $plans = $query->get(['id', 'code', 'name', 'scheme_id', 'plan_type', 'tier_level']);

            return $this->success($plans, 'Plans retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plans list', 500);
        }
    }

    /**
     * Get plans by scheme.
     * GET /v1/medical/schemes/{schemeId}/plans
     */
    public function byScheme(string $schemeId): JsonResponse
    {
        try {
            $plans = Plan::where('scheme_id', $schemeId)
                ->with('scheme')
                ->withCount(['planBenefits', 'planAddons'])
                ->ordered()
                ->get();

            return $this->success(
                PlanResource::collection($plans),
                'Plans retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plans for scheme', 500);
        }
    }

    /**
     * Export plan to PDF.
     * GET /v1/medical/plans/{id}/export-pdf
     */
    public function exportPdf(string $id)
    {
        try {
            $plan = Plan::with([
                'scheme',
                'planBenefits.benefit.category',
                'planBenefits.memberLimits',
                'planAddons.addon.addonBenefits.benefit',
                'rateCards' => fn($q) => $q->where('is_active', true)->latest('effective_from'),
                'exclusions',
                'waitingPeriods',
            ])
            ->withCount(['planBenefits', 'planAddons'])
            ->findOrFail($id);

            // Prepare PDF data
            $pdfData = [
                'plan' => $plan,
                'generated_at' => now()->format('F d, Y'),
                'generated_by' => 'System Administrator', // You can get from auth user
            ];

            // For now, return JSON with all plan data
            // In production, you would use a PDF library like DomPDF or Snappy
            return response()->json([
                'success' => true,
                'message' => 'PDF export data prepared',
                'data' => $pdfData,
                'download_url' => '/api/v1/medical/plans/' . $id . '/export-pdf',
            ]);

            // Example with DomPDF (uncomment when library is installed):
            // $pdf = PDF::loadView('medical::pdf.plan-detail', $pdfData);
            // return $pdf->download($plan->code . '_plan_details.pdf');

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare multiple plans.
     * POST /v1/medical/plans/compare
     */
    public function compare(): JsonResponse
    {
        try {
            $planIds = request('plan_ids', []);

            if (count($planIds) < 2) {
                return $this->error('Select at least 2 plans to compare', 422);
            }

            $plans = Plan::whereIn('id', $planIds)
                ->with(['planBenefits.benefit.category', 'planAddons.addon'])
                ->get();

            // Build comparison matrix
            $allBenefitIds = $plans->flatMap(fn($p) => $p->planBenefits->pluck('benefit_id'))->unique();

            $comparison = [];
            foreach ($allBenefitIds as $benefitId) {
                $row = [];
                foreach ($plans as $plan) {
                    $pb = $plan->planBenefits->firstWhere('benefit_id', $benefitId);
                    $row[$plan->id] = [
                        'is_covered' => $pb?->is_covered ?? false,
                        'limit' => $pb?->formatted_display_value ?? 'Not Covered',
                    ];
                }
                $comparison[$benefitId] = $row;
            }

            return $this->success([
                'plans' => PlanResource::collection($plans),
                'comparison' => $comparison,
            ], 'Comparison generated');
        } catch (Throwable $e) {
            return $this->error('Failed to generate comparison', 500);
        }
    }
}