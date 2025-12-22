<?php
namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Modules\Medical\Models\Plan;
use Modules\Medical\Http\Requests\PlanRequest;
use Modules\Medical\Http\Requests\SyncPlanAddonsRequest;
use Modules\Medical\Http\Requests\SyncPlanFeatureRequest;


class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            // Eager load scheme name and count features for the UI
            $plans = Plan::with('scheme:id,name')
                        //  ->withCount('features')
                         ->latest()
                         ->get();
            return $this->success($plans, 'Plans retrieved successfully');
        } catch (Exception $e) {
            return $this->error('Failed to retrieve plans', 500, $e->getMessage());
        }
    }

    public function store(PlanRequest $request): JsonResponse
    {
        try {
            $plan = Plan::create($request->validated());
            return $this->success($plan->load('scheme'), 'Plan created successfully', 201);
        } catch (Exception $e) {
            return $this->error('Failed to create plan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $plan = Plan::with(['scheme', 'features'])->findOrFail($id);
            return $this->success($plan, 'Plan details retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Exception $e) {
            return $this->error('Something went wrong', 500, $e->getMessage());
        }
    }

    public function update(PlanRequest $request, $id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);
            $plan->update($request->validated());
            return $this->success($plan->load('scheme'), 'Plan updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to update plan', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);
            
            // Industrial Best Practice: Prevent deletion if billing/claims exist
            // For now, we just delete
            $plan->delete();
            return $this->success(null, 'Plan deleted successfully');

        } catch (ModelNotFoundException $e) {
            return $this->error('Plan not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to delete plan', 500, $e->getMessage());
        }
    }

    // Inside PlanController.php

    public function getPlansByScheme($scheme_id): JsonResponse
    {
        try {
            $plans = Plan::where('scheme_id', $scheme_id)
                        // ->withCount('features')
                        ->get();
                        
            return $this->success($plans, "Plans for scheme #{$scheme_id} retrieved");
        } catch (Exception $e) {
            return $this->error('Failed to retrieve plans for this scheme', 500, $e->getMessage());
        }
    }

   // Modules/Medical/Controllers/PlanController.php

    public function syncFeatures(SyncPlanFeatureRequest $request, $planId): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($planId);

            // $request->validated() only returns data that passed the rules
            $data = $request->validated();

            // Perform the sync using the 'features' array
            $plan->features()->sync($data['features']);

            return $this->success(null, 'Benefit matrix updated successfully');
            
        } catch (Exception $e) {
            return $this->error('Failed to update benefits', 500, $e->getMessage());
        }
    }

    public function syncAddons(SyncPlanAddonsRequest $request, $id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);
    
            // sync() handles the many-to-many insertion/deletion in med_plan_addon
            $plan->addons()->sync($request->input('addon_ids'));
    
            return $this->success(
                null, 
                "Optional covers for {$plan->name} have been updated."
            );
        } catch (\Exception $e) {
            return $this->error('Failed to link add-ons', 500, $e->getMessage());
        }
    }
}