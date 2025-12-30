<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class PlanBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'benefit_id' => 'required|exists:med_benefits,id',
            'parent_plan_benefit_id' => 'nullable|exists:med_plan_benefits,id',
            'limit_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_TYPES)),
            ],
            'limit_frequency' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_FREQUENCIES)),
            ],
            'limit_basis' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::LIMIT_BASES)),
            ],
            'limit_amount' => 'nullable|numeric|min:0',
            'limit_count' => 'nullable|integer|min:0',
            'limit_days' => 'nullable|integer|min:0',
            'per_claim_limit' => 'nullable|numeric|min:0',
            'per_day_limit' => 'nullable|numeric|min:0',
            'max_claims_per_year' => 'nullable|integer|min:0',
            'waiting_period_days' => 'nullable|integer|min:0',
            'cost_sharing' => 'nullable|array',
            'cost_sharing.copay_type' => 'nullable|string',
            'cost_sharing.copay_amount' => 'nullable|numeric|min:0',
            'cost_sharing.copay_percentage' => 'nullable|numeric|min:0|max:100',
            'cost_sharing.deductible' => 'nullable|numeric|min:0',
            'is_covered' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'display_value' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            
            // Member-specific limits
            'member_limits' => 'nullable|array',
            'member_limits.*.member_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::MEMBER_TYPES)),
            ],
            'member_limits.*.min_age' => 'nullable|integer|min:0|max:100',
            'member_limits.*.max_age' => 'nullable|integer|min:0|max:100',
            'member_limits.*.limit_amount' => 'nullable|numeric|min:0',
            'member_limits.*.limit_count' => 'nullable|integer|min:0',
            'member_limits.*.limit_days' => 'nullable|integer|min:0',
            'member_limits.*.display_value' => 'nullable|string|max:100',
        ];
    }
}