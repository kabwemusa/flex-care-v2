<?php
namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Medical\Models\DiscountCard;
use Modules\Medical\Http\Requests\DiscountCardRequest;
use Exception;

class DiscountCardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            $discounts = DiscountCard::with('plan:id,name')->latest()->get();
            return $this->success($discounts, 'Discount cards retrieved');
        } catch (Exception $e) {
            return $this->error('Failed to load discounts', 500, $e->getMessage());
        }
    }

    public function store(DiscountCardRequest $request): JsonResponse
    {
        try {
            $discount = DiscountCard::create($request->validated());
            return $this->success($discount, 'Discount card created successfully', 201);
        } catch (Exception $e) {
            return $this->error('Failed to create discount', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $discount = DiscountCard::with('plan')->findOrFail($id);
            return $this->success($discount, 'Discount card Retrieved');
        } catch (Exception $e) {
            return $this->error('Discount card not found', 404);
        }
    }

    public function update(DiscountCardRequest $request, $id): JsonResponse
    {
        try {
            $discount = DiscountCard::findOrFail($id);
            $discount->update($request->validated());
            return $this->success($discount, 'Discount updated successfully');
        } catch (Exception $e) {
            return $this->error('Update failed', 500, $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $discount = DiscountCard::findOrFail($id);
            $discount->delete();
            return $this->success(null, 'Discount card deleted');
        } catch (Exception $e) {
            return $this->error('Deletion failed', 500, $e->getMessage());
        }
    }
}