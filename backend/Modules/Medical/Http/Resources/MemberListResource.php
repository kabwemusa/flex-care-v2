<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'policy_id' => $this->policy_id,
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'status' => $this->policy->status,
            ]),
            
            'member_type' => $this->member_type,
            'member_type_label' => $this->member_type_label,
            'is_principal' => $this->is_principal,
            
            'principal' => $this->whenLoaded('principal', fn() => [
                'id' => $this->principal->id,
                'full_name' => $this->principal->full_name,
            ]),
            
            'full_name' => $this->full_name,
            'short_name' => $this->short_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'gender' => $this->gender,
            
            'national_id' => $this->national_id,
            'employee_number' => $this->employee_number,
            'email' => $this->email,
            'mobile' => $this->mobile,
            
            'cover_start_date' => $this->cover_start_date?->toDateString(),
            'cover_end_date' => $this->cover_end_date?->toDateString(),
            'has_cover' => $this->has_cover,
            'is_in_waiting_period' => $this->is_in_waiting_period,
            
            'card_number' => $this->card_number,
            'card_status' => $this->card_status,
            
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_active' => $this->is_active,
            
            'premium' => (float) $this->premium,
            'loading_amount' => (float) $this->loading_amount,
            
            'created_at' => $this->created_at,
        ];
    }
}