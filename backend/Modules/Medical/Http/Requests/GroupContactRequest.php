<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class GroupContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::CONTACT_TYPES)),
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'has_portal_access' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_type.required' => 'Contact type is required',
            'contact_type.in' => 'Invalid contact type',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email address',
        ];
    }
}