<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Plan;
use Modules\Medical\Http\Resources\PublicPlanResource;
use App\Traits\ApiResponse;
use Throwable;

/**
 * Controller for public-facing plan endpoints.
 * All methods enforce active + visible filtering for security.
 */
class PublicPlanController extends Controller
{
    use ApiResponse;

    /**
     * List publicly available plans.
     * GET /v1/medical/public/plans
     *
     * Only returns plans that are:
     * - is_active = true
     * - is_visible = true
     * - Currently effective (within effective_from/effective_to dates)
     */
    public function index(): JsonResponse
    {
        try {
            $query = Plan::query()
                ->where('is_active', true)
                ->where('is_visible', true)
                ->effective()  // Within effective date range
                ->with('scheme')
                ->withCount(['planBenefits', 'planAddons']);

            // Safe search - only on public fields
            if ($search = request('search')) {
                $search = $this->sanitizeSearch($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by plan type
            if ($planType = request('plan_type')) {
                $allowedTypes = ['individual', 'family', 'corporate', 'sme', 'group'];
                if (in_array($planType, $allowedTypes)) {
                    $query->where('plan_type', $planType);
                }
            }

            // Filter by tier level
            if ($tierLevel = request('tier_level')) {
                $query->where('tier_level', (int) $tierLevel);
            }

            $query->ordered();

            $perPage = min((int) request('per_page', 20), 50); // Cap at 50
            $plans = $query->paginate($perPage);

            return $this->success(
                PublicPlanResource::collection($plans),
                'Plans retrieved successfully'
            );
        } catch (Throwable $e) {
            // Don't expose internal errors to public
            return $this->error('Unable to retrieve plans', 500);
        }
    }

    /**
     * Show public plan details.
     * GET /v1/medical/public/plans/{id}
     *
     * Only returns plan if active and visible.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $plan = Plan::query()
                ->where('is_active', true)
                ->where('is_visible', true)
                ->effective()
                ->with([
                    'scheme',
                    'planBenefits.benefit',
                    'planAddons.addon',
                ])
                ->withCount(['planBenefits', 'planAddons'])
                ->findOrFail($id);

            return $this->success(
                new PublicPlanResource($plan),
                'Plan retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            // Generic message - don't reveal if plan exists but is inactive
            return $this->error('Plan not found', 404);
        } catch (Throwable $e) {
            return $this->error('Unable to retrieve plan details', 500);
        }
    }

    /**
     * Compare multiple public plans.
     * POST /v1/medical/public/plans/compare
     */
    public function compare(): JsonResponse
    {
        try {
            $planIds = request('plan_ids', []);

            if (!is_array($planIds) || count($planIds) < 2 || count($planIds) > 5) {
                return $this->error('Select 2 to 5 plans to compare', 422);
            }

            // Only fetch active, visible plans
            $plans = Plan::query()
                ->whereIn('id', $planIds)
                ->where('is_active', true)
                ->where('is_visible', true)
                ->effective()
                ->with(['planBenefits.benefit', 'planAddons.addon'])
                ->get();

            if ($plans->count() < 2) {
                return $this->error('Not enough valid plans to compare', 422);
            }

            // Build comparison matrix with limited data
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
                'plans' => PublicPlanResource::collection($plans),
                'comparison' => $comparison,
            ], 'Comparison generated');
        } catch (Throwable $e) {
            return $this->error('Unable to generate comparison', 500);
        }
    }

    /**
     * Sanitize search input to prevent regex-based DoS.
     */
    private function sanitizeSearch(string $search): string
    {
        // Remove potentially dangerous characters, limit length
        $search = preg_replace('/[%_\\\\]/', '', $search);
        return mb_substr(trim($search), 0, 100);
    }
}
