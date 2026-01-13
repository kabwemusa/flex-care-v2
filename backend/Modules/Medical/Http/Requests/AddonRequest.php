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
            'pricing_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::ADDON_PRICING_TYPES)),
            ],
            'currency' => 'nullable|string|max:10',
            'amount' => 'required_if:pricing_type,fixed,per_member|nullable|numeric|min:0',
            'percentage' => 'required_if:pricing_type,percentage|nullable|numeric|min:0|max:100',
            'percentage_basis' => 'nullable|string|in:base_premium,total_premium',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}