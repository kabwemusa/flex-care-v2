<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Models\RateCardEntry;
use Modules\Medical\Http\Requests\RateCardRequest;
use Modules\Medical\Http\Resources\RateCardResource;
use App\Traits\ApiResponse;
use Modules\Medical\Services\PremiumCalculatorService;
use Throwable;
use Exception;

class RateCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PremiumCalculatorService $PremiumCalculatorService
    ) {}

    /**
     * List rate cards.
     * GET /v1/medical/rate-cards
     */
    public function index(): JsonResponse
    {
        try {
            $query = RateCard::with('plan');

            if ($planId = request('plan_id')) {
                $query->where('plan_id', $planId);
            }

            if (request('active_only', false)) {
                $query->active();
            }

            $query->latest('effective_from');

            $rateCards = $query->paginate(request('per_page', 20));

            return $this->success(
                RateCardResource::collection($rateCards),
                'Rate cards retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve rate cards: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create rate card.
     * POST /v1/medical/rate-cards
     */
    public function store(RateCardRequest $request): JsonResponse
    {
        try {
            $rateCard = DB::transaction(function () use ($request) {
                $rateCard = RateCard::create($request->validated());

                // Add entries if provided
                if ($entries = $request->entries) {
                    foreach ($entries as $entry) {
                        $rateCard->entries()->create($entry);
                    }
                }

                // Add tiers if provided
                if ($tiers = $request->tiers) {
                    foreach ($tiers as $tier) {
                        $rateCard->tiers()->create($tier);
                    }
                }

                return $rateCard;
            });

            $rateCard->load(['plan', 'entries', 'tiers']);

            return $this->success(
                new RateCardResource($rateCard),
                'Rate card created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create rate card: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show rate card details.
     * GET /v1/medical/rate-cards/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $rateCard = RateCard::with(['plan', 'entries', 'tiers'])
                ->findOrFail($id);

            return $this->success(
                new RateCardResource($rateCard),
                'Rate card retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve rate card details', 500);
        }
    }

    /**
     * Update rate card.
     * PUT /v1/medical/rate-cards/{id}
     */
    public function update(RateCardRequest $request, string $id): JsonResponse
    {
        try {
            $rateCard = DB::transaction(function () use ($request, $id) {
                $rateCard = RateCard::findOrFail($id);

                if ($rateCard->is_active) {
                    throw new Exception('Cannot modify active rate card. Clone it instead.', 422);
                }

                $rateCard->update($request->validated());
                return $rateCard->fresh(['plan']);
            });

            return $this->success(
                new RateCardResource($rateCard),
                'Rate card updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Delete rate card.
     * DELETE /v1/medical/rate-cards/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $rateCard = RateCard::findOrFail($id);

                if ($rateCard->is_active) {
                    throw new Exception('Cannot delete active rate card', 422);
                }

                $rateCard->entries()->delete();
                $rateCard->tiers()->delete();
                $rateCard->delete();
            });

            return $this->success(null, 'Rate card deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate rate card.
     * POST /v1/medical/rate-cards/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $rateCard = DB::transaction(function () use ($id) {
                $rateCard = RateCard::findOrFail($id);

                if ($rateCard->entries()->count() === 0 && $rateCard->tiers()->count() === 0) {
                    throw new Exception('Rate card must have entries or tiers before activation', 422);
                }

                $rateCard->approve();
                $rateCard->activate();

                return $rateCard->fresh(['plan']);
            });

            return $this->success(
                new RateCardResource($rateCard),
                'Rate card activated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Clone rate card with optional premium adjustment.
     * POST /v1/medical/rate-cards/{id}/clone
     */
    public function clone(string $id): JsonResponse
    {
        try {
            $newRateCard = DB::transaction(function () use ($id) {
                $source = RateCard::with(['entries', 'tiers'])->findOrFail($id);
                $adjustment = request('premium_adjustment', 0); // percentage

                $newRateCard = $source->replicate(['id', 'code', 'created_at', 'updated_at']);
                $newRateCard->is_active = false;
                $newRateCard->is_draft = true;
                $newRateCard->approved_at = null;
                $newRateCard->approved_by = null;
                $newRateCard->version = $this->incrementVersion($source->version);
                $newRateCard->save();

                $multiplier = 1 + ($adjustment / 100);

                // Clone entries with adjustment
                foreach ($source->entries as $entry) {
                    $newEntry = $entry->replicate(['id', 'created_at', 'updated_at']);
                    $newEntry->rate_card_id = $newRateCard->id;
                    $newEntry->base_premium = round($entry->base_premium * $multiplier, 2);
                    $newEntry->save();
                }

                // Clone tiers with adjustment
                foreach ($source->tiers as $tier) {
                    $newTier = $tier->replicate(['id', 'created_at', 'updated_at']);
                    $newTier->rate_card_id = $newRateCard->id;
                    $newTier->tier_premium = round($tier->tier_premium * $multiplier, 2);
                    if ($tier->extra_member_premium) {
                        $newTier->extra_member_premium = round($tier->extra_member_premium * $multiplier, 2);
                    }
                    $newTier->save();
                }

                return $newRateCard->fresh(['plan', 'entries', 'tiers']);
            });

            return $this->success(
                new RateCardResource($newRateCard),
                'Rate card cloned',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Source rate card not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to clone rate card: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // RATE CARD ENTRIES
    // =========================================================================

    /**
     * Add entry to rate card.
     * POST /v1/medical/rate-cards/{id}/entries
     */
    public function addEntry(string $id): JsonResponse
    {
        try {
            $entry = DB::transaction(function () use ($id) {
                $rateCard = RateCard::findOrFail($id);

                if ($rateCard->is_active) {
                    throw new Exception('Cannot modify active rate card', 422);
                }

                $validated = request()->validate([
                    'min_age' => 'required|integer|min:0',
                    'max_age' => 'required|integer|min:0|gte:min_age',
                    'age_band_label' => 'nullable|string|max:50',
                    'gender' => 'nullable|string|in:M,F',
                    'region_code' => 'nullable|string|max:20',
                    'base_premium' => 'required|numeric|min:0',
                ]);

                return $rateCard->entries()->create($validated);
            });

            return $this->success($entry, 'Entry added', 201);
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Update rate card entry.
     * PUT /v1/medical/rate-card-entries/{id}
     */
    public function updateEntry(string $id): JsonResponse
    {
        try {
            $entry = DB::transaction(function () use ($id) {
                $entry = RateCardEntry::findOrFail($id);

                if ($entry->rateCard->is_active) {
                    throw new Exception('Cannot modify active rate card', 422);
                }

                $validated = request()->validate([
                    'min_age' => 'sometimes|integer|min:0',
                    'max_age' => 'sometimes|integer|min:0',
                    'base_premium' => 'sometimes|numeric|min:0',
                ]);

                $entry->update($validated);
                return $entry;
            });

            return $this->success($entry, 'Entry updated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Entry not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Delete rate card entry.
     * DELETE /v1/medical/rate-card-entries/{id}
     */
    public function deleteEntry(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $entry = RateCardEntry::findOrFail($id);

                if ($entry->rateCard->is_active) {
                    throw new Exception('Cannot modify active rate card', 422);
                }

                $entry->delete();
            });

            return $this->success(null, 'Entry deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Entry not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Bulk import entries.
     * POST /v1/medical/rate-cards/{id}/entries/bulk
     */
    public function bulkImportEntries(string $id): JsonResponse
    {
        try {
            $count = DB::transaction(function () use ($id) {
                $rateCard = RateCard::findOrFail($id);

                if ($rateCard->is_active) {
                    throw new Exception('Cannot modify active rate card', 422);
                }

                $validated = request()->validate([
                    'entries' => 'required|array|min:1',
                    'entries.*.min_age' => 'required|integer|min:0',
                    'entries.*.max_age' => 'required|integer|min:0',
                    'entries.*.base_premium' => 'required|numeric|min:0',
                    'entries.*.gender' => 'nullable|string|in:M,F',
                    'entries.*.region_code' => 'nullable|string',
                    'replace_existing' => 'nullable|boolean',
                ]);

                if ($validated['replace_existing'] ?? false) {
                    $rateCard->entries()->delete();
                }

                foreach ($validated['entries'] as $entry) {
                    $rateCard->entries()->create($entry);
                }

                return count($validated['entries']);
            });

            return $this->success(
                ['imported_count' => $count],
                'Entries imported'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // PREMIUM CALCULATION
    // =========================================================================

    /**
     * Calculate premium using rate card.
     * POST /v1/medical/rate-cards/{id}/calculate
     */
    public function calculate(string $id): JsonResponse
    {
        try {
            $rateCard = RateCard::findOrFail($id);

            $validated = request()->validate([
                'members' => 'required|array|min:1',
                'members.*.age' => 'required|integer|min:0|max:100',
                'members.*.member_type' => 'required|string',
                'members.*.gender' => 'nullable|string|in:M,F',
                'addon_ids' => 'nullable|array',
            ]);

            $result = $this->PremiumCalculatorService->calculateTotalPremium(
                $rateCard,
                $validated['members'],
                $validated['addon_ids'] ?? []
            );

            if (!$result['success']) {
                return $this->error($result['message'], 422);
            }

            return $this->success($result, 'Premium calculated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Rate card not found', 404);
        } catch (Throwable $e) {
            return $this->error('Calculation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get rate cards for a plan.
     * GET /v1/medical/plans/{planId}/rate-cards
     */
    public function byPlan(string $planId): JsonResponse
    {
        try {
            $rateCards = RateCard::where('plan_id', $planId)
                ->withCount(['entries', 'tiers'])
                ->latest('effective_from')
                ->get();

            return $this->success(
                RateCardResource::collection($rateCards),
                'Rate cards retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve rate cards', 500);
        }
    }

    protected function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        $minor = (int) ($parts[1] ?? 0);
        return $parts[0] . '.' . ($minor + 1);
    }
}