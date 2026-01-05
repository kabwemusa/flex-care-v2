<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Scheme;
use Modules\Medical\Http\Requests\SchemeRequest;
use Modules\Medical\Http\Resources\SchemeResource;
use App\Traits\ApiResponse;
use Throwable;

class SchemeController extends Controller
{
    use ApiResponse;

    /**
     * List all schemes with search & pagination.
     * GET /v1/medical/schemes
     */
    public function index(): JsonResponse
    {
        try {
            $query = Scheme::query()->withCount('plans');

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($segment = request('market_segment')) {
                $query->where('market_segment', $segment);
            }

            if (request('active_only', false)) {
                $query->active()->effective();
            }

            $query->orderBy('name');

            $schemes = $query->paginate(request('per_page', 20));

            return $this->success(
                SchemeResource::collection($schemes),
                'Schemes retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve schemes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new scheme.
     * POST /v1/medical/schemes
     */
    public function store(SchemeRequest $request): JsonResponse
    {
        try {
            // DB Transaction ensures atomicity
            $scheme = DB::transaction(function () use ($request) {
                return Scheme::create($request->validated());
            });

            return $this->success(
                new SchemeResource($scheme),
                'Scheme created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create scheme: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show scheme details.
     * GET /v1/medical/schemes/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $scheme = Scheme::with(['plans' => fn($q) => $q->ordered()])
                ->withCount('plans')
                ->findOrFail($id);

            return $this->success(
                new SchemeResource($scheme),
                'Scheme retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve scheme details', 500);
        }
    }

    /**
     * Update a scheme.
     * PUT /v1/medical/schemes/{id}
     */
    public function update(SchemeRequest $request, string $id): JsonResponse
    {
        try {
            $scheme = DB::transaction(function () use ($request, $id) {
                $scheme = Scheme::findOrFail($id);
                $scheme->update($request->validated());
                return $scheme->fresh();
            });

            return $this->success(
                new SchemeResource($scheme),
                'Scheme updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update scheme: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a scheme.
     * DELETE /v1/medical/schemes/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $scheme = Scheme::withCount('plans')->findOrFail($id);

                // Business logic validation inside transaction
                if ($scheme->plans_count > 0) {
                    // Throwing exception triggers rollback and is caught below
                    throw new \Exception('Cannot delete scheme with existing plans. Remove plans first.', 422);
                }

                $scheme->delete();

                return $this->success(null, 'Scheme deleted successfully');
            });

        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Throwable $e) {
            // Check if it's our custom validation error (422) or a server error
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate/deactivate a scheme.
     * POST /v1/medical/schemes/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $scheme = Scheme::findOrFail($id);

            // Toggle the is_active status
            $scheme->is_active = !$scheme->is_active;
            $scheme->save();

            $action = $scheme->is_active ? 'activated' : 'deactivated';

            return $this->success(
                new SchemeResource($scheme),
                "Scheme {$action} successfully"
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Scheme not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update scheme status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get schemes for dropdown/select.
     * GET /v1/medical/schemes/dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $schemes = Scheme::active()
                ->effective()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'market_segment']);

            return $this->success($schemes, 'Schemes retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve dropdown data', 500);
        }
    }
}