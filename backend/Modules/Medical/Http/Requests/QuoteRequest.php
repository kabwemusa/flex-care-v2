<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:med_plans,id',
            'members' => 'required|array|min:1|max:20',
            'members.*.member_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::MEMBER_TYPES)),
            ],
            'members.*.age' => 'required|integer|min:0|max:100',
            'members.*.gender' => 'nullable|string|in:M,F',
            'members.*.name' => 'nullable|string|max:100',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:med_addons,id',
            'promo_code' => 'nullable|string|max:50',
            'discount_context' => 'nullable|array',
            'discount_context.billing_frequency' => 'nullable|string',
            'discount_context.group_size' => 'nullable|integer|min:1',
            'medical_conditions' => 'nullable|array',
            'medical_conditions.*' => 'string|max:100',
            'cover_start_date' => 'nullable|date|after_or_equal:today',
            'region_code' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'members.required' => 'At least one member is required.',
            'members.*.age.max' => 'Member age cannot exceed 100 years.',
        ];
    }
}