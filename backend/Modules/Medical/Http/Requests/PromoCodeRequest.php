<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promoId = $this->route('promo_code') ?? $this->route('id');

        return [
            'discount_rule_id' => 'required|exists:med_discount_rules,id',
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'eligible_schemes' => 'nullable|array',
            'eligible_schemes.*' => 'exists:med_schemes,id',
            'eligible_plans' => 'nullable|array',
            'eligible_plans.*' => 'exists:med_plans,id',
            'eligible_groups' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.alpha_dash' => 'Promo code can only contain letters, numbers, dashes and underscores.',
        ];
    }
}