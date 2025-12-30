<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class MemberLoadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loading_rule_id' => 'nullable|uuid|exists:med_loading_rules,id',
            'condition_name' => 'required|string|max:255',
            'icd10_code' => 'nullable|string|max:20',
            'loading_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::LOADING_TYPES)),
            'loading_value' => 'required_unless:loading_type,exclusion|numeric|min:0',
            'duration_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::LOADING_DURATIONS)),
            'duration_months' => 'required_if:duration_type,time_limited|nullable|integer|min:1|max:120',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'review_date' => 'nullable|date',
            'underwriting_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'condition_name.required' => 'Condition name is required',
            'loading_type.required' => 'Loading type is required',
            'loading_value.required_unless' => 'Loading value is required for percentage or fixed loadings',
            'duration_type.required' => 'Duration type is required',
            'duration_months.required_if' => 'Duration months is required for time-limited loadings',
            'start_date.required' => 'Start date is required',
        ];
    }
}