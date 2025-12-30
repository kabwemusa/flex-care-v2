<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'currency' => $this->currency,
            'premium_frequency' => $this->premium_frequency,
            'premium_frequency_label' => $this->premium_frequency_label,
            'premium_basis' => $this->premium_basis,
            'premium_basis_label' => $this->premium_basis_label,
            'member_type_factors' => $this->member_type_factors,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_draft' => $this->is_draft,
            'is_approved' => $this->is_approved,
            'is_effective' => $this->is_effective,
            'is_tiered' => $this->is_tiered,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'notes' => $this->notes,
            'entries_count' => $this->whenCounted('entries'),
            'tiers_count' => $this->whenCounted('tiers'),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'entries' => RateCardEntryResource::collection($this->whenLoaded('entries')),
            'tiers' => RateCardTierResource::collection($this->whenLoaded('tiers')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}