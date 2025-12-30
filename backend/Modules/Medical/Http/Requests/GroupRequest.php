<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'employee_count' => ['nullable', 'integer'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'physical_address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string'],
            'preferred_payment_method' => ['nullable', 'string'],
            'broker_id' => ['nullable', 'uuid'],
            'broker_commission_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'billing_email' => ['nullable', 'email'],
            'billing_address' => ['nullable', 'string'],

            // Nested primary contact
            'primary_contact' => ['nullable', 'array'],
            'primary_contact.contact_type' => ['required_with:primary_contact'],
            'primary_contact.first_name' => ['required_with:primary_contact'],
            'primary_contact.last_name' => ['required_with:primary_contact'],
        ];

        if ($this->isMethod('POST')) {
            $rules['registration_number'][] = 'unique:med_corporate_groups,registration_number';
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $groupId = $this->route('group');
            $rules['registration_number'][] =
                Rule::unique('med_corporate_groups', 'registration_number')->ignore($groupId);
        }

        return $rules;
    }
}
