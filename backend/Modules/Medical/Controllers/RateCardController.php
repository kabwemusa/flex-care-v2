<?php
namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Http\Requests\RateCardRequest;
use Exception; 
use Illuminate\Support\Facades\DB;

class RateCardController extends Controller
{
    use ApiResponse;

    /**
     * List all rate cards with their parent plan name
     */
    public function index(): JsonResponse
    {
        try {
            $cards = RateCard::with('plan:id,name')
                ->withCount('entries')
                ->latest()
                ->get();
            return $this->success($cards, 'Rate cards retrieved');
        } catch (Exception $e) {
            return $this->error('Failed to load rate cards', 500, $e->getMessage());
        }
    }

    /**
     * Create a new rate card container
     */
    public function store(RateCardRequest $request): JsonResponse
    {
        try {
            // If this card is set to active, deactivate others for the same plan
            if ($request->is_active) {
                RateCard::where('plan_id', $request->plan_id)->update(['is_active' => false]);
            }

            $card = RateCard::create($request->validated());
            return $this->success($card, 'Rate card created successfully', 201);
        } catch (Exception $e) {
            return $this->error('Creation failed', 500, $e->getMessage());
        }
    }

    /**
     * Show a single rate card with all its pricing entries
     */
    public function show($id): JsonResponse
    {
        try {
            $card = RateCard::with(['plan', 'entries'])->findOrFail($id);
            return $this->success($card, 'Rate card retrieved');
        } catch (Exception $e) {
            return $this->error('Rate card not found', 404);
        }
    }

    /**
     * Update the metadata (Name, Validity, Status)
     */
    public function update(RateCardRequest $request, $id): JsonResponse
    {
        try {
            $card = RateCard::findOrFail($id);
            
            if ($request->is_active && !$card->is_active) {
                RateCard::where('plan_id', $card->plan_id)->update(['is_active' => false]);
            }

            $card->update($request->validated());
            return $this->success($card, 'Rate card updated');
        } catch (Exception $e) {
            return $this->error('Update failed', 500, $e->getMessage());
        }
    }

    /**
     * Sync the Price Matrix (Entries)
     * This replaces all current entries with the new set
     */
    public function syncEntries(Request $request, $id): JsonResponse
    {
        try {
            $card = RateCard::findOrFail($id);
            
            $request->validate([
                'entries' => 'present|array',
                'entries.*.min_age' => 'required|integer|min:0',
                'entries.*.max_age' => 'required|integer|gte:entries.*.min_age',
                'entries.*.member_type' => 'required|string',
                'entries.*.price' => 'required|numeric|min:0'
            ]);

            // Atomic transaction for entry replacement
            DB::transaction(function () use ($card, $request) {
                $card->entries()->delete();
                $card->entries()->createMany($request->input('entries'));
            });

            return $this->success(null, 'Price matrix synchronized successfully');
        } catch (Exception $e) {
            return $this->error('Failed to sync pricing', 500, $e->getMessage());
        }
    }

    /**
     * Delete a rate card and all its entries (via cascade)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $card = RateCard::findOrFail($id);
            $card->delete();
            return $this->success(null, 'Rate card deleted');
        } catch (Exception $e) {
            return $this->error('Deletion failed', 500, $e->getMessage());
        }
    }
}