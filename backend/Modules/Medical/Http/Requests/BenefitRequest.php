<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class BenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:med_benefit_categories,id',
            'parent_id' => 'nullable|exists:med_benefits,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'benefit_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::CATEGORIES)),
            ],
            'default_limit_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_TYPES)),
            ],
            'default_limit_frequency' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_FREQUENCIES)),
            ],
            'default_limit_basis' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_BASES)),
            ],
            'applicable_member_types' => 'nullable|array',
            'requires_preauth' => 'nullable|boolean',
            'requires_referral' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}