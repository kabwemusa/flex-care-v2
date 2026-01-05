<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'application_type' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::APPLICATION_TYPES)),
            'policy_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::POLICY_TYPES)),
            'scheme_id' => 'required|uuid|exists:med_schemes,id',
            'plan_id' => 'required|uuid|exists:med_plans,id',
            'rate_card_id' => 'required|uuid|exists:med_rate_cards,id',
            'group_id' => 'nullable|uuid|exists:med_corporate_groups,id',
            'renewal_of_policy_id' => 'nullable|uuid|exists:med_policies,id',
            
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            
            'proposed_start_date' => 'nullable|date|after_or_equal:today',
            'proposed_end_date' => 'nullable|date|after:proposed_start_date',
            'policy_term_months' => 'nullable|integer|min:1|max:36',
            'billing_frequency' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::BILLING_FREQUENCIES)),
            'currency' => 'nullable|string|size:3',
            
            'source' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::APPLICATION_SOURCES)),
            'sales_agent_id' => 'nullable|string|max:36',
            'broker_id' => 'nullable|string|max:36',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            
            'notes' => 'nullable|string|max:2000',
            'metadata' => 'nullable|array',
            
            // Nested members
            'members' => 'nullable|array',
            'members.*.member_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::MEMBER_TYPES)),
            'members.*.first_name' => 'required|string|max:100',
            'members.*.last_name' => 'required|string|max:100',
            'members.*.date_of_birth' => 'required|date|before:today',
            'members.*.gender' => 'nullable|in:M,F',
            'members.*.relationship' => 'nullable|in:' . implode(',', array_keys(MedicalConstants::RELATIONSHIPS)),
            'members.*.national_id' => 'nullable|string|max:50',
            'members.*.email' => 'nullable|email|max:255',
            'members.*.phone' => 'nullable|string|max:50',
            'members.*.mobile' => 'nullable|string|max:50',
            
            // Nested addons
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required|uuid|exists:med_addons,id',
            'addons.*.addon_rate_id' => 'nullable|uuid|exists:med_addon_rates,id',
        ];

        // For corporate applications, group is required
        if ($this->input('policy_type') === MedicalConstants::POLICY_TYPE_CORPORATE) {
            $rules['group_id'] = 'required|uuid|exists:med_corporate_groups,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'policy_type.required' => 'Policy type is required',
            'scheme_id.required' => 'Scheme is required',
            'plan_id.required' => 'Plan is required',
            'rate_card_id.required' => 'Rate card is required',
            'group_id.required' => 'Corporate group is required for corporate policies',
            'proposed_start_date.after_or_equal' => 'Start date must be today or in the future',
            'members.*.first_name.required' => 'Member first name is required',
            'members.*.last_name.required' => 'Member last name is required',
            'members.*.date_of_birth.required' => 'Member date of birth is required',
        ];
    }
}