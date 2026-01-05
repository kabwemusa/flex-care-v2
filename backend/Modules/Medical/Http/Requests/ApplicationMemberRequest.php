<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class ApplicationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::MEMBER_TYPES)),
            'principal_member_id' => 'nullable|uuid|exists:med_application_members,id',
            'relationship' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::RELATIONSHIPS)),
            
            'title' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'nullable|in:M,F',
            'marital_status' => 'nullable|string|max:20',
            
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            
            // Employment (for corporate)
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
            'declared_conditions.*.diagnosis_date' => 'nullable|date',
            'declared_conditions.*.treatment' => 'nullable|string',
            'medical_history_notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'member_type.required' => 'Member type is required',
            'member_type.in' => 'Invalid member type',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'principal_member_id.exists' => 'Principal member not found',
            'relationship.required_if' => 'Relationship is required for dependents',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $memberType = $this->input('member_type');
            
            // Dependents must have principal_member_id or relationship
            if ($memberType !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                if (!$this->input('principal_member_id') && !$this->input('relationship')) {
                    // This is acceptable during creation - will be linked to first principal
                }
            }
        });
    }
}