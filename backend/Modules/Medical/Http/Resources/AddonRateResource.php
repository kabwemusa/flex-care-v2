<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addon_id' => $this->addon_id,
            'plan_id' => $this->plan_id,
            'pricing_type' => $this->pricing_type,
            'pricing_type_label' => $this->pricing_type_label,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'percentage_basis' => $this->percentage_basis,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_effective' => $this->is_effective,
            'is_global' => $this->is_global,
            'is_plan_specific' => $this->is_plan_specific,
        ];
    }
}