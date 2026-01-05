<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            
            // Type
            'member_type' => $this->member_type,
            'member_type_label' => $this->member_type_label,
            'relationship' => $this->relationship,
            'relationship_label' => $this->relationship_label,
            'is_principal' => $this->is_principal,
            'is_dependent' => $this->is_dependent,
            
            // Principal reference
            'principal_member_id' => $this->principal_member_id,
            'principal' => $this->whenLoaded('principal', fn() => [
                'id' => $this->principal->id,
                'name' => $this->principal->full_name,
            ]),
            
            // Personal info
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'short_name' => $this->short_name,
            'initials' => $this->initials,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age,
            'age_at_inception' => $this->age_at_inception,
            'age_band' => $this->age_band,
            'gender' => $this->gender,
            'gender_label' => $this->gender_label,
            'marital_status' => $this->marital_status,
            
            // Identification
            'national_id' => $this->national_id,
            'passport_number' => $this->passport_number,
            
            // Contact
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city' => $this->city,
            
            // Employment
            'employee_number' => $this->employee_number,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'employment_date' => $this->employment_date?->format('Y-m-d'),
            'salary' => $this->salary,
            'salary_band' => $this->salary_band,
            
            // Premium
            'base_premium' => (float) $this->base_premium,
            'loading_amount' => (float) $this->loading_amount,
            'total_premium' => (float) $this->total_premium,
            
            // Medical
            'has_pre_existing_conditions' => $this->has_pre_existing_conditions,
            'declared_conditions' => $this->declared_conditions,
            'medical_history_notes' => $this->medical_history_notes,
            
            // Underwriting
            'underwriting_status' => $this->underwriting_status,
            'underwriting_status_label' => $this->underwriting_status_label,
            'is_underwriting_pending' => $this->is_underwriting_pending,
            'is_underwriting_approved' => $this->is_underwriting_approved,
            'is_underwriting_declined' => $this->is_underwriting_declined,
            'has_terms' => $this->has_terms,
            'applied_loadings' => $this->applied_loadings,
            'applied_exclusions' => $this->applied_exclusions,
            'has_loadings' => $this->has_loadings,
            'has_exclusions' => $this->has_exclusions,
            'loadings_count' => $this->loadings_count,
            'exclusions_count' => $this->exclusions_count,
            'underwriting_notes' => $this->underwriting_notes,
            'underwritten_by' => $this->underwritten_by,
            'underwritten_at' => $this->underwritten_at?->toISOString(),
            
            // Conversion
            'converted_member_id' => $this->converted_member_id,
            'is_converted' => $this->is_converted,
            
            // Status
            'is_active' => $this->is_active,
            
            // Dependents count (for principals)
            'dependent_count' => $this->when($this->is_principal, $this->dependent_count),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}