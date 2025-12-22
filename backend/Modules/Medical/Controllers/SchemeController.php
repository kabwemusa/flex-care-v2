<?php

namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse; // <--- Import the Trait
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Modules\Medical\Models\Scheme;
use Modules\Medical\Http\Requests\SchemeRequest;

class SchemeController extends Controller
{
    use ApiResponse; // <--- Activate the Trait

    public function index(): JsonResponse
    {
        try {
            $schemes = Scheme::latest()->get();
            return $this->success($schemes, 'Schemes retrieved successfully');
        } catch (Exception $e) {
            return $this->error('Failed to retrieve schemes', 500, $e->getMessage());
        }
    }

    public function store(SchemeRequest $request): JsonResponse
    {
        try {
            // Logic is clean: Create and Return
            $scheme = Scheme::create($request->validated());
            return $this->success($scheme, 'Scheme created successfully', 201);
            
        } catch (Exception $e) {
            // Catch unexpected database errors
            return $this->error('Failed to create scheme', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $scheme = Scheme::findOrFail($id);
            return $this->success($scheme, 'Scheme details retrieved');
            
        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Exception $e) {
            return $this->error('Something went wrong', 500, $e->getMessage());
        }
    }

    public function update(SchemeRequest $request, $id): JsonResponse
    {
        try {
            $scheme = Scheme::findOrFail($id);
            $scheme->update($request->validated());
            return $this->success($scheme, 'Scheme updated successfully');

        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to update scheme', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $scheme = Scheme::findOrFail($id);
            
            // Optional: Check logic before delete
            // if ($scheme->plans()->exists()) {
            //     return $this->error('Cannot delete scheme with active plans', 409);
            // }

            $scheme->delete();
            return $this->success(null, 'Scheme deleted successfully');

        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to delete scheme', 500, $e->getMessage());
        }
    }
}