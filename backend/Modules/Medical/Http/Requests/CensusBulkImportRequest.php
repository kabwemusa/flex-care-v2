<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CensusBulkImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/permissions
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,xlsx,xls',
                'max:10240', // 10MB max
            ],
            'group_id' => 'required|exists:med_corporate_groups,id',
            'scheme_id' => 'nullable|exists:med_schemes,id',
            'plan_id' => 'nullable|exists:med_plans,id',
            'effective_date' => 'nullable|date|after_or_equal:today',
            'validate_only' => 'nullable|boolean', // Preview mode without saving
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a census file.',
            'file.mimes' => 'Census file must be CSV, Excel (.xlsx), or Excel (.xls) format.',
            'file.max' => 'Census file size must not exceed 10MB.',
            'group_id.required' => 'Corporate group is required.',
            'group_id.exists' => 'Selected corporate group does not exist.',
        ];
    }
}
