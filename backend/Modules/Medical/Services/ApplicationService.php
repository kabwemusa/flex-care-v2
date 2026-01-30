<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\ApplicationAddon;
use Modules\Medical\Models\ApplicationDocument;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PolicyAddon;
use Modules\Medical\Models\PolicyDocument;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\MemberLoading;
use Modules\Medical\Models\MemberExclusion;
use Modules\Medical\Models\MemberDocument;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Constants\MedicalConstants;
use App\Services\ApprovalService;
use App\Models\ApprovalRequest;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    public function __construct(
        protected PremiumService $premiumService,
        protected BillingService $billingService
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

    // public function addMembersToApplication(Application $application, array $members): void
    // {
    //     $principalMap = [];

    //     foreach ($members as $memberData) {
    //         $memberType = $memberData['member_type'] ?? MedicalConstants::MEMBER_TYPE_PRINCIPAL;
            
    //         // Resolve Principal ID
    //         $principalId = null;
    //         if ($memberType !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
    //             if (!empty($memberData['principal_member_id'])) {
    //                 $principalId = $memberData['principal_member_id']; // Direct ID passed (unlikely in new creation)
    //             } elseif (!empty($principalMap)) {
    //                 $principalId = reset($principalMap); // Link to the first principal created in this batch
    //             }
    //         }

    //         // Calculate Age
    //         $dob = $memberData['date_of_birth'] ?? null;
    //         $ageAtInception = null;
    //         if ($dob) {
    //             $inceptionDate = $application->proposed_start_date ?? now();
    //             $ageAtInception = Carbon::parse($dob)->diffInYears($inceptionDate);
    //         }

    //         $member = ApplicationMember::create([
    //             'application_id' => $application->id,
    //             'member_type' => $memberType,
    //             'principal_member_id' => $principalId,
    //             'relationship' => $memberData['relationship'] ?? null,
    //             'title' => $memberData['title'] ?? null,
    //             'first_name' => $memberData['first_name'],
    //             'middle_name' => $memberData['middle_name'] ?? null,
    //             'last_name' => $memberData['last_name'],
    //             'date_of_birth' => $dob,
    //             'gender' => $memberData['gender'] ?? null,
    //             'marital_status' => $memberData['marital_status'] ?? null,
    //             'national_id' => $memberData['national_id'] ?? null,
    //             'passport_number' => $memberData['passport_number'] ?? null,
    //             'email' => $memberData['email'] ?? null,
    //             'phone' => $memberData['phone'] ?? null,
    //             'mobile' => $memberData['mobile'] ?? null,
    //             'address' => $memberData['address'] ?? null,
    //             'city' => $memberData['city'] ?? null,
    //             'employee_number' => $memberData['employee_number'] ?? null,
    //             'job_title' => $memberData['job_title'] ?? null,
    //             'department' => $memberData['department'] ?? null,
    //             'employment_date' => $memberData['employment_date'] ?? null,
    //             'salary' => $memberData['salary'] ?? null,
    //             'salary_band' => $memberData['salary_band'] ?? null,
    //             'age_at_inception' => $ageAtInception,
    //             'has_pre_existing_conditions' => $memberData['has_pre_existing_conditions'] ?? false,
    //             'declared_conditions' => $memberData['declared_conditions'] ?? null,
    //             'medical_history_notes' => $memberData['medical_history_notes'] ?? null,
    //         ]);

    //         if ($memberType === MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
    //             $principalMap[$member->id] = $member->id;
    //         }

    //         // Calculate individual premium immediately
    //         $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
    //     }
    // }


    public function addMembersToApplication(Application $application, array $members): void
    {
        // Use a variable to track the *current* principal in the loop context
        $currentPrincipalId = null;

        foreach ($members as $memberData) {
            $memberType = $memberData['member_type'] ?? MedicalConstants::MEMBER_TYPE_PRINCIPAL;
            
            // 1. Resolve Principal ID
            $principalId = null;
            
            if ($memberType === MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                // This member is a principal; they have no parent
                $principalId = null;
            } else {
                // This is a dependent.
                // Priority 1: Did the frontend pass a specific principal ID?
                if (!empty($memberData['principal_member_id'])) {
                    $principalId = $memberData['principal_member_id'];
                } 
                // Priority 2: Use the most recently created principal in this loop
                elseif ($currentPrincipalId) {
                    $principalId = $currentPrincipalId;
                }
            }

            // 2. Calculate Age (Fixing the Float vs Int PostgreSQL error)
            $dob = $memberData['date_of_birth'] ?? null;
            $ageAtInception = 0; // Default to 0 if no DOB
            
            if ($dob) {
                $inceptionDate = $application->proposed_start_date ? Carbon::parse($application->proposed_start_date) : now();
                // diffInYears returns an INT, solving your Postgres error
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
                'age_at_inception' => (int) $ageAtInception, // Explicit cast for safety
                'has_pre_existing_conditions' => $memberData['has_pre_existing_conditions'] ?? false,
                'declared_conditions' => $memberData['declared_conditions'] ?? null,
                'medical_history_notes' => $memberData['medical_history_notes'] ?? null,
            ]);

            // 3. Update Current Principal Tracker
            if ($memberType === MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                $currentPrincipalId = $member->id;
            }

            // 4. Calculate Premium
            // Ensure rateCard is loaded to avoid N+1 or null errors
            if ($application->relationLoaded('rateCard') || $application->rateCard) {
                $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
            }
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

    /**
     * Add a single member to an application
     * Used for adding members one-by-one (e.g., from census import)
     */
    public function addMember(string $applicationId, array $memberData): ApplicationMember
    {
        $application = Application::with('rateCard')->findOrFail($applicationId);

        $memberType = $memberData['member_type'] ?? MedicalConstants::MEMBER_TYPE_PRINCIPAL;

        // Resolve Principal ID for dependents
        $principalId = null;
        if ($memberType !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
            // Link to principal if specified, or find first principal in application
            if (!empty($memberData['principal_member_id'])) {
                $principalId = $memberData['principal_member_id'];
            } else {
                $firstPrincipal = $application->activeMembers()
                    ->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL)
                    ->first();
                $principalId = $firstPrincipal?->id;
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
            'relationship' => $memberData['relationship'] ?? null,
            'principal_member_id' => $principalId,
            'title' => $memberData['title'] ?? null,
            'first_name' => $memberData['first_name'],
            'middle_name' => $memberData['middle_name'] ?? null,
            'last_name' => $memberData['last_name'],
            'date_of_birth' => $dob,
            'age_at_inception' => $ageAtInception !== null ? (int) $ageAtInception : null,
            'gender' => $memberData['gender'],
            'national_id' => $memberData['national_id'] ?? null,
            'email' => $memberData['email'] ?? null,
            'phone' => $memberData['phone'] ?? null,
            'address' => $memberData['address'] ?? null,
            'city' => $memberData['city'] ?? null,
            'state' => $memberData['state'] ?? null,
            'country' => $memberData['country'] ?? 'Zambia',
            'postal_code' => $memberData['postal_code'] ?? null,
            'employee_number' => $memberData['employee_number'] ?? null,
            'job_title' => $memberData['job_title'] ?? null,
            'department' => $memberData['department'] ?? null,
            'has_pre_existing_conditions' => $memberData['has_pre_existing_conditions'] ?? false,
            'declared_conditions' => $memberData['declared_conditions'] ?? null,
            'underwriting_status' => MedicalConstants::UW_STATUS_PENDING,
        ]);

        $applicationTogo = new Application();
        $applicationTogo->activeMembers = $member;
    
        $this->premiumService->calculateApplicationPremium($applicationTogo);

        // Calculate individual premium
        // $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);

        return $member->fresh();
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

    public function submitForUnderwriting(Application $application, User $submittedBy): Application
    {
        if (!$application->can_be_submitted) {
            throw new Exception('Application cannot be submitted. Check status.');
        }

        $application->update([
            'status' => MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            'submitted_at' => now()
        ]);

        // Check if there's an approval workflow for applications
        if ($application->hasApprovalWorkflow()) {
            try {
                // Initiate the approval workflow
                $application->submitForApproval($submittedBy);
                Log::info('Approval workflow initiated for application: ' . $application->id);
            } catch (Exception $e) {
                Log::error('Failed to initiate approval workflow: ' . $e->getMessage(), [
                    'application_id' => $application->id,
                    'entity_type' => $application->getApprovalEntityType(),
                    'error' => $e->getMessage()
                ]);
                // Re-throw to notify user of the issue
                throw new Exception('Failed to initiate approval workflow: ' . $e->getMessage());
            }
        } else {
            Log::info('No approval workflow found for application entity type: ' . $application->getApprovalEntityType());
        }

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
        array $discounts = [],
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
                $updateData['applied_discounts'] = [];
                break;

            case 'decline':
                $updateData['underwriting_status'] = MedicalConstants::UW_STATUS_DECLINED;
                $updateData['is_active'] = false;
                break;

            case 'terms':
                $updateData['underwriting_status'] = MedicalConstants::UW_STATUS_TERMS;
                $updateData['applied_loadings'] = $loadings;
                $updateData['applied_exclusions'] = $exclusions;
                $updateData['applied_discounts'] = $discounts;
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

        // Calculate and update discount amount on the application
        $this->updateApplicationDiscountAmount($application);

        // FIX: Pass the RateCard as the second argument
        $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);

        // Recalculate application (totals)
        $this->premiumService->calculateApplicationPremium($application);

        return $member->fresh();
    }

    /**
     * Calculate the total discount amount from all member-applied discounts
     * and update the application's discount_amount field.
     */
    protected function updateApplicationDiscountAmount(Application $application): void
    {
        $application->load('activeMembers');

        $totalDiscountAmount = 0;
        $basePremium = (float) $application->base_premium;

        foreach ($application->activeMembers as $member) {
            $appliedDiscounts = $member->applied_discounts ?? [];

            foreach ($appliedDiscounts as $discount) {
                $type = $discount['type'] ?? 'percentage';
                $value = (float) ($discount['value'] ?? 0);

                if ($type === 'percentage') {
                    // Calculate percentage of base premium for this member
                    $memberBasePremium = (float) ($member->base_premium ?? 0);
                    $totalDiscountAmount += round($memberBasePremium * ($value / 100), 2);
                } elseif ($type === 'fixed') {
                    $totalDiscountAmount += $value;
                }
            }
        }

        $application->update([
            'discount_amount' => round($totalDiscountAmount, 2),
        ]);
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
    // APPROVAL WORKFLOW ACTIONS
    // =========================================================================

    /**
     * Approve an application at the current approval step
     */
    public function approveApplicationStep(Application $application, User $approver, ?string $comments = null): array
    {
        $approvalService = app(ApprovalService::class);
        $request = $application->getActiveApprovalRequest();

        if (!$request) {
            throw new Exception('No active approval request found for this application');
        }

        if (!$request->userCanApprove($approver)) {
            throw new Exception('You are not authorized to approve at this step');
        }

        $updatedRequest = $approvalService->approve($request, $approver, $comments);

        return [
            'request' => $updatedRequest,
            'application' => $application->fresh(),
            'is_final' => $updatedRequest->status === ApprovalRequest::STATUS_APPROVED,
        ];
    }

    /**
     * Reject an application at the current approval step
     */
    public function rejectApplicationStep(Application $application, User $rejector, string $reason): array
    {
        $approvalService = app(ApprovalService::class);
        $request = $application->getActiveApprovalRequest();

        if (!$request) {
            throw new Exception('No active approval request found for this application');
        }

        if (!$request->userCanApprove($rejector)) {
            throw new Exception('You are not authorized to reject at this step');
        }

        $updatedRequest = $approvalService->reject($request, $rejector, $reason);

        return [
            'request' => $updatedRequest,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Return an application for amendment at the current approval step
     */
    public function returnApplicationStep(Application $application, User $returner, string $reason): array
    {
        $approvalService = app(ApprovalService::class);
        $request = $application->getActiveApprovalRequest();

        if (!$request) {
            throw new Exception('No active approval request found for this application');
        }

        if (!$request->userCanApprove($returner)) {
            throw new Exception('You are not authorized to return at this step');
        }

        $updatedRequest = $approvalService->returnForAmendment($request, $returner, $reason);

        return [
            'request' => $updatedRequest,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Get the approval status for an application
     */
    public function getApprovalStatus(Application $application): array
    {
        return $application->getApprovalProgress();
    }

    /**
     * Check if user can approve the current step
     */
    public function canUserApprove(Application $application, User $user): bool
    {
        $request = $application->getActiveApprovalRequest();
        return $request && $request->userCanApprove($user);
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

            // 4. Copy Application Documents to Policy/Members
            $this->copyApplicationDocuments($application, $policy, $memberMap);

            // 5. Update Application Status
            $application->markAsConverted($policy->id, $issuedBy);

            // 6. Activate Group if Prospect
            if ($policy->group_id && $policy->group && $policy->group->status === MedicalConstants::GROUP_STATUS_PROSPECT) {
                $policy->group->update(['status' => MedicalConstants::GROUP_STATUS_ACTIVE]);
            }

            // 7. Auto-generate first invoice if configured
            if (config('medical.invoice.auto_generate_on_policy_creation', true)) {
                $this->billingService->generateFirstInvoice($policy, $issuedBy);
            }

            return $policy->fresh(['scheme', 'plan', 'members', 'invoices']);
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

    /**
     * Copy application documents to policy and member documents.
     *
     * - Documents with application_member_id → MemberDocument (linked to converted member)
     * - Documents without application_member_id → PolicyDocument (general policy docs)
     */
    protected function copyApplicationDocuments(Application $application, Policy $policy, array $memberMap): void
    {
        $applicationDocuments = ApplicationDocument::where('application_id', $application->id)
            ->active()
            ->get();

        foreach ($applicationDocuments as $appDoc) {
            // Skip if file doesn't exist
            if (!Storage::disk('private')->exists($appDoc->file_path)) {
                continue;
            }

            if ($appDoc->application_member_id) {
                // Member-specific document → copy to MemberDocument
                $member = $memberMap[$appDoc->application_member_id] ?? null;
                if ($member) {
                    $this->copyDocumentToMember($appDoc, $member);
                }
            } else {
                // General application document → copy to PolicyDocument
                $this->copyDocumentToPolicy($appDoc, $policy);
            }
        }
    }

    /**
     * Copy an application document to a policy document.
     */
    protected function copyDocumentToPolicy(ApplicationDocument $appDoc, Policy $policy): PolicyDocument
    {
        // Copy file to new location
        $newPath = "policies/{$policy->id}/documents/{$appDoc->file_name}";
        Storage::disk('private')->copy($appDoc->file_path, $newPath);

        // Create policy document record
        return PolicyDocument::create([
            'policy_id' => $policy->id,
            'document_type' => $this->mapApplicationDocTypeToPolicyDocType($appDoc->document_type),
            'title' => $appDoc->title,
            'file_path' => $newPath,
            'file_name' => $appDoc->file_name,
            'mime_type' => $appDoc->mime_type,
            'file_size' => $appDoc->file_size,
            'issue_date' => now(),
            'uploaded_by' => $appDoc->uploaded_by,
            'is_system_generated' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Copy an application document to a member document.
     */
    protected function copyDocumentToMember(ApplicationDocument $appDoc, Member $member): MemberDocument
    {
        // Copy file to new location
        $newPath = "members/{$member->id}/documents/{$appDoc->file_name}";
        Storage::disk('private')->copy($appDoc->file_path, $newPath);

        // Create member document record
        return MemberDocument::create([
            'member_id' => $member->id,
            'document_type' => $appDoc->document_type,
            'title' => $appDoc->title,
            'file_path' => $newPath,
            'file_name' => $appDoc->file_name,
            'mime_type' => $appDoc->mime_type,
            'file_size' => $appDoc->file_size,
            'issue_date' => now(),
            'is_verified' => $appDoc->is_verified,
            'verified_by' => $appDoc->verified_by,
            'verified_at' => $appDoc->verified_at,
            'uploaded_by' => $appDoc->uploaded_by,
            'is_active' => true,
        ]);
    }

    /**
     * Map application document types to policy document types.
     * Some types are shared, others need mapping.
     */
    protected function mapApplicationDocTypeToPolicyDocType(string $appDocType): string
    {
        // Application doc types: id_copy, medical_report, census_file, declaration, passport, birth_certificate
        // Policy doc types: certificate, schedule, endorsement, terms, supporting_document, census_file, declaration_form

        $mapping = [
            'declaration' => MedicalConstants::DOC_TYPE_DECLARATION,
            'declaration_form' => MedicalConstants::DOC_TYPE_DECLARATION,
            'census_file' => MedicalConstants::DOC_TYPE_CENSUS,
        ];

        return $mapping[$appDocType] ?? MedicalConstants::DOC_TYPE_SUPPORTING;
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
            ? (int) $member->date_of_birth->diffInYears($application->proposed_start_date)
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