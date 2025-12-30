<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class RateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:med_plans,id',
            'name' => 'required|string|max:255',
            'version' => 'nullable|string|max:20',
            'currency' => 'nullable|string|size:3',
            'premium_frequency' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::PREMIUM_FREQUENCIES)),
            ],
            'premium_basis' => [
                'required',
                'string',
                Rule::in(array_keys(MedicalConstants::PREMIUM_BASES)),
            ],
            'member_type_factors' => 'nullable|array',
            'member_type_factors.principal' => 'nullable|numeric|min:0|max:5',
            'member_type_factors.spouse' => 'nullable|numeric|min:0|max:5',
            'member_type_factors.child' => 'nullable|numeric|min:0|max:5',
            'member_type_factors.parent' => 'nullable|numeric|min:0|max:5',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'notes' => 'nullable|string|max:1000',
            
            // Entries (for age-banded pricing)
            'entries' => 'nullable|array',
            'entries.*.min_age' => 'required|integer|min:0|max:100',
            'entries.*.max_age' => 'required|integer|min:0|max:100|gte:entries.*.min_age',
            'entries.*.age_band_label' => 'nullable|string|max:50',
            'entries.*.gender' => 'nullable|string|in:M,F',
            'entries.*.region_code' => 'nullable|string|max:20',
            'entries.*.base_premium' => 'required|numeric|min:0',
            
            // Tiers (for family-size pricing)
            'tiers' => 'nullable|array',
            'tiers.*.tier_name' => 'required|string|max:50',
            'tiers.*.tier_description' => 'nullable|string|max:255',
            'tiers.*.min_members' => 'required|integer|min:1',
            'tiers.*.max_members' => 'nullable|integer|min:1',
            'tiers.*.tier_premium' => 'required|numeric|min:0',
            'tiers.*.extra_member_premium' => 'nullable|numeric|min:0',
        ];
    }
}