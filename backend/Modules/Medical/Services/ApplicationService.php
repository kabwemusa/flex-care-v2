<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\ApplicationAddon;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PolicyAddon;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\MemberLoading;
use Modules\Medical\Models\MemberExclusion;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;
use Exception;

class ApplicationService
{
    public function __construct(
        protected PremiumService $premiumService
    ) {}

    // =========================================================================
    // APPLICATION CREATION
    // =========================================================================

    public function createApplication(array $data): Application
    {
        return DB::transaction(function () use ($data) {
            $application = Application::create([
                'application_type' => $data['application_type'] ?? MedicalConstants::APPLICATION_TYPE_NEW,
                'policy_type' => $data['policy_type'],
                'scheme_id' => $data['scheme_id'],
                'plan_id' => $data['plan_id'],
                'rate_card_id' => $data['rate_card_id'],
                'group_id' => $data['group_id'] ?? null,
                'renewal_of_policy_id' => $data['renewal_of_policy_id'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'proposed_start_date' => $data['proposed_start_date'] ?? now()->addDays(1),
                'proposed_end_date' => $data['proposed_end_date'] ?? null,
                'policy_term_months' => $data['policy_term_months'] ?? 12,
                'billing_frequency' => $data['billing_frequency'] ?? MedicalConstants::BILLING_MONTHLY,
                'currency' => $data['currency'] ?? 'ZMW',
                'source' => $data['source'] ?? MedicalConstants::SOURCE_ONLINE,
                'sales_agent_id' => $data['sales_agent_id'] ?? null,
                'broker_id' => $data['broker_id'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? null,
                'status' => MedicalConstants::APPLICATION_STATUS_DRAFT,
            ]);

            if (!empty($data['members'])) {
                $this->addMembersToApplication($application, $data['members']);
            }

            if (!empty($data['addons'])) {
                $this->addAddonsToApplication($application, $data['addons']);
            }

            // Initial calculation
            $this->premiumService->calculateApplicationPremium($application);
            $application->updateMemberCounts();

            return $application->fresh([
                'scheme', 'plan', 'rateCard', 'activeMembers', 'activeAddons'
            ]);
        });
    }

    public function addMembersToApplication(Application $application, array $members): void
    {
        $principalMap = [];

        foreach ($members as $memberData) {
            $memberType = $memberData['member_type'] ?? MedicalConstants::MEMBER_TYPE_PRINCIPAL;
            
            // Resolve Principal ID
            $principalId = null;
            if ($memberType !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                if (!empty($memberData['principal_member_id'])) {
                    $principalId = $memberData['principal_member_id']; // Direct ID passed (unlikely in new creation)
                } elseif (!empty($principalMap)) {
                    $principalId = reset($principalMap); // Link to the first principal created in this batch
                }
            }

            // Calculate Age
            $dob = $memberData['date_of_birth'] ?? null;
            $ageAtInception = null;
            if ($dob) {
                $inceptionDate = $application->proposed_start_date ?? now();
                $ageAtInception = Carbon::parse($dob)->diffInYears($inceptionDate);
            }

            $member = ApplicationMember::create([
                'application_id' => $application->id,
                'member_type' => $memberType,
                'principal_member_id' => $principalId,
                'relationship' => $memberData['relationship'] ?? null,
                'title' => $memberData['title'] ?? null,
                'first_name' => $memberData['first_name'],
                'middle_name' => $memberData['middle_name'] ?? null,
                'last_name' => $memberData['last_name'],
                'date_of_birth' => $dob,
                'gender' => $memberData['gender'] ?? null,
                'marital_status' => $memberData['marital_status'] ?? null,
                'national_id' => $memberData['national_id'] ?? null,
                'passport_number' => $memberData['passport_number'] ?? null,
                'email' => $memberData['email'] ?? null,
                'phone' => $memberData['phone'] ?? null,
                'mobile' => $memberData['mobile'] ?? null,
                'address' => $memberData['address'] ?? null,
                'city' => $memberData['city'] ?? null,
                'employee_number' => $memberData['employee_number'] ?? null,
                'job_title' => $memberData['job_title'] ?? null,
                'department' => $memberData['department'] ?? null,
                'employment_date' => $memberData['employment_date'] ?? null,
                'salary' => $memberData['salary'] ?? null,
                'salary_band' => $memberData['salary_band'] ?? null,
                'age_at_inception' => $ageAtInception,
                'has_pre_existing_conditions' => $memberData['has_pre_existing_conditions'] ?? false,
                'declared_conditions' => $memberData['declared_conditions'] ?? null,
                'medical_history_notes' => $memberData['medical_history_notes'] ?? null,
            ]);

            if ($memberType === MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                $principalMap[$member->id] = $member->id;
            }

            // Calculate individual premium immediately
            $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
        }
    }

    public function addAddonsToApplication(Application $application, array $addons): void
    {
        foreach ($addons as $addonData) {
            ApplicationAddon::create([
                'application_id' => $application->id,
                'addon_id' => $addonData['addon_id'],
                'addon_rate_id' => $addonData['addon_rate_id'] ?? null,
                'premium' => $addonData['premium'] ?? 0,
            ]);
        }
    }

    // =========================================================================
    // WORKFLOW
    // =========================================================================

    public function markAsQuoted(Application $application): Application
    {
        if ($application->activeMembers()->count() === 0) {
            throw new Exception('Application must have at least one member');
        }

        $this->premiumService->calculateApplicationPremium($application);

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_QUOTED,
            'quoted_at' => now(),
            'quote_valid_until' => now()->addDays(30),
        ]);

        return $application->fresh();
    }

