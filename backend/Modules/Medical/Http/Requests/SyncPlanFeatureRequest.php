<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlanFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Ensure 'features' is sent as a key containing an object/array
            'features' => 'present|array',
            
            // Validate the IDs (keys of the array)
            'features.*' => 'array',
            
            // Validate the pivot data (limit_amount and description)
            'features.*.limit_amount' => 'required|numeric|min:0|max:999999999.99',
            'features.*.limit_description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'features.*.limit_amount.required' => 'Every selected benefit must have a limit amount (enter 0 for unlimited).',
            'features.*.limit_amount.numeric' => 'The limit amount must be a valid number.',
        ];
    }
}