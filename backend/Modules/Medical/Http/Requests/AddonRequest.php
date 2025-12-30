<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class AddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'addon_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::ADDON_TYPES)),
            ],
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            
            // Addon benefits
            'benefits' => 'nullable|array',
            'benefits.*.benefit_id' => 'required|exists:med_benefits,id',
            'benefits.*.limit_amount' => 'nullable|numeric|min:0',
            'benefits.*.limit_count' => 'nullable|integer|min:0',
            'benefits.*.limit_days' => 'nullable|integer|min:0',
            'benefits.*.waiting_period_days' => 'nullable|integer|min:0',
            'benefits.*.display_value' => 'nullable|string|max:100',
        ];
    }
}