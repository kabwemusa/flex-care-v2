<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'    => 'required|string|max:50',
            'premium' => 'required|numeric|min:0',
            'plan_id' => 'nullable|uuid|exists:med_plans,id',
        ];
    }
}
