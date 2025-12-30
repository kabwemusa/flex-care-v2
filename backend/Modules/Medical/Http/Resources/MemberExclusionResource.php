<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberExclusionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'benefit_id' => $this->benefit_id,
            'benefit' => $this->whenLoaded('benefit', fn() => [
                'id' => $this->benefit->id,
                'code' => $this->benefit->code,
                'name' => $this->benefit->name,
            ]),
            
            'exclusion_type' => $this->exclusion_type,
            'exclusion_type_label' => $this->exclusion_type_label,
            'exclusion_name' => $this->exclusion_name,
            'description' => $this->description,
            'icd10_codes' => $this->icd10_codes,
            'icd_codes_array' => $this->icd_codes_array,
            
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'review_date' => $this->review_date?->toDateString(),
            
            'is_permanent' => $this->is_permanent,
            'is_time_limited' => $this->is_time_limited,
            'days_remaining' => $this->days_remaining,
            'duration_label' => $this->duration_label,
            
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