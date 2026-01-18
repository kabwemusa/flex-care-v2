<?php

namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Medical\Constants\MedicalConstants;

class EndorsementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'policy_id' => 'required|uuid|exists:med_policies,id',
            'endorsement_type' => [
                'required',
                Rule::in(array_keys(MedicalConstants::ENDORSEMENT_TYPES)),
            ],
            'description' => 'required|string|max:255',
            'effective_date' => 'required|date|after_or_equal:today',
            'request_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'supporting_documents' => 'nullable|array',
        ];

        // Add type-specific validation rules
        $type = $this->input('endorsement_type');

        switch ($type) {
            case MedicalConstants::ENDORSEMENT_TYPE_ADD_MEMBER:
                $rules = array_merge($rules, $this->addMemberRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_REMOVE_MEMBER:
                $rules = array_merge($rules, $this->removeMemberRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_ADD_ADDON:
                $rules = array_merge($rules, $this->addAddonRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_REMOVE_ADDON:
                $rules = array_merge($rules, $this->removeAddonRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_UPGRADE_PLAN:
            case MedicalConstants::ENDORSEMENT_TYPE_DOWNGRADE_PLAN:
                $rules = array_merge($rules, $this->planChangeRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_CHANGE_DETAILS:
                $rules = array_merge($rules, $this->changeDetailsRules());
                break;

            case MedicalConstants::ENDORSEMENT_TYPE_CANCELLATION:
                $rules = array_merge($rules, $this->cancellationRules());
                break;
        }

        return $rules;
    }

    protected function addMemberRules(): array
    {
        return [
            'member_data' => 'required|array',
            'member_data.member_type' => [
                'required',
                Rule::in(array_keys(MedicalConstants::MEMBER_TYPES)),
            ],
            'member_data.first_name' => 'required|string|max:100',
            'member_data.last_name' => 'required|string|max:100',
            'member_data.date_of_birth' => 'required|date|before:today',
            'member_data.gender' => 'required|in:M,F',
            'member_data.title' => 'nullable|string|max:20',
            'member_data.middle_name' => 'nullable|string|max:100',
            'member_data.national_id' => 'nullable|string|max:50',
            'member_data.passport_number' => 'nullable|string|max:50',
            'member_data.email' => 'nullable|email|max:255',
            'member_data.phone' => 'nullable|string|max:20',
            'member_data.mobile' => 'nullable|string|max:20',
            'member_data.address' => 'nullable|string|max:255',
            'member_data.city' => 'nullable|string|max:100',
            'member_data.principal_id' => 'nullable|uuid|exists:med_members,id',
            'member_data.relationship' => [
                'nullable',
                Rule::in(array_keys(MedicalConstants::RELATIONSHIPS)),
            ],
        ];
    }

    protected function removeMemberRules(): array
    {
        return [
            'member_id' => 'required|uuid|exists:med_members,id',
            'reason' => 'required|string|max:255',
        ];
    }

    protected function addAddonRules(): array
    {
        return [
            'addon_id' => 'required|uuid|exists:med_addons,id',
        ];
    }

    protected function removeAddonRules(): array
    {
        return [
            'addon_id' => 'required|uuid|exists:med_addons,id',
        ];
    }

    protected function planChangeRules(): array
    {
        return [
            'new_plan_id' => 'required|uuid|exists:med_plans,id',
        ];
    }

    protected function changeDetailsRules(): array
    {
        return [
            'changes' => 'required|array',
            'member_id' => 'nullable|uuid|exists:med_members,id',
        ];
    }

    protected function cancellationRules(): array
    {
        return [
            'reason' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'policy_id.required' => 'Policy is required',
            'policy_id.exists' => 'Policy not found',
            'endorsement_type.required' => 'Endorsement type is required',
            'endorsement_type.in' => 'Invalid endorsement type',
            'description.required' => 'Description is required',
            'effective_date.required' => 'Effective date is required',
            'effective_date.after_or_equal' => 'Effective date must be today or in the future',

            // Add member
            'member_data.required' => 'Member data is required for add member endorsement',
            'member_data.member_type.required' => 'Member type is required',
            'member_data.first_name.required' => 'First name is required',
            'member_data.last_name.required' => 'Last name is required',
            'member_data.date_of_birth.required' => 'Date of birth is required',
            'member_data.date_of_birth.before' => 'Date of birth must be in the past',
            'member_data.gender.required' => 'Gender is required',

            // Remove member
            'member_id.required' => 'Member ID is required for remove member endorsement',
            'member_id.exists' => 'Member not found',
            'reason.required' => 'Reason is required',

            // Addon
            'addon_id.required' => 'Add-on ID is required',
            'addon_id.exists' => 'Add-on not found',

            // Plan change
            'new_plan_id.required' => 'New plan is required for plan change endorsement',
            'new_plan_id.exists' => 'Plan not found',

            // Changes
            'changes.required' => 'Changes data is required',
        ];
    }

    public function attributes(): array
    {
        return [
            'policy_id' => 'policy',
            'endorsement_type' => 'endorsement type',
            'effective_date' => 'effective date',
            'member_data.first_name' => 'first name',
            'member_data.last_name' => 'last name',
            'member_data.date_of_birth' => 'date of birth',
            'member_data.member_type' => 'member type',
            'new_plan_id' => 'new plan',
            'addon_id' => 'add-on',
        ];
    }
}
