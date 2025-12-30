<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonBenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addon_id' => $this->addon_id,
            'benefit_id' => $this->benefit_id,
            'limit_type' => $this->effective_limit_type,
            'limit_frequency' => $this->effective_limit_frequency,
            'limit_basis' => $this->effective_limit_basis,
            'limit_amount' => $this->limit_amount,
            'limit_count' => $this->limit_count,
            'limit_days' => $this->limit_days,
            'waiting_period_days' => $this->waiting_period_days,
            'display_value' => $this->formatted_display_value,
            'benefit' => new BenefitResource($this->whenLoaded('benefit')),
        ];
    }
}