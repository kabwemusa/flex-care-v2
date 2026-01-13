<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupQuoteRequest extends FormRequest
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
            'group_id' => 'required|exists:med_corporate_groups,id',
            'plan_id' => 'required|exists:med_plans,id',
            'rate_card_id' => 'nullable|exists:med_rate_cards,id',
            'billing_frequency' => 'nullable|in:monthly,quarterly,semi_annual,annual',

            // Members array (can be provided or loaded from census)
            'members' => 'required|array|min:1',
            'members.*.date_of_birth' => 'required|date|before:today',
            'members.*.member_type' => 'required|in:principal,spouse,child,parent,other',
            'members.*.gender' => 'nullable|in:M,F,Male,Female',

            // Optional addons
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required|exists:med_addons,id',

            // Optional discount codes
            'discount_codes' => 'nullable|array',
            'discount_codes.*' => 'string|max:50',

            // Quote options
            'show_tier_breakdown' => 'nullable|boolean',
            'show_volume_discounts' => 'nullable|boolean',
            'include_next_tier_info' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'group_id.required' => 'Corporate group is required for group quotes.',
            'group_id.exists' => 'Selected corporate group does not exist.',
            'plan_id.required' => 'Medical plan is required.',
            'plan_id.exists' => 'Selected medical plan does not exist.',
            'members.required' => 'At least one member is required for quote generation.',
            'members.min' => 'At least one member is required for quote generation.',
            'members.*.date_of_birth.required' => 'Date of birth is required for all members.',
            'members.*.date_of_birth.before' => 'Date of birth must be in the past.',
            'members.*.member_type.required' => 'Member type is required for all members.',
            'members.*.member_type.in' => 'Invalid member type. Must be principal, spouse, child, parent, or other.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'group_id' => 'corporate group',
            'plan_id' => 'medical plan',
            'rate_card_id' => 'rate card',
            'billing_frequency' => 'billing frequency',
        ];
    }
}
