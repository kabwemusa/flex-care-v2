<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'addon_type' => $this->addon_type,
            'addon_type_label' => $this->addon_type_label,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_effective' => $this->is_effective,
            'sort_order' => $this->sort_order,
            'addon_benefits_count' => $this->whenCounted('addonBenefits'),
            'plan_addons_count' => $this->whenCounted('planAddons'),
            'addon_benefits' => AddonBenefitResource::collection($this->whenLoaded('addonBenefits')),
            'rates' => AddonRateResource::collection($this->whenLoaded('rates')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}