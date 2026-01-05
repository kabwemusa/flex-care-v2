<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\PlanExclusion;
use Modules\Medical\Http\Requests\PlanExclusionRequest;
use Modules\Medical\Http\Resources\PlanExclusionResource;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class PlanExclusionController extends Controller
{
    use ApiResponse;

    /**
     * Get exclusions for a plan.
     * GET /v1/medical/plans/{planId}/exclusions
     */
    public function index(string $planId): JsonResponse
    {
        try {
            $query = PlanExclusion::where('plan_id', $planId)
                ->with('benefit:id,name,code');

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($exclusionType = request('exclusion_type')) {
                $query->where('exclusion_type', $exclusionType);
            }

            if (request('benefit_specific', false)) {
                $query->benefitSpecific();
            } elseif (request('general', false)) {
                $query->general();
            }

            if (request('active_only', false)) {
                $query->where('is_active', true);
            }

            $query->ordered();

            $exclusions = $query->paginate(request('per_page', 20));

            return $this->success(
                PlanExclusionResource::collection($exclusions),
                'Exclusions retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve exclusions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create exclusion for a plan.
     * POST /v1/medical/plans/{planId}/exclusions
     */
    public function store(PlanExclusionRequest $request, string $planId): JsonResponse
    {
        try {
            $exclusion = DB::transaction(function () use ($request, $planId) {
                $data = $request->validated();
                $data['plan_id'] = $planId;

                return PlanExclusion::create($data);
            });

            $exclusion->load('benefit:id,name,code');

            return $this->success(
                new PlanExclusionResource($exclusion),
                'Exclusion created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create exclusion: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show exclusion details.
     * GET /v1/medical/plan-exclusions/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $exclusion = PlanExclusion::with(['plan:id,name,code', 'benefit:id,name,code'])
                ->findOrFail($id);

            return $this->success(
                new PlanExclusionResource($exclusion),
                'Exclusion retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Exclusion not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve exclusion details', 500);
        }
    }

    /**
     * Update exclusion.
     * PUT /v1/medical/plan-exclusions/{id}
     */
    public function update(PlanExclusionRequest $request, string $id): JsonResponse
    {
        try {
            $exclusion = DB::transaction(function () use ($request, $id) {
                $exclusion = PlanExclusion::findOrFail($id);
                $exclusion->update($request->validated());
                return $exclusion->fresh('benefit:id,name,code');
            });

            return $this->success(
                new PlanExclusionResource($exclusion),
                'Exclusion updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Exclusion not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update exclusion: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete exclusion.
     * DELETE /v1/medical/plan-exclusions/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $exclusion = PlanExclusion::findOrFail($id);
                $exclusion->delete();
            });

            return $this->success(null, 'Exclusion deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Exclusion not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to delete exclusion: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate/deactivate an exclusion.
     * POST /v1/medical/plan-exclusions/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $exclusion = PlanExclusion::findOrFail($id);

            // Toggle the is_active status
            $exclusion->is_active = !$exclusion->is_active;
            $exclusion->save();

            $action = $exclusion->is_active ? 'activated' : 'deactivated';

            return $this->success(
                new PlanExclusionResource($exclusion->fresh('benefit:id,name,code')),
                "Exclusion {$action} successfully"
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Exclusion not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update exclusion status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete exclusions.
     * POST /v1/medical/plans/{planId}/exclusions/bulk-delete
     */
    public function bulkDelete(string $planId): JsonResponse
    {
        try {
            $validated = request()->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|exists:med_plan_exclusions,id',
            ]);

            DB::transaction(function () use ($planId, $validated) {
                PlanExclusion::where('plan_id', $planId)
                    ->whereIn('id', $validated['ids'])
                    ->delete();
            });

            return $this->success(null, 'Exclusions deleted successfully');
        } catch (Throwable $e) {
            return $this->error('Failed to delete exclusions: ' . $e->getMessage(), 500);
        }
    }
}
