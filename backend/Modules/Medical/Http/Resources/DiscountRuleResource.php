<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheme_id' => $this->scheme_id,
            'plan_id' => $this->plan_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'adjustment_type' => $this->adjustment_type,
            'adjustment_type_label' => $this->adjustment_type_label,
            'value_type' => $this->value_type,
            'value' => $this->value,
            'formatted_value' => $this->formatted_value,
            'applies_to' => $this->applies_to,
            'applies_to_label' => $this->applies_to_label,
            'application_method' => $this->application_method,
            'application_method_label' => $this->application_method_label,
            'trigger_rules' => $this->trigger_rules,
            'can_stack' => $this->can_stack,
            'max_total_discount' => $this->max_total_discount,
            'priority' => $this->priority,
            'max_uses' => $this->max_uses,
            'current_uses' => $this->current_uses,
            'has_usage_limit' => $this->has_usage_limit,
            'is_usage_limit_reached' => $this->is_usage_limit_reached,
            'terms_conditions' => $this->terms_conditions,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_discount' => $this->is_discount,
            'is_loading' => $this->is_loading,
            'is_automatic' => $this->is_automatic,
            'is_global' => $this->is_global,
            'scheme' => $this->whenLoaded('scheme', fn() => [
                'id' => $this->scheme->id,
                'name' => $this->scheme->name,
            ]),
            'plan' => $this->whenLoaded('plan', fn() => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
            ]),
            'promo_codes_count' => $this->whenCounted('promoCodes'),
            'promo_codes' => PromoCodeResource::collection($this->whenLoaded('promoCodes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}