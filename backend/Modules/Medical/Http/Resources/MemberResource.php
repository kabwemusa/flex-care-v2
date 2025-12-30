<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
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
            
            // Type
            'member_type' => $this->member_type,
            'member_type_label' => $this->member_type_label,
            'is_principal' => $this->is_principal,
            'is_dependent' => $this->is_dependent,
            
            // Principal/Relationship
            'principal_id' => $this->principal_id,
            'principal' => $this->whenLoaded('principal', fn() => [
                'id' => $this->principal->id,
                'member_number' => $this->principal->member_number,
                'full_name' => $this->principal->full_name,
            ]),
            'relationship' => $this->relationship,
            
            // Personal
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'short_name' => $this->short_name,
            'initials' => $this->initials,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'age_band' => $this->age_band,
            'gender' => $this->gender,
            'gender_label' => $this->gender_label,
            'marital_status' => $this->marital_status,
            
            // Identification
            'national_id' => $this->national_id,
            'passport_number' => $this->passport_number,
            'employee_number' => $this->employee_number,
            
            // Contact
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city' => $this->city,
            'region_code' => $this->region_code,
            
            // Employment
            'job_title' => $this->job_title,
            'department' => $this->department,
            'employment_date' => $this->employment_date?->toDateString(),
            'salary' => $this->salary,
            'salary_band' => $this->salary_band,
            
            // Coverage
            'cover_start_date' => $this->cover_start_date?->toDateString(),
            'cover_end_date' => $this->cover_end_date?->toDateString(),
            'has_cover' => $this->has_cover,
            'waiting_period_end_date' => $this->waiting_period_end_date?->toDateString(),
            'is_in_waiting_period' => $this->is_in_waiting_period,
            'waiting_days_remaining' => $this->waiting_days_remaining,
            
            // Premium
            'premium' => (float) $this->premium,
            'loading_amount' => (float) $this->loading_amount,
            'total_premium' => $this->total_premium,
            
            // Card
            'card_number' => $this->card_number,
            'card_issued_date' => $this->card_issued_date?->toDateString(),
            'card_expiry_date' => $this->card_expiry_date?->toDateString(),
            'card_status' => $this->card_status,
            'card_status_label' => $this->card_status_label,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_active' => $this->is_active,
            'is_pending' => $this->is_pending,
            'status_changed_at' => $this->status_changed_at?->toDateString(),
            'status_reason' => $this->status_reason,
            
            // Termination
            'terminated_at' => $this->terminated_at?->toDateString(),
            'termination_reason' => $this->termination_reason,
            'termination_notes' => $this->termination_notes,
            
            // Medical
            'has_pre_existing_conditions' => $this->has_pre_existing_conditions,
            'is_chronic_patient' => $this->is_chronic_patient,
            'requires_special_underwriting' => $this->requires_special_underwriting,
            'declared_conditions' => $this->declared_conditions,
            
            // Eligibility
            'can_make_claim' => $this->canMakeClaim(),
            
            // Counts
            'dependents_count' => $this->whenCounted('dependents'),
            'active_loadings_count' => $this->whenCounted('activeLoadings'),
            'active_exclusions_count' => $this->whenCounted('activeExclusions'),
            
            // Related
            'dependents' => MemberResource::collection($this->whenLoaded('dependents')),
            'loadings' => MemberLoadingResource::collection($this->whenLoaded('loadings')),
            'exclusions' => MemberExclusionResource::collection($this->whenLoaded('exclusions')),
            'documents' => MemberDocumentResource::collection($this->whenLoaded('documents')),
            
            // Portal
            'has_portal_access' => $this->has_portal_access,
            
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}