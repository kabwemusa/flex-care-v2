<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for public-facing addon data.
 * Only exposes fields safe for unauthenticated users.
 */
class PublicAddonResource extends JsonResource
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
            'pricing_type' => $this->pricing_type,
            'pricing_type_label' => $this->pricing_type_label,
            'currency' => $this->currency,
            // Only show amount for fixed pricing, not internal percentage details
            'amount' => $this->pricing_type === 'fixed' ? $this->amount : null,
            'percentage' => $this->pricing_type === 'percentage' ? $this->percentage : null,
        ];
    }
}