    public function submitForUnderwriting(Application $application): Application
    {
        if (!$application->can_be_submitted) {
            throw new Exception('Application cannot be submitted. Check status.');
        }

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            'submitted_at' => now()
        ]);

        return $application->fresh();
    }

    public function startUnderwriting(Application $application, string $underwriterId): Application
    {
        if (!in_array($application->status, [
            MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            MedicalConstants::APPLICATION_STATUS_REFERRED,
        ])) {
            throw new Exception('Application is not ready for underwriting');
        }

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
            'underwriting_status' => MedicalConstants::UW_STATUS_IN_PROGRESS,
            'underwriter_id' => $underwriterId,
            'underwriting_started_at' => now(),
        ]);

        return $application->fresh();
    }

    public function applyMemberUnderwritingDecision(
        ApplicationMember $member,
        string $decision,
        string $underwriterId,
        array $loadings = [],
        array $exclusions = [],
        ?string $notes = null
    ): ApplicationMember {
        
        $updateData = [
            'underwritten_by' => $underwriterId,
            'underwritten_at' => now(),
            'underwriting_notes' => $notes,
        ];

        switch ($decision) {
            case 'approve':
                $updateData['underwriting_status'] = MedicalConstants::UW_STATUS_APPROVED;
                $updateData['applied_loadings'] = []; 
                $updateData['applied_exclusions'] = []; 
                break;

            case 'decline':
                $updateData['underwriting_status'] = MedicalConstants::UW_STATUS_DECLINED;
                $updateData['is_active'] = false;
                break;

            case 'terms':
                $updateData['underwriting_status'] = MedicalConstants::UW_STATUS_TERMS;
                $updateData['applied_loadings'] = $loadings;
                $updateData['applied_exclusions'] = $exclusions;
                break;

            default:
                throw new Exception("Invalid decision: {$decision}");
        }

        $member->update($updateData);

        // Access the parent application to get the rate card
        // Ideally eager load this earlier, but lazy loading here is safe for single actions
        $application = $member->application;

        if (!$application->rateCard) {
            throw new Exception("Cannot recalculate premium: Application has no active Rate Card.");
        }

        // FIX: Pass the RateCard as the second argument
        $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
        
        // Recalculate application (totals)
        $this->premiumService->calculateApplicationPremium($application);

        return $member->fresh();
    }

    public function approveApplication(Application $application, string $underwriterId, ?string $notes = null): Application
    {
        // Auto-approve pending members as Standard
        $application->activeMembers()
            ->where('underwriting_status', MedicalConstants::UW_STATUS_PENDING)
            ->update([
                'underwriting_status' => MedicalConstants::UW_STATUS_APPROVED,
                'underwritten_by' => $underwriterId,
                'underwritten_at' => now(),
            ]);

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_APPROVED,
            'underwriting_status' => MedicalConstants::UW_STATUS_APPROVED,
            'underwriter_id' => $underwriterId,
            'underwriting_completed_at' => now(),
            'underwriting_notes' => $notes,
            'quote_valid_until' => now()->addDays(14),
        ]);

        return $application->fresh();
    }

    public function declineApplication(Application $application, string $underwriterId, string $reason): Application
    {
        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_DECLINED,
            'underwriting_status' => MedicalConstants::UW_STATUS_DECLINED,
            'underwriter_id' => $underwriterId,
            'underwriting_completed_at' => now(),
            'underwriting_notes' => $reason,
        ]);
        return $application->fresh();
    }

    public function referApplication(Application $application, string $underwriterId, string $reason): Application
    {
        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_REFERRED,
            'underwriting_status' => MedicalConstants::UW_STATUS_REFERRED,
            'underwriter_id' => $underwriterId,
            'underwriting_notes' => $reason,
        ]);
        return $application->fresh();
    }

    public function acceptQuote(Application $application, ?string $acceptanceReference = null): Application
    {
        if (!$application->can_be_accepted) {
            throw new Exception('Application cannot be accepted. Check status and validity.');
        }

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_ACCEPTED,
            'accepted_at' => now(),
            'acceptance_reference' => $acceptanceReference,
        ]);

        return $application->fresh();
    }

    // =========================================================================
    // CONVERSION
    // =========================================================================

    public function convertToPolicy(Application $application, string $issuedBy): Policy
    {
        if (!$application->can_be_converted) {
            throw new Exception('Application cannot be converted.');
        }

        return DB::transaction(function () use ($application, $issuedBy) {
            // 1. Create Policy
            $policy = Policy::createFromApplication($application, $issuedBy);

            // 2. Convert Members
            $memberMap = []; // app_member_id => member_model
            
            // 2a. Principals
            $principals = $application->activeMembers()
                ->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL)
                ->where('underwriting_status', '!=', MedicalConstants::UW_STATUS_DECLINED)
                ->get();

            foreach ($principals as $appMember) {
                $member = $this->convertApplicationMember($appMember, $policy, null);
                $memberMap[$appMember->id] = $member;

                if (!$policy->principal_member_id) {
                    $policy->setPrincipalMember($member);
                }
            }

            // 2b. Dependents
            $dependents = $application->activeMembers()
                ->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL)
                ->where('underwriting_status', '!=', MedicalConstants::UW_STATUS_DECLINED)
                ->get();

            foreach ($dependents as $appMember) {
                $principal = $memberMap[$appMember->principal_member_id] ?? null;
                $member = $this->convertApplicationMember($appMember, $policy, $principal);
                $memberMap[$appMember->id] = $member;
            }

            // 3. Convert Addons
            foreach ($application->activeAddons as $appAddon) {
                PolicyAddon::create([
                    'policy_id' => $policy->id,
                    'addon_id' => $appAddon->addon_id,
                    'addon_rate_id' => $appAddon->addon_rate_id,
                    'premium' => $appAddon->premium,
                    'is_active' => true,
                ]);
            }

            $policy->updateMemberCounts();
            
            // 4. Update Application Status
            $application->markAsConverted($policy->id, $issuedBy);

            // 5. Activate Group if Prospect
            if ($policy->group_id && $policy->group && $policy->group->status === MedicalConstants::GROUP_STATUS_PROSPECT) {
                $policy->group->update(['status' => MedicalConstants::GROUP_STATUS_ACTIVE]);
            }

            return $policy->fresh(['scheme', 'plan', 'members']);
        });
    }

    protected function convertApplicationMember(ApplicationMember $appMember, Policy $policy, ?Member $principal): Member
    {
        // Create basic Member record
        $member = Member::createFromApplicationMember($appMember, $policy, $principal);

        // Materialize Loadings from JSON -> DB Table
        if (!empty($appMember->applied_loadings)) {
            foreach ($appMember->applied_loadings as $loading) {
                MemberLoading::create([
                    'member_id' => $member->id,
                    'condition_name' => $loading['condition_name'] ?? 'Loading',
                    'icd10_code' => $loading['icd10_code'] ?? null,
                    'loading_type' => $loading['loading_type'] ?? 'percentage',
                    'loading_value' => $loading['value'] ?? 0,
                    'loading_amount' => $loading['loading_amount'] ?? 0, // Calculated amount
                    'duration_type' => $loading['duration_type'] ?? 'permanent',
                    'duration_months' => $loading['duration_months'] ?? null,
                    'start_date' => $policy->inception_date,
                    'status' => 'active',
                    'applied_by' => $appMember->underwritten_by,
                    'applied_at' => $appMember->underwritten_at,
                    'notes' => $loading['notes'] ?? null,
                ]);
            }
            $member->recalculateLoadings();
        }

        // Materialize Exclusions from JSON -> DB Table
        if (!empty($appMember->applied_exclusions)) {
            foreach ($appMember->applied_exclusions as $exclusion) {
                MemberExclusion::create([
                    'member_id' => $member->id,
                    'exclusion_type' => $exclusion['exclusion_type'] ?? 'condition',
                    'exclusion_name' => $exclusion['exclusion_name'] ?? 'Exclusion',
                    'icd10_codes' => $exclusion['icd10_codes'] ?? null,
                    'benefit_id' => $exclusion['benefit_id'] ?? null,
                    'is_permanent' => $exclusion['is_permanent'] ?? true,
                    'start_date' => $policy->inception_date,
                    'status' => 'active',
                    'applied_by' => $appMember->underwritten_by,
                    'applied_at' => $appMember->underwritten_at,
                    'notes' => $exclusion['notes'] ?? null,
                ]);
            }
        }

        return $member;
    }

    // =========================================================================
    // PROMO CODE
    // =========================================================================

    public function applyPromoCode(Application $application, string $code): Application
    {
        $promoCode = PromoCode::byCode($code)->usable()->first();

        if (!$promoCode) {
            throw new Exception('Invalid or expired promo code');
        }

        if (!$promoCode->isEligibleForScheme($application->scheme_id) || !$promoCode->isEligibleForPlan($application->plan_id)) {
            throw new Exception('Promo code not valid for this plan/scheme');
        }

        // Apply
        $application->promo_code_id = $promoCode->id;

        $discountRule = $promoCode->discountRule;
        if ($discountRule) {
            $discountAmount = $discountRule->calculateAdjustment($application->base_premium);
            $application->discount_amount = $discountAmount;
            
            // Store history
            $currentDiscounts = $application->applied_discounts ?? [];
            $currentDiscounts[] = [
                'type' => 'promo',
                'code' => $code,
                'amount' => $discountAmount,
                'applied_at' => now()->toIsoString()
            ];
            $application->applied_discounts = $currentDiscounts;
        }

        $application->save();
        $promoCode->incrementUsage();

        // Recalculate
        $this->premiumService->calculateApplicationPremium($application);

        return $application->fresh();
    }

    // =========================================================================
    // RENEWAL APPLICATION (FIXED)
    // =========================================================================

    public function createRenewalApplication(Policy $policy, array $overrides = []): Application
    {
        if (!$policy->is_active && $policy->status !== MedicalConstants::POLICY_STATUS_EXPIRED) {
            throw new Exception('Only active or expired policies can be renewed');
        }

        if ($policy->renewed_to_policy_id) {
            throw new Exception('Policy has already been renewed');
        }

        return DB::transaction(function () use ($policy, $overrides) {
            $newStartDate = $policy->expiry_date->copy()->addDay();
            
            $application = Application::create([
                'application_type' => MedicalConstants::APPLICATION_TYPE_RENEWAL,
                'policy_type' => $policy->policy_type,
                'scheme_id' => $overrides['scheme_id'] ?? $policy->scheme_id,
                'plan_id' => $overrides['plan_id'] ?? $policy->plan_id,
                'rate_card_id' => $overrides['rate_card_id'] ?? $policy->rate_card_id,
                'group_id' => $policy->group_id,
                'renewal_of_policy_id' => $policy->id,
                'contact_name' => $policy->holder_name,
                'contact_email' => $policy->holder_email,
                'contact_phone' => $policy->holder_phone,
                'proposed_start_date' => $newStartDate,
                'policy_term_months' => $overrides['policy_term_months'] ?? $policy->policy_term_months,
                'billing_frequency' => $overrides['billing_frequency'] ?? $policy->billing_frequency,
                'currency' => $policy->currency,
                'source' => MedicalConstants::SOURCE_RENEWAL,
                'status' => MedicalConstants::APPLICATION_STATUS_DRAFT,
            ]);

            // Map old members to new application members
            $memberMap = []; // old_id => new_app_member_id

            // 1. Copy Principals
            foreach ($policy->activeMembers()->principals()->get() as $member) {
                $appMember = $this->copyMemberToApplication($application, $member, null);
                $memberMap[$member->id] = $appMember->id;
            }

            // 2. Copy Dependents
            foreach ($policy->activeMembers()->dependents()->get() as $member) {
                $principalAppId = $memberMap[$member->principal_member_id] ?? null;
                $this->copyMemberToApplication($application, $member, $principalAppId);
            }

            // 3. Copy Addons
            foreach ($policy->activeAddons as $policyAddon) {
                ApplicationAddon::create([
                    'application_id' => $application->id,
                    'addon_id' => $policyAddon->addon_id,
                    'addon_rate_id' => $policyAddon->addon_rate_id,
                ]);
            }

            $this->premiumService->calculateApplicationPremium($application);
            $application->updateMemberCounts();

            return $application->fresh();
        });
    }

    /**
     * Copies a policy member to an application member, PRESERVING RISK DATA.
     */
    protected function copyMemberToApplication(Application $application, Member $member, ?string $principalAppId): ApplicationMember
    {
        $ageAtInception = $member->date_of_birth 
            ? $member->date_of_birth->diffInYears($application->proposed_start_date)
            : null;

        // 1. Extract existing loadings to JSON format
        $loadingsJson = [];
        $hasLoadings = false;
        foreach ($member->activeLoadings as $loading) {
            $hasLoadings = true;
            $loadingsJson[] = [
                'condition_name' => $loading->condition_name,
                'icd10_code' => $loading->icd10_code,
                'loading_type' => $loading->loading_type, // percentage vs fixed
                'value' => $loading->loading_value,
                'duration_type' => $loading->duration_type,
                'duration_months' => $loading->duration_months,
                'notes' => 'Carried over from renewal'
            ];
        }

        // 2. Extract existing exclusions to JSON format
        $exclusionsJson = [];
        $hasExclusions = false;
        foreach ($member->activeExclusions as $exclusion) {
            $hasExclusions = true;
            $exclusionsJson[] = [
                'exclusion_name' => $exclusion->exclusion_name,
                'exclusion_type' => $exclusion->exclusion_type,
                'icd10_codes' => $exclusion->icd10_codes,
                'benefit_id' => $exclusion->benefit_id,
                'is_permanent' => $exclusion->is_permanent,
                'notes' => 'Carried over from renewal'
            ];
        }

        return ApplicationMember::create([
            'application_id' => $application->id,
            'member_type' => $member->member_type,
            'principal_member_id' => $principalAppId,
            'relationship' => $member->relationship,
            'title' => $member->title,
            'first_name' => $member->first_name,
            'middle_name' => $member->middle_name,
            'last_name' => $member->last_name,
            'date_of_birth' => $member->date_of_birth,
            'gender' => $member->gender,
            'marital_status' => $member->marital_status,
            'national_id' => $member->national_id,
            'passport_number' => $member->passport_number,
            'email' => $member->email,
            'phone' => $member->phone,
            'mobile' => $member->mobile,
            'address' => $member->address,
            'city' => $member->city,
            // Employment
            'employee_number' => $member->employee_number,
            'job_title' => $member->job_title,
            'department' => $member->department,
            'employment_date' => $member->employment_date,
            'salary' => $member->salary,
            'salary_band' => $member->salary_band,
            // Risk
            'age_at_inception' => $ageAtInception,
            'has_pre_existing_conditions' => $member->has_pre_existing_conditions,
            'declared_conditions' => $member->declared_conditions,
            
            // Critical Renewal Logic:
            // If they had risk terms, carry them over and set status to TERMS
            // If standard, set to APPROVED
            'underwriting_status' => ($hasLoadings || $hasExclusions) 
                ? MedicalConstants::UW_STATUS_TERMS 
                : MedicalConstants::UW_STATUS_APPROVED,
            
            'applied_loadings' => $loadingsJson,
            'applied_exclusions' => $exclusionsJson,
        ]);
    }
}