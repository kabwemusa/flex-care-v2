<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class PlanExclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'benefit_id' => 'nullable|exists:med_benefits,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'exclusion_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::EXCLUSION_TYPES)),
            ],
            'conditions' => 'nullable|array',
            'exclusion_period_days' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(function () {
                    return $this->input('exclusion_type') === MedicalConstants::EXCLUSION_TYPE_TIME_LIMITED;
                }),
            ],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Exclusion name is required',
            'exclusion_type.required' => 'Exclusion type is required',
            'exclusion_type.in' => 'Invalid exclusion type',
            'exclusion_period_days.required_if' => 'Exclusion period is required for time-limited exclusions',
            'benefit_id.exists' => 'The selected benefit does not exist',
        ];
    }
}
