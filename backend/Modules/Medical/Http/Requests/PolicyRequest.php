<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Medical\Constants\MedicalConstants;

class PolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'scheme_id' => 'required|uuid|exists:med_schemes,id',
            'plan_id' => 'required|uuid|exists:med_plans,id',
            'rate_card_id' => 'nullable|uuid|exists:med_rate_cards,id',
            'policy_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::POLICY_TYPES)),
            'group_id' => 'nullable|uuid|exists:med_corporate_groups,id',
            'inception_date' => 'required|date',
            'expiry_date' => 'required|date|after:inception_date',
            'renewal_date' => 'nullable|date',
            'policy_term_months' => 'nullable|integer|min:1|max:60',
            'is_auto_renew' => 'boolean',
            'currency' => 'nullable|string|size:3',
            'billing_frequency' => 'required|in:' . implode(',', array_keys(MedicalConstants::BILLING_FREQUENCIES)),
            'base_premium' => 'nullable|numeric|min:0',
            'addon_premium' => 'nullable|numeric|min:0',
            'loading_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'source' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',

            // Addons
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required|uuid|exists:med_addons,id',
            'addons.*.addon_rate_id' => 'nullable|uuid|exists:med_addon_rates,id',
            'addons.*.premium' => 'nullable|numeric|min:0',

            // Promo code
            'promo_code' => 'nullable|string|max:50',

            // Sales
            'sales_agent_id' => 'nullable|uuid',
            'broker_id' => 'nullable|uuid',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ];

        // Corporate policies require group_id
        if ($this->input('policy_type') === MedicalConstants::POLICY_TYPE_CORPORATE) {
            $rules['group_id'] = 'required|uuid|exists:med_corporate_groups,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'scheme_id.required' => 'Please select a scheme',
            'plan_id.required' => 'Please select a plan',
            'policy_type.required' => 'Policy type is required',
            'policy_type.in' => 'Invalid policy type',
            'inception_date.required' => 'Inception date is required',
            'expiry_date.required' => 'Expiry date is required',
            'expiry_date.after' => 'Expiry date must be after inception date',
            'billing_frequency.required' => 'Billing frequency is required',
            'group_id.required' => 'Corporate policies require a group',
        ];
    }
}