<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:med_plans,id',
            'name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean'
        ];
    }
}