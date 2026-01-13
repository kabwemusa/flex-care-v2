<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class AddMemberToPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/policies
    }

    public function rules(): array
    {
        return [
            // Effective date for mid-term addition
            'effective_date' => 'required|date|after_or_equal:today',

            // Member type and relationship
            'member_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::MEMBER_TYPES)),
            'principal_member_id' => 'nullable|uuid|exists:med_members,id',
            'relationship' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::RELATIONSHIPS)),

            // Personal information
            'title' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:M,F',
            'marital_status' => 'nullable|string|max:20',

            // Identification
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',

            // Contact information
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',

            // Employment (for corporate policies)
            'employee_number' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'employment_date' => 'nullable|date|before_or_equal:today',
            'salary' => 'nullable|numeric|min:0',
            'salary_band' => 'nullable|string|max:50',

            // Medical declaration
            'has_pre_existing_conditions' => 'nullable|boolean',
            'declared_conditions' => 'nullable|array',
            'declared_conditions.*.name' => 'required_with:declared_conditions|string',
            'declared_conditions.*.icd10_code' => 'nullable|string',
            'declared_conditions.*.diagnosed_date' => 'nullable|date',
            'declared_conditions.*.treatment_status' => 'nullable|string',

            'medical_history_notes' => 'nullable|string|max:2000',

            // Emergency contact
            'emergency_contact_name' => 'nullable|string|max:200',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relationship' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'effective_date.required' => 'Effective date is required for mid-term additions',
            'effective_date.after_or_equal' => 'Effective date cannot be in the past',
            'member_type.required' => 'Member type is required',
            'member_type.in' => 'Invalid member type selected',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be M or F',
            'principal_member_id.exists' => 'The selected principal member does not exist in this policy',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'effective_date' => 'effective date',
            'member_type' => 'member type',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'date_of_birth' => 'date of birth',
            'national_id' => 'national ID',
            'employee_number' => 'employee number',
            'has_pre_existing_conditions' => 'pre-existing conditions flag',
        ];
    }
}
