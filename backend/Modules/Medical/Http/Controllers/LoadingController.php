<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\LoadingRule;
use Modules\Medical\Http\Requests\LoadingRuleRequest;
use Modules\Medical\Http\Resources\LoadingRuleResource;
use Modules\Medical\Services\LoadingService;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class LoadingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoadingService $loadingService
    ) {}

    /**
     * List loading rules.
     * GET /v1/medical/loading-rules
     */
    public function index(): JsonResponse
    {
        try {
            $query = LoadingRule::query();

            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('condition_name', 'like', "%{$search}%")
                      ->orWhere('icd10_code', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($category = request('condition_category')) {
                $query->where('condition_category', $category);
            }

            if ($loadingType = request('loading_type')) {
                $query->where('loading_type', $loadingType);
            }

            if (request('active_only', true)) {
                $query->active();
            }

            $query->orderBy('condition_category')->orderBy('condition_name');

            $rules = $query->paginate(request('per_page', 50));

            return $this->success(
                LoadingRuleResource::collection($rules),
                'Loading rules retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve loading rules: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create loading rule.
     * POST /v1/medical/loading-rules
     */
    public function store(LoadingRuleRequest $request): JsonResponse
    {
        try {
            $rule = DB::transaction(function () use ($request) {
                return LoadingRule::create($request->validated());
            });

            return $this->success(
                new LoadingRuleResource($rule),
                'Loading rule created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create loading rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show loading rule.
     * GET /v1/medical/loading-rules/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $rule = LoadingRule::findOrFail($id);

            return $this->success(
                new LoadingRuleResource($rule),
                'Loading rule retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Loading rule not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve loading rule', 500);
        }
    }

    /**
     * Update loading rule.
     * PUT /v1/medical/loading-rules/{id}
     */
    public function update(LoadingRuleRequest $request, string $id): JsonResponse
    {
        try {
            $rule = DB::transaction(function () use ($request, $id) {
                $rule = LoadingRule::findOrFail($id);
                $rule->update($request->validated());
                return $rule->fresh();
            });

            return $this->success(
                new LoadingRuleResource($rule),
                'Loading rule updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Loading rule not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update loading rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete loading rule.
     * DELETE /v1/medical/loading-rules/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $rule = LoadingRule::findOrFail($id);
                $rule->delete();
            });

            return $this->success(null, 'Loading rule deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Loading rule not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to delete loading rule', 500);
        }
    }

    /**
     * Search loading rules by ICD code or condition name.
     * GET /v1/medical/loading-rules/search
     */
    public function search(): JsonResponse
    {
        try {
            $term = request('q', '');

            if (strlen($term) < 2) {
                return $this->error('Search term must be at least 2 characters', 422);
            }

            $results = $this->loadingService->searchRules($term);

            return $this->success($results, 'Search results');
        } catch (Throwable $e) {
            return $this->error('Search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get loading options for a condition.
     * GET /v1/medical/loading-rules/options/{identifier}
     */
    public function options(string $identifier): JsonResponse
    {
        try {
            $options = $this->loadingService->getLoadingOptions($identifier);

            if (!$options['found']) {
                return $this->error($options['message'], 404);
            }

            return $this->success($options, 'Loading options retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve loading options', 500);
        }
    }

    /**
     * Calculate loadings for conditions.
     * POST /v1/medical/loadings/calculate
     */
    public function calculate(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'premium' => 'required|numeric|min:0',
                'conditions' => 'required|array|min:1',
                'conditions.*' => 'string',
                'cover_start_date' => 'nullable|date',
            ]);

            $result = $this->loadingService->calculateLoadings(
                $validated['premium'],
                $validated['conditions'],
                isset($validated['cover_start_date']) 
                    ? \Carbon\Carbon::parse($validated['cover_start_date']) 
                    : null
            );

            return $this->success($result, 'Loadings calculated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let Laravel handle standard validation errors (422) or catch here if custom format needed
            throw $e;
        } catch (Throwable $e) {
            return $this->error('Calculation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get loading categories for dropdown.
     * GET /v1/medical/loading-rules/categories
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = LoadingRule::select('condition_category')
                ->distinct()
                ->pluck('condition_category')
                ->map(fn($cat) => [
                    'value' => $cat,
                    'label' => ucfirst(str_replace('_', ' ', $cat)),
                ]);

            return $this->success($categories, 'Categories retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve categories', 500);
        }
    }

    /**
     * Get loading rules by category.
     * GET /v1/medical/loading-rules/by-category/{category}
     */
    public function byCategory(string $category): JsonResponse
    {
        try {
            $rules = LoadingRule::active()
                ->where('condition_category', $category)
                ->orderBy('condition_name')
                ->get();

            return $this->success(
                LoadingRuleResource::collection($rules),
                'Loading rules retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve rules by category', 500);
        }
    }
}