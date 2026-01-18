<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'policy_id' => 'required|uuid|exists:med_policies,id',
            'member_id' => 'required|uuid|exists:med_members,id',
            'claim_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::CLAIM_TYPES)),
            ],
            'submission_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::SUBMISSION_TYPES)),
            ],
            'submission_channel' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::SUBMISSION_CHANNELS)),
            ],
            'service_date' => 'required|date|before_or_equal:today',
            'service_end_date' => 'nullable|date|after_or_equal:service_date',
            'admission_date' => 'nullable|date|before_or_equal:service_date',
            'discharge_date' => 'nullable|date|after_or_equal:admission_date',
            'days_admitted' => 'nullable|integer|min:1',

            // Provider information
            'provider_name' => 'nullable|string|max:255',
            'provider_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::PROVIDER_TYPES)),
            ],
            'provider_invoice_number' => 'nullable|string|max:50',

            // Diagnosis
            'primary_diagnosis' => 'nullable|string|max:255',
            'primary_icd_code' => 'nullable|string|max:20',
            'secondary_diagnoses' => 'nullable|array',
            'secondary_diagnoses.*.diagnosis' => 'required_with:secondary_diagnoses|string',
            'secondary_diagnoses.*.icd_code' => 'nullable|string|max:20',
            'diagnosis_notes' => 'nullable|string|max:2000',

            // Amounts
            'currency' => 'nullable|string|size:3',
            'claimed_amount' => 'required|numeric|min:0.01',

            // Pre-authorization
            'requires_preauth' => 'nullable|boolean',
            'preauth_number' => 'nullable|string|max:30',

            // Claim lines
            'lines' => 'nullable|array',
            'lines.*.service_description' => 'required_with:lines|string|max:255',
            'lines.*.service_date' => 'nullable|date',
            'lines.*.service_code' => 'nullable|string|max:30',
            'lines.*.benefit_id' => 'nullable|uuid|exists:med_benefits,id',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.claimed_amount' => 'required_with:lines|numeric|min:0.01',
        ];

        // If updating, some fields are optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['policy_id'] = 'sometimes|uuid|exists:med_policies,id';
            $rules['member_id'] = 'sometimes|uuid|exists:med_members,id';
            $rules['claim_type'] = [
                'sometimes',
                'string',
                Rule::in(array_keys(MedicalConstants::CLAIM_TYPES)),
            ];
            $rules['service_date'] = 'sometimes|date|before_or_equal:today';
            $rules['claimed_amount'] = 'sometimes|numeric|min:0.01';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'policy_id.required' => 'A policy must be selected',
            'policy_id.exists' => 'The selected policy does not exist',
            'member_id.required' => 'A member must be selected',
            'member_id.exists' => 'The selected member does not exist',
            'claim_type.required' => 'Please select a claim type',
            'claim_type.in' => 'Invalid claim type selected',
            'service_date.required' => 'Service date is required',
            'service_date.before_or_equal' => 'Service date cannot be in the future',
            'claimed_amount.required' => 'Claimed amount is required',
            'claimed_amount.min' => 'Claimed amount must be greater than zero',
            'lines.*.service_description.required_with' => 'Service description is required for each line',
            'lines.*.claimed_amount.required_with' => 'Claimed amount is required for each line',
        ];
    }
}
