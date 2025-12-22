<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchemeRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        // 1. Get the scheme ID from the route. 
        // Note: If your route is /schemes/{scheme}, use 'scheme' instead of 'id'.
        $schemeId = $this->route('scheme') ?? $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // 2. Use the Rule class for a cleaner, safer unique check
                Rule::unique('med_schemes', 'name')->ignore($schemeId),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}