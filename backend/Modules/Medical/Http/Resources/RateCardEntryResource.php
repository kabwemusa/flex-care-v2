<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateCardEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rate_card_id' => $this->rate_card_id,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'age_band_label' => $this->age_band_label,
            'gender' => $this->gender,
            'gender_label' => $this->gender_label,
            'region_code' => $this->region_code,
            'base_premium' => $this->base_premium,
            'formatted_premium' => $this->formatted_premium,
            'is_unisex' => $this->is_unisex,
            'is_national' => $this->is_national,
        ];
    }
}