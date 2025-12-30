<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class LoadingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_name' => 'required|string|max:255',
            'condition_category' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::CONDITION_CATEGORIES)),
            ],
            'icd10_code' => 'nullable|string|max:20',
            'related_icd_codes' => 'nullable|array',
            'related_icd_codes.*' => 'string|max:20',
            'loading_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::LOADING_TYPES)),
            ],
            'loading_value' => 'required_unless:loading_type,exclusion|nullable|numeric|min:0',
            'duration_type' => [
                'required',
                'string',
                Rule::in(['permanent', 'time_limited', 'reviewable']),
            ],
            'duration_months' => 'required_if:duration_type,time_limited|nullable|integer|min:1|max:120',
            'exclusion_available' => 'nullable|boolean',
            'exclusion_terms' => 'nullable|string|max:1000',
            'exclusion_benefit_id' => 'nullable|exists:med_benefits,id',
            'required_documents' => 'nullable|array',
            'required_documents.*' => 'string|max:255',
            'underwriting_notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ];
    }
}