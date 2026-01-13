<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Addon;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Http\Requests\AddonRequest;
use Modules\Medical\Http\Resources\AddonResource;
use Modules\Medical\Http\Resources\PlanAddonResource;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class AddonController extends Controller
{
    use ApiResponse;

    // =========================================================================
    // ADDON CATALOG
    // =========================================================================

    /**
     * List all addons.
     * GET /v1/medical/addons
     */
    public function index(): JsonResponse
    {
        try {
            $query = Addon::withCount('planAddons');

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($addonType = request('addon_type')) {
                $query->where('addon_type', $addonType);
            }

            if (request('active_only', false)) {
                $query->active()->effective();
            }

            $query->ordered();

            $addons = $query->paginate(request('per_page', 20));

            return $this->success(
                AddonResource::collection($addons),
                'Addons retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve addons: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create addon.
     * POST /v1/medical/addons
     */
    public function store(AddonRequest $request): JsonResponse
    {
        try {
            $addon = DB::transaction(function () use ($request) {
                return Addon::create($request->validated());
            });

            return $this->success(
                new AddonResource($addon),
                'Addon created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create addon', 500);
        }
    }

    /**
     * Show addon details.
     * GET /v1/medical/addons/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);

            return $this->success(
                new AddonResource($addon),
                'Addon retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve addon details', 500);
        }
    }

    /**
     * Update addon.
     * PUT /v1/medical/addons/{id}
     */
    public function update(AddonRequest $request, string $id): JsonResponse
    {
        try {
            $addon = DB::transaction(function () use ($request, $id) {
                $addon = Addon::findOrFail($id);
                $addon->update($request->validated());
                return $addon->fresh();
            });

            return $this->success(
                new AddonResource($addon),
                'Addon updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update addon', 500);
        }
    }

    /**
     * Delete addon.
     * DELETE /v1/medical/addons/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $addon = Addon::withCount('planAddons')->findOrFail($id);

                if ($addon->plan_addons_count > 0) {
                    throw new Exception('Cannot delete addon assigned to plans', 422);
                }

                $addon->delete();
            });

            return $this->success(null, 'Addon deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Addon not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate/deactivate an addon.
     * POST /v1/medical/addons/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);

            // Toggle the is_active status
            $addon->is_active = !$addon->is_active;
            $addon->save();

            $action = $addon->is_active ? 'activated' : 'deactivated';

            return $this->success(
                new AddonResource($addon),
                "Addon {$action} successfully"
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update addon status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get addons dropdown.
     * GET /v1/medical/addons/dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $addons = Addon::active()
                ->effective()
                ->ordered()
                ->get(['id', 'code', 'name', 'addon_type']);

            return $this->success($addons, 'Addons retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve addons list', 500);
        }
    }

    // =========================================================================
    // PLAN ADDONS (Configuration)
    // =========================================================================

    /**
     * Get addons for a plan.
     * GET /v1/medical/plans/{planId}/addons
     */
    public function planAddons(string $planId): JsonResponse
    {
        try {
            $planAddons = PlanAddon::where('plan_id', $planId)
                ->with('addon')
                ->ordered()
                ->get();

            return $this->success(
                PlanAddonResource::collection($planAddons),
                'Plan addons retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve plan addons', 500);
        }
    }

    /**
     * Configure addon for plan.
     * POST /v1/medical/plans/{planId}/addons
     */
    public function configurePlanAddon(string $planId): JsonResponse
    {
        try {
            $planAddon = DB::transaction(function () use ($planId) {
                $validated = request()->validate([
                    'addon_id' => 'required|exists:med_addons,id',
                    'availability' => 'required|string|in:optional,mandatory,included,conditional',
                    'conditions' => 'nullable|array',
                    'benefit_overrides' => 'nullable|array',
                ]);

                $planAddon = PlanAddon::updateOrCreate(
                    ['plan_id' => $planId, 'addon_id' => $validated['addon_id']],
                    [
                        'availability' => $validated['availability'],
                        'conditions' => $validated['conditions'] ?? null,
                        'benefit_overrides' => $validated['benefit_overrides'] ?? null,
                        'is_active' => true,
                    ]
                );

                return $planAddon->load('addon');
            });

            return $this->success(
                new PlanAddonResource($planAddon),
                'Plan addon configured'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to configure plan addon', 500);
        }
    }

    /**
     * Update plan addon configuration.
     * PUT /v1/medical/plan-addons/{id}
     */
    public function updatePlanAddon(string $id): JsonResponse
    {
        try {
            $planAddon = DB::transaction(function () use ($id) {
                $planAddon = PlanAddon::findOrFail($id);

                $validated = request()->validate([
                    'availability' => 'sometimes|string|in:optional,mandatory,included,conditional',
                    'conditions' => 'nullable|array',
                    'benefit_overrides' => 'nullable|array',
                    'is_active' => 'nullable|boolean',
                ]);

                $planAddon->update($validated);
                return $planAddon->fresh('addon');
            });

            return $this->success(
                new PlanAddonResource($planAddon),
                'Plan addon updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update plan addon', 500);
        }
    }

    /**
     * Remove addon from plan.
     * DELETE /v1/medical/plan-addons/{id}
     */
    public function removePlanAddon(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $planAddon = PlanAddon::findOrFail($id);
                $planAddon->delete();
            });

            return $this->success(null, 'Addon removed from plan');
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to remove plan addon', 500);
        }
    }

    /**
     * Get available addons for a plan (not yet configured).
     * GET /v1/medical/plans/{planId}/available-addons
     */
    public function availableAddons(string $planId): JsonResponse
    {
        try {
            $configuredAddonIds = PlanAddon::where('plan_id', $planId)->pluck('addon_id');

            $availableAddons = Addon::active()
                ->effective()
                ->whereNotIn('id', $configuredAddonIds)
                ->ordered()
                ->get();

            return $this->success(
                AddonResource::collection($availableAddons),
                'Available addons retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve available addons', 500);
        }
    }
}