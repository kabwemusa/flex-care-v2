<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateCardTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rate_card_id' => $this->rate_card_id,
            'tier_name' => $this->tier_name,
            'tier_description' => $this->tier_description,
            'min_members' => $this->min_members,
            'max_members' => $this->max_members,
            'member_range_label' => $this->member_range_label,
            'tier_premium' => $this->tier_premium,
            'extra_member_premium' => $this->extra_member_premium,
            'formatted_premium' => $this->formatted_premium,
            'has_extra_member_premium' => $this->has_extra_member_premium,
            'sort_order' => $this->sort_order,
        ];
    }
}