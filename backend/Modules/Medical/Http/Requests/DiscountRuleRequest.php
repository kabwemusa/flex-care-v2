<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class DiscountRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheme_id' => 'nullable|exists:med_schemes,id',
            'plan_id' => 'nullable|exists:med_plans,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'adjustment_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::ADJUSTMENT_TYPES)),
            ],
            'value_type' => [
                'required',
                'string',
                Rule::in(['percentage', 'fixed']),
            ],
            'value' => 'required|numeric|min:0',
            'applies_to' => [
                'nullable',
                'string',
                Rule::in(['base_premium', 'total_premium']),
            ],
            'application_method' => [
                'required',
                'string',
                Rule::in(['automatic', 'manual', 'promo_code']),
            ],
            'trigger_rules' => 'nullable|array',
            'trigger_rules.min_group_size' => 'nullable|integer|min:1',
            'trigger_rules.billing_frequency' => 'nullable|string',
            'trigger_rules.min_members' => 'nullable|integer|min:1',
            'can_stack' => 'nullable|boolean',
            'max_total_discount' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:0|max:100',
            'max_uses' => 'nullable|integer|min:1',
            'terms_conditions' => 'nullable|string|max:2000',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'nullable|boolean',
        ];
    }
}