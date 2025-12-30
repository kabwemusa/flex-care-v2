<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanAddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'addon_id' => $this->addon_id,
            'availability' => $this->availability,
            'availability_label' => $this->availability_label,
            'is_mandatory' => $this->is_mandatory,
            'is_optional' => $this->is_optional,
            'is_included' => $this->is_included,
            'is_conditional' => $this->is_conditional,
            'requires_additional_premium' => $this->requires_additional_premium,
            'conditions' => $this->conditions,
            'benefit_overrides' => $this->benefit_overrides,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'addon' => new AddonResource($this->whenLoaded('addon')),
        ];
    }
}