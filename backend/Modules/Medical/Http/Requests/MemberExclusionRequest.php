<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class MemberExclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'benefit_id' => 'nullable|uuid|exists:med_benefits,id',
            'exclusion_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::EXCLUSION_TYPES)),
            'exclusion_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icd10_codes' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'review_date' => 'nullable|date',
            'underwriting_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'exclusion_type.required' => 'Exclusion type is required',
            'exclusion_name.required' => 'Exclusion name is required',
            'start_date.required' => 'Start date is required',
            'end_date.after' => 'End date must be after start date',
        ];
    }
}