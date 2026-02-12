<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for public-facing plan data.
 * Only exposes fields safe for unauthenticated users.
 */
class PublicPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tier_level' => $this->tier_level,
            'plan_type' => $this->plan_type,
            'plan_type_label' => $this->plan_type_label,
            'network_type' => $this->network_type,
            'network_type_label' => $this->network_type_label,
            'description' => $this->description,
            'highlights' => $this->highlights,

            // Limited member config - only what's needed for quote form
            'max_dependents' => $this->max_dependents,
            'allowed_member_types' => $this->allowed_member_types,
            'child_age_limit' => $this->child_age_limit,

            // Counts only, not full relations
            'plan_benefits_count' => $this->whenCounted('planBenefits'),
            'plan_addons_count' => $this->whenCounted('planAddons'),

            // Limited scheme info
            'scheme' => $this->when(
                $this->relationLoaded('scheme'),
                fn() => [
                    'id' => $this->scheme->id,
                    'name' => $this->scheme->name,
                    'code' => $this->scheme->code,
                ]
            ),

            // Public benefits (limited fields)
            'benefits' => $this->when(
                $this->relationLoaded('planBenefits'),
                fn() => $this->planBenefits->map(fn($pb) => [
                    'name' => $pb->benefit?->name,
                    'category' => $pb->benefit?->category?->name,
                    'is_covered' => $pb->is_covered,
                    'limit_display' => $pb->formatted_display_value,
                ])
            ),

            // Public addons (with availability info for mandatory/optional distinction)
            'addons' => $this->when(
                $this->relationLoaded('planAddons'),
                fn() => $this->planAddons
                    ->filter(fn($pa) => $pa->is_active && $pa->addon && $pa->addon->is_active)
                    ->map(fn($pa) => [
                        'id' => $pa->addon->id,
                        'name' => $pa->addon->name,
                        'description' => $pa->addon->description,
                        'availability' => $pa->availability,
                        'availability_label' => $pa->availability_label,
                        'pricing_type' => $pa->addon->pricing_type,
                        'amount' => \in_array($pa->addon->pricing_type, ['fixed', 'per_member'], true)
                            ? $pa->addon->amount : null,
                        'percentage' => $pa->addon->pricing_type === 'percentage'
                            ? $pa->addon->percentage : null,
                    ])
                    ->values()
            ),
        ];
    }
}
