<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'premium'              => 'required|numeric|min:0',
            'discount_rule_ids'    => 'required|array|min:1',
            'discount_rule_ids.*'  => 'required|uuid|exists:med_discount_rules,id',
        ];
    }
}
