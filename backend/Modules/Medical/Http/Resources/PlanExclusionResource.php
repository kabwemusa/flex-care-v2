<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanExclusionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'benefit_id' => $this->benefit_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'exclusion_type' => $this->exclusion_type,
            'exclusion_type_label' => $this->exclusion_type_label,
            'conditions' => $this->conditions,
            'exclusion_period_days' => $this->exclusion_period_days,
            'is_general' => $this->is_general,
            'is_benefit_specific' => $this->is_benefit_specific,
            'is_time_limited' => $this->is_time_limited,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'plan' => $this->whenLoaded('plan', function () {
                return [
                    'id' => $this->plan->id,
                    'code' => $this->plan->code,
                    'name' => $this->plan->name,
                ];
            }),
            'benefit' => $this->whenLoaded('benefit', function () {
                return $this->benefit ? [
                    'id' => $this->benefit->id,
                    'code' => $this->benefit->code,
                    'name' => $this->benefit->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
