<?php
namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Modules\Medical\Models\Addon;
use Modules\Medical\Http\Requests\AddonRequest;

class AddonController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            $addons = Addon::latest()->get();
            return $this->success($addons, 'Medical add-ons retrieved successfully');
        } catch (Exception $e) {
            return $this->error('Failed to retrieve add-ons', 500, $e->getMessage());
        }
    }

    public function store(AddonRequest $request): JsonResponse
    {
        try {
            $addon = Addon::create($request->validated());
            return $this->success($addon, 'Add-on created successfully', 201);
        } catch (Exception $e) {
            return $this->error('Failed to create add-on', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);
            return $this->success($addon, 'Add-on details retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Add-on not found', 404);
        }
    }

    public function update(AddonRequest $request, $id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);
            $addon->update($request->validated());
            return $this->success($addon, 'Add-on updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->error('Add-on not found', 404);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);
            // Institutional Guard: Don't delete if linked to active policies
            // For now, simple delete
            $addon->delete();
            return $this->success(null, 'Add-on deleted successfully');
        } catch (Exception $e) {
            return $this->error('Failed to delete add-on', 500, $e->getMessage());
        }
    }
}