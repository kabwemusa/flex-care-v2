<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('id');
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'policy_id' => ($isUpdate ? 'nullable' : 'required') . '|uuid|exists:med_policies,id',
            'member_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::MEMBER_TYPES)),
            'principal_id' => 'nullable|uuid|exists:med_members,id',
            'relationship' => 'nullable|string|max:30',
            'title' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:M,F',
            'marital_status' => 'nullable|string|max:20',
            'national_id' => 'nullable|string|max:30',
            'passport_number' => 'nullable|string|max:30',
            'employee_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'region_code' => 'nullable|string|max:20',
            
            // Employment
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'employment_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'salary_band' => 'nullable|string|max:20',

            // Coverage
            'cover_start_date' => 'nullable|date',
            'cover_end_date' => 'nullable|date|after_or_equal:cover_start_date',

            // Medical history
            'has_pre_existing_conditions' => 'boolean',
            'is_chronic_patient' => 'boolean',
            'declared_conditions' => 'nullable|array',
            'declared_conditions.*' => 'string',

            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];

        // Dependents require principal_id and relationship
        if ($this->input('member_type') !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
            $rules['principal_id'] = 'required|uuid|exists:med_members,id';
            $rules['relationship'] = 'required|string|max:30';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'policy_id.required' => 'Policy is required',
            'member_type.required' => 'Member type is required',
            'member_type.in' => 'Invalid member type',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be M or F',
            'principal_id.required' => 'Principal member is required for dependents',
            'relationship.required' => 'Relationship is required for dependents',
        ];
    }
}