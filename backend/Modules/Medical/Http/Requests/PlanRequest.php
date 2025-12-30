<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheme_id' => 'required|exists:med_schemes,id',
            'name' => 'required|string|max:255',
            'tier_level' => 'nullable|integer|min:1|max:10',
            'plan_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::PLAN_TYPES)),
            ],
            'network_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(MedicalConstants::NETWORK_TYPES)),
            ],
            'member_config' => 'nullable|array',
            'member_config.max_dependents' => 'nullable|integer|min:0|max:20',
            'member_config.allowed_member_types' => 'nullable|array',
            'member_config.child_age_limit' => 'nullable|integer|min:0|max:30',
            'member_config.child_student_age_limit' => 'nullable|integer|min:0|max:30',
            'member_config.parent_age_limit' => 'nullable|integer|min:0|max:100',
            'default_waiting_periods' => 'nullable|array',
            'default_waiting_periods.general' => 'nullable|integer|min:0',
            'default_waiting_periods.maternity' => 'nullable|integer|min:0',
            'default_waiting_periods.pre_existing' => 'nullable|integer|min:0',
            'default_waiting_periods.chronic' => 'nullable|integer|min:0',
            'default_cost_sharing' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
        ];
    }
}