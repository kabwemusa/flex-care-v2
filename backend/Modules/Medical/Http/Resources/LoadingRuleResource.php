<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoadingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'condition_name' => $this->condition_name,
            'condition_category' => $this->condition_category,
            'condition_category_label' => $this->condition_category_label,
            'icd10_code' => $this->icd10_code,
            'related_icd_codes' => $this->related_icd_codes,
            'loading_type' => $this->loading_type,
            'loading_type_label' => $this->loading_type_label,
            'loading_value' => $this->loading_value,
            'formatted_loading_value' => $this->formatted_loading_value,
            'duration_type' => $this->duration_type,
            'duration_type_label' => $this->duration_type_label,
            'duration_months' => $this->duration_months,
            'duration_label' => $this->duration_label,
            'is_permanent' => $this->is_permanent,
            'is_time_limited' => $this->is_time_limited,
            'is_reviewable' => $this->is_reviewable,
            'exclusion_available' => $this->exclusion_available,
            'exclusion_terms' => $this->exclusion_terms,
            'exclusion_benefit_id' => $this->exclusion_benefit_id,
            'required_documents' => $this->required_documents,
            'underwriting_notes' => $this->underwriting_notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}