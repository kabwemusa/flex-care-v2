<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'market_segment' => $this->market_segment,
            'market_segment_label' => $this->market_segment_label,
            'description' => $this->description,
            'eligibility_rules' => $this->eligibility_rules,
            'underwriting_rules' => $this->underwriting_rules,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_effective' => $this->is_effective,
            'plans_count' => $this->whenCounted('plans'),
            'plans' => PlanResource::collection($this->whenLoaded('plans')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}