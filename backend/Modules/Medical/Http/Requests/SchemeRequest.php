<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class SchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schemeId = $this->route('scheme') ?? $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('med_schemes', 'slug')->ignore($schemeId),
            ],
            'market_segment' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::MARKET_SEGMENTS)),
            ],
            'description' => 'nullable|string|max:1000',
            'eligibility_rules' => 'nullable|array',
            'underwriting_rules' => 'nullable|array',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'nullable|boolean',
        ];
    }
}