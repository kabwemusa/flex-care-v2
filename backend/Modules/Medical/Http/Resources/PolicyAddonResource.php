<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyAddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_id' => $this->policy_id,
            'addon_id' => $this->addon_id,
            'addon_rate_id' => $this->addon_rate_id,
            
            'addon' => $this->whenLoaded('addon', fn() => [
                'id' => $this->addon->id,
                'code' => $this->addon->code,
                'name' => $this->addon->name,
                'addon_type' => $this->addon->addon_type,
            ]),
            
            'premium' => (float) $this->premium,
            'is_active' => $this->is_active,
            'is_effective' => $this->is_effective,
            
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}