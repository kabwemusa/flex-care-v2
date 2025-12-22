<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlanAddonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for linking add-ons to a plan
     */
    public function rules(): array
    {
        return [
            // Ensure addon_ids is sent as an array
            'addon_ids'   => 'present|array',
            
            // Validate that each ID exists in the med_addons table
            'addon_ids.*' => 'required|integer|exists:med_addons,id',
        ];
    }

    public function messages(): array
    {
        return [
            'addon_ids.*.exists' => 'One or more selected add-ons are invalid or no longer exist in the library.',
        ];
    }
}