<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'benefit_type' => $this->benefit_type,
            'benefit_type_label' => $this->benefit_type_label,
            'default_limit_type' => $this->limit_type,
            'default_limit_frequency' => $this->limit_frequency,
            'default_limit_basis' => $this->limit_basis,
            'applicable_member_types' => $this->applicable_member_types,
            'requires_preauth' => $this->requires_preauth,
            'requires_referral' => $this->requires_referral,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_root' => $this->is_root,
            'has_children' => $this->has_children,
            'full_path' => $this->full_path,
            'category' => new BenefitCategoryResource($this->whenLoaded('category')),
            'parent' => new BenefitResource($this->whenLoaded('parent')),
            'children' => BenefitResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}