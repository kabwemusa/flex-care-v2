<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Addon;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Http\Resources\PublicAddonResource;
use App\Traits\ApiResponse;
use Throwable;

/**
 * Controller for public-facing addon endpoints.
 * All methods enforce active filtering for security.
 */
class PublicAddonController extends Controller
{
    use ApiResponse;

    /**
     * List publicly available addons.
     * GET /v1/medical/public/addons
     *
     * Only returns addons that are:
     * - is_active = true
     * - Currently effective (within effective_from/effective_to dates)
     */
    public function index(): JsonResponse
    {
        try {
            $query = Addon::query()
                ->where('is_active', true)
                ->effective();  // Within effective date range

            // Safe search - only on public fields
            if ($search = request('search')) {
                $search = $this->sanitizeSearch($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by addon type
            if ($addonType = request('addon_type')) {
                $allowedTypes = ['benefit', 'rider', 'service'];
                if (in_array($addonType, $allowedTypes)) {
                    $query->where('addon_type', $addonType);
                }
            }

            $query->ordered();

            $perPage = min((int) request('per_page', 20), 50); // Cap at 50
            $addons = $query->paginate($perPage);

            return $this->success(
                PublicAddonResource::collection($addons),
                'Addons retrieved successfully'
            );
        } catch (Throwable $e) {
            // Don't expose internal errors to public
            return $this->error('Unable to retrieve addons', 500);
        }
    }

    /**
     * Show public addon details.
     * GET /v1/medical/public/addons/{id}
     *
     * Only returns addon if active and effective.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $addon = Addon::query()
                ->where('is_active', true)
                ->effective()
                ->findOrFail($id);

            return $this->success(
                new PublicAddonResource($addon),
                'Addon retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            // Generic message - don't reveal if addon exists but is inactive
            return $this->error('Addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Unable to retrieve addon details', 500);
        }
    }

    /**
     * List addons available for a specific plan, with availability info.
     * GET /v1/medical/public/plans/{planId}/addons
     *
     * Returns plan-specific addons with their availability (optional/mandatory/included).
     * Only returns addons that are active and effective.
     */
    public function forPlan(string $planId): JsonResponse
    {
        try {
            $plan = Plan::where('is_active', true)
                ->where('is_visible', true)
                ->effective()
                ->find($planId);

            if (!$plan) {
                return $this->error('Plan not found', 404);
            }

            $planAddons = PlanAddon::where('plan_id', $planId)
                ->where('is_active', true)
                ->with(['addon' => function ($q) {
                    $q->where('is_active', true)->effective();
                }])
                ->ordered()
                ->get()
                ->filter(fn ($pa) => $pa->addon !== null)
                ->values();

            $data = $planAddons->map(fn (PlanAddon $pa) => [
                'id' => $pa->addon->id,
                'code' => $pa->addon->code,
                'name' => $pa->addon->name,
                'description' => $pa->addon->description,
                'availability' => $pa->availability,
                'availability_label' => $pa->availability_label,
                'pricing_type' => $pa->addon->pricing_type,
                'pricing_type_label' => $pa->addon->pricing_type_label,
                'currency' => $pa->addon->currency,
                'amount' => \in_array($pa->addon->pricing_type, ['fixed', 'per_member'], true)
                    ? $pa->addon->amount : null,
                'percentage' => $pa->addon->pricing_type === 'percentage'
                    ? $pa->addon->percentage : null,
            ]);

            return $this->success($data, 'Plan addons retrieved successfully');
        } catch (Throwable $e) {
            return $this->error('Unable to retrieve plan addons', 500);
        }
    }

    /**
     * Sanitize search input to prevent regex-based DoS.
     */
    private function sanitizeSearch(string $search): string
    {
        $search = preg_replace('/[%_\\\\]/', '', $search);
        return mb_substr(trim($search), 0, 100);
    }
}
