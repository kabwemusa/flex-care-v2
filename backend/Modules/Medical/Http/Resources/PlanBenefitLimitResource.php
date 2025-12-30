<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanBenefitLimitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_benefit_id' => $this->plan_benefit_id,
            'member_type' => $this->member_type,
            'member_type_label' => $this->member_type_label,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'age_band_label' => $this->age_band_label,
            'limit_amount' => $this->limit_amount,
            'limit_count' => $this->limit_count,
            'limit_days' => $this->limit_days,
            'display_value' => $this->formatted_display_value,
        ];
    }
}