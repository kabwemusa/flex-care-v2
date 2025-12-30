<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PolicyAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'addon_id' => 'required|uuid|exists:med_addons,id',
            'addon_rate_id' => 'nullable|uuid|exists:med_addon_rates,id',
            'premium' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ];
    }

    public function messages(): array
    {
        return [
            'addon_id.required' => 'Addon is required',
            'addon_id.exists' => 'Invalid addon selected',
        ];
    }
}