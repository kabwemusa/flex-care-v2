<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_rule_id' => $this->discount_rule_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'max_uses' => $this->max_uses,
            'current_uses' => $this->current_uses,
            'remaining_uses' => $this->remaining_uses,
            'has_max_uses' => $this->has_max_uses,
            'is_exhausted' => $this->is_exhausted,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'is_valid' => $this->is_valid,
            'is_expired' => $this->is_expired,
            'is_usable' => $this->is_usable,
            'days_until_expiry' => $this->days_until_expiry,
            'eligible_schemes' => $this->eligible_schemes,
            'eligible_plans' => $this->eligible_plans,
            'eligible_groups' => $this->eligible_groups,
            'is_active' => $this->is_active,
            'discount_rule' => new DiscountRuleResource($this->whenLoaded('discountRule')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}