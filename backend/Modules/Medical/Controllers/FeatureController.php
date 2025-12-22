<?php
namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Modules\Medical\Models\Feature;
use Modules\Medical\Http\Requests\FeatureRequest;

class FeatureController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            $features = Feature::orderBy('category')->orderBy('name')->get();
            return $this->success($features, 'Benefit library retrieved successfully');
        } catch (Exception $e) {
            return $this->error('Failed to retrieve features', 500, $e->getMessage());
        }
    }

    public function store(FeatureRequest $request): JsonResponse
    {
        try {
            $feature = Feature::create($request->validated());
            return $this->success($feature, 'New benefit feature added to library', 201);
        } catch (Exception $e) {
            return $this->error('Failed to create feature', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $feature = Feature::findOrFail($id);
            return $this->success($feature, 'Feature details retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Feature not found', 404);
        }
    }

    public function update(FeatureRequest $request, $id): JsonResponse
    {
        try {
            $feature = Feature::findOrFail($id);
            $feature->update($request->validated());
            return $this->success($feature, 'Feature updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->error('Feature not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to update feature', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $feature = Feature::findOrFail($id);
            
            // Institutional Guard: Prevent deletion if already linked to plans
            // if ($feature->plans()->exists()) {
            //     return $this->error('Cannot delete: This feature is currently linked to active medical plans.', 422);
            // }

            $feature->delete();
            return $this->success(null, 'Feature removed from library');
        } catch (Exception $e) {
            return $this->error('Failed to delete feature', 500, $e->getMessage());
        }
    }
}