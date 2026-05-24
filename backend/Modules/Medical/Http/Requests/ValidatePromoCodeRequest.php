<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'      => 'required|string|max:50',
            'scheme_id' => 'nullable|uuid|exists:med_schemes,id',
            'plan_id'   => 'nullable|uuid|exists:med_plans,id',
        ];
    }
}
