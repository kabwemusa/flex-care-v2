<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheme_id' => $this->scheme_id,
            'code' => $this->code,
            'name' => $this->name,
            'tier_level' => $this->tier_level,
            'plan_type' => $this->plan_type,
            'plan_type_label' => $this->plan_type_label,
            'network_type' => $this->network_type,
            'network_type_label' => $this->network_type_label,
            'member_config' => $this->member_config,
            'max_dependents' => $this->max_dependents,
            'allowed_member_types' => $this->allowed_member_types,
            'child_age_limit' => $this->child_age_limit,
            'default_waiting_periods' => $this->default_waiting_periods,
            'default_cost_sharing' => $this->default_cost_sharing,
            'description' => $this->description,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_visible' => $this->is_visible,
            'plan_benefits_count' => $this->whenCounted('planBenefits'),
            'plan_addons_count' => $this->whenCounted('planAddons'),
            'scheme' => new SchemeResource($this->whenLoaded('scheme')),
            'plan_benefits' => PlanBenefitResource::collection($this->whenLoaded('planBenefits')),
            'plan_addons' => PlanAddonResource::collection($this->whenLoaded('planAddons')),
            'rate_cards' => RateCardResource::collection($this->whenLoaded('rateCards')),
            'active_rate_card' => $this->when(
                $this->relationLoaded('rateCards'),
                fn() => new RateCardResource($this->active_rate_card)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}