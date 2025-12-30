<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanBenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'benefit_id' => $this->benefit_id,
            'parent_plan_benefit_id' => $this->parent_plan_benefit_id,
            'limit_type' => $this->effective_limit_type,
            'limit_frequency' => $this->effective_limit_frequency,
            'limit_basis' => $this->effective_limit_basis,
            'limit_amount' => $this->limit_amount,
            'limit_count' => $this->limit_count,
            'limit_days' => $this->limit_days,
            'per_claim_limit' => $this->per_claim_limit,
            'per_day_limit' => $this->per_day_limit,
            'max_claims_per_year' => $this->max_claims_per_year,
            'waiting_period_days' => $this->waiting_period_days,
            'cost_sharing' => $this->cost_sharing,
            'is_covered' => $this->is_covered,
            'is_visible' => $this->is_visible,
            'display_value' => $this->formatted_display_value,
            'notes' => $this->notes,
            'sort_order' => $this->sort_order,
            'is_sub_limit' => $this->is_sub_limit,
            'has_sub_limits' => $this->has_sub_limits,
            'benefit' => new BenefitResource($this->whenLoaded('benefit')),
            'member_limits' => PlanBenefitLimitResource::collection($this->whenLoaded('memberLimits')),
            'child_plan_benefits' => PlanBenefitResource::collection($this->whenLoaded('childPlanBenefits')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}