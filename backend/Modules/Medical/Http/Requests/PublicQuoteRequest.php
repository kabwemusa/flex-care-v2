<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Request validation for public quote generation.
 * More restrictive than QuoteRequest - doesn't allow medical_conditions
 * as those require authenticated underwriting.
 */
class PublicQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            // Plan ID - validation that it's active/visible is done in controller
            // to provide better error messages
            'plan_id' => 'required|uuid',

            // Members
            'members' => 'required|array|min:1|max:10', // Lower limit for public
            'members.*.member_type' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::MEMBER_TYPES)),
            ],
            'members.*.age' => 'required|integer|min:0|max:100',
            'members.*.gender' => 'nullable|string|in:M,F',
            // No 'name' field for public quotes - privacy

            // Addons
            'addon_ids' => 'nullable|array|max:10',
            'addon_ids.*' => 'uuid',

            // Promo code
            'promo_code' => 'nullable|string|max:50|alpha_dash',

            // Discount context (limited fields)
            'discount_context' => 'nullable|array',
            'discount_context.billing_frequency' => [
                'nullable',
                'string',
                Rule::in(['monthly', 'quarterly', 'semi_annual', 'annual']),
            ],

            // NOTE: medical_conditions is NOT allowed in public quotes
            // NOTE: cover_start_date is NOT allowed in public quotes
            // NOTE: region_code is NOT allowed in public quotes
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a plan.',
            'plan_id.uuid' => 'Invalid plan selection.',
            'members.required' => 'At least one member is required.',
            'members.max' => 'Maximum 10 members allowed per quote.',
            'members.*.age.max' => 'Member age cannot exceed 100 years.',
            'members.*.member_type.in' => 'Invalid member type.',
            'addon_ids.max' => 'Maximum 10 addons allowed.',
            'promo_code.alpha_dash' => 'Invalid promo code format.',
        ];
    }
}
