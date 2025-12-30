<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'loading_rule_id' => $this->loading_rule_id,
            'loading_rule' => $this->whenLoaded('loadingRule', fn() => [
                'id' => $this->loadingRule->id,
                'code' => $this->loadingRule->code,
                'condition_name' => $this->loadingRule->condition_name,
            ]),
            
            'condition_name' => $this->condition_name,
            'icd10_code' => $this->icd10_code,
            
            'loading_type' => $this->loading_type,
            'loading_type_label' => $this->loading_type_label,
            'loading_value' => (float) $this->loading_value,
            'loading_amount' => (float) $this->loading_amount,
            'formatted_loading' => $this->formatted_loading,
            
            'duration_type' => $this->duration_type,
            'duration_type_label' => $this->duration_type_label,
            'duration_months' => $this->duration_months,
            'is_permanent' => $this->is_permanent,
            'is_time_limited' => $this->is_time_limited,
            'is_reviewable' => $this->is_reviewable,
            
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'review_date' => $this->review_date?->toDateString(),
            'days_remaining' => $this->days_remaining,
            
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_expired' => $this->is_expired,
            
            'underwriting_notes' => $this->underwriting_notes,
            'applied_by' => $this->applied_by,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}