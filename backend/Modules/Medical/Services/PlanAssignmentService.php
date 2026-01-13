<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Group;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;
use Exception;

/**
 * Service for managing member plan assignments within a group.
 * Supports multi-plan groups and member plan upgrades/downgrades.
 */
class PlanAssignmentService
{
    public function __construct(
        protected PremiumService $premiumService
    ) {}

    /**
     * Assign members to plans based on salary band or department.
     * Creates multiple applications per group - one per plan tier.
     *
     * @param array $members Array of member data with salary_band or department
     * @param array $planMapping [salary_band => plan_id] or [department => plan_id]
     * @param string $groupId
     * @param array $commonData Common application data (scheme, rate_card, dates, etc.)
     * @return array Created applications grouped by plan
     */
    public function assignMembersToPlans(
        array $members,
        array $planMapping,
        string $groupId,
        array $commonData
    ): array {
        return DB::transaction(function () use ($members, $planMapping, $groupId, $commonData) {
            $createdApplications = [];
            $membersByPlan = [];

            // 1. Group members by their assigned plan
            foreach ($members as $member) {
                $planId = $this->determinePlanForMember($member, $planMapping);

                if (!$planId) {
                    // Default to first plan if no match
                    $planId = reset($planMapping);
                }

                if (!isset($membersByPlan[$planId])) {
                    $membersByPlan[$planId] = [];
                }

                $membersByPlan[$planId][] = $member;
            }

            // 2. Create separate application for each plan
            foreach ($membersByPlan as $planId => $planMembers) {
                $application = Application::create([
                    'application_type' => MedicalConstants::APPLICATION_TYPE_NEW,
                    'policy_type' => MedicalConstants::POLICY_TYPE_CORPORATE,
                    'scheme_id' => $commonData['scheme_id'],
                    'plan_id' => $planId,
                    'rate_card_id' => $commonData['rate_card_id'],
                    'group_id' => $groupId,
                    'inception_date' => $commonData['inception_date'],
                    'billing_frequency' => $commonData['billing_frequency'],
                    'status' => MedicalConstants::APPLICATION_STATUS_DRAFT,
                ]);

                // 3. Add members to this application
                foreach ($planMembers as $memberData) {
                    ApplicationMember::create([
                        'application_id' => $application->id,
                        'member_type' => $memberData['member_type'] ?? MedicalConstants::MEMBER_TYPE_PRINCIPAL,
                        'relationship' => $memberData['relationship'] ?? null,
                        'first_name' => $memberData['first_name'],
                        'last_name' => $memberData['last_name'],
                        'date_of_birth' => $memberData['date_of_birth'],
                        'gender' => $memberData['gender'],
                        'email' => $memberData['email'] ?? null,
                        'phone' => $memberData['phone'] ?? null,
                        'employee_number' => $memberData['employee_number'] ?? null,
                        'job_title' => $memberData['job_title'] ?? null,
                        'department' => $memberData['department'] ?? null,
                        'salary_band' => $memberData['salary_band'] ?? null,
                        'underwriting_status' => MedicalConstants::UW_STATUS_PENDING,
                    ]);
                }

                // 4. Calculate premium for this application
                $this->premiumService->calculateApplicationPremium($application);

                $createdApplications[] = [
                    'application_id' => $application->id,
                    'plan_id' => $planId,
                    'member_count' => count($planMembers),
                ];
            }

            return [
                'success' => true,
                'applications' => $createdApplications,
                'total_members' => count($members),
                'plans_used' => count($membersByPlan),
            ];
        });
    }

    /**
     * Determine which plan a member should be assigned to based on mapping rules.
     */
    protected function determinePlanForMember(array $member, array $planMapping): ?string
    {
        // Try salary_band first
        if (!empty($member['salary_band']) && isset($planMapping[$member['salary_band']])) {
            return $planMapping[$member['salary_band']];
        }

        // Try department
        if (!empty($member['department']) && isset($planMapping[$member['department']])) {
            return $planMapping[$member['department']];
        }

        // Try job_title
        if (!empty($member['job_title']) && isset($planMapping[$member['job_title']])) {
            return $planMapping[$member['job_title']];
        }

        return null;
    }

    /**
     * Move member from one plan to another (upgrade/downgrade).
     * Works for both Application Members (prospects) and Policy Members (active).
     *
     * @param string $memberId ApplicationMember or Member ID
     * @param string $newPlanId Target plan ID
     * @param string $effectiveDate When the change takes effect
     * @param string $reason Reason for change (upgrade, downgrade, lateral move)
     * @return array Result with new premium calculations
     */
    public function moveMemberToPlan(
        string $memberId,
        string $newPlanId,
        string $effectiveDate,
        string $reason = 'plan_change'
    ): array {
        return DB::transaction(function () use ($memberId, $newPlanId, $effectiveDate, $reason) {
            // 1. Determine if this is an Application Member or Policy Member
            $applicationMember = ApplicationMember::find($memberId);
            $policyMember = null;

            if ($applicationMember) {
                return $this->moveApplicationMember($applicationMember, $newPlanId, $reason);
            }

            $policyMember = Member::find($memberId);
            if ($policyMember) {
                return $this->movePolicyMember($policyMember, $newPlanId, $effectiveDate, $reason);
            }

            throw new Exception("Member not found: {$memberId}");
        });
    }

    /**
     * Move application member to different plan (create new application).
     */
    protected function moveApplicationMember(
        ApplicationMember $member,
        string $newPlanId,
        string $reason
    ): array {
        $oldApplication = $member->application;

        // Create new application for the target plan
        $newApplication = Application::firstOrCreate(
            [
                'group_id' => $oldApplication->group_id,
                'plan_id' => $newPlanId,
                'status' => MedicalConstants::APPLICATION_STATUS_DRAFT,
            ],
            [
                'application_type' => MedicalConstants::APPLICATION_TYPE_NEW,
                'policy_type' => MedicalConstants::POLICY_TYPE_CORPORATE,
                'scheme_id' => $oldApplication->scheme_id,
                'rate_card_id' => $oldApplication->rate_card_id, // May need different rate card
                'inception_date' => $oldApplication->inception_date,
                'billing_frequency' => $oldApplication->billing_frequency,
            ]
        );

        // Move member to new application
        $member->update(['application_id' => $newApplication->id]);

        // Recalculate premiums for both applications
        $this->premiumService->calculateApplicationPremium($oldApplication);
        $this->premiumService->calculateApplicationPremium($newApplication);

        return [
            'success' => true,
            'member_id' => $member->id,
            'old_application_id' => $oldApplication->id,
            'new_application_id' => $newApplication->id,
            'new_plan_id' => $newPlanId,
            'reason' => $reason,
        ];
    }

    /**
     * Move policy member to different plan (mid-term change).
     * Creates new policy for target plan or adds to existing.
     */
    protected function movePolicyMember(
        Member $member,
        string $newPlanId,
        string $effectiveDate,
        string $reason
    ): array {
        $oldPolicy = $member->policy;
        $effectiveCarbon = Carbon::parse($effectiveDate);

        // Check if there's already a policy for this group + plan combination
        $newPolicy = Policy::where('group_id', $oldPolicy->group_id)
            ->where('plan_id', $newPlanId)
            ->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
            ->first();

        // If no existing policy, create one
        if (!$newPolicy) {
            $newPolicy = Policy::create([
                'policy_number' => 'POL-' . strtoupper(uniqid()),
                'scheme_id' => $oldPolicy->scheme_id,
                'plan_id' => $newPlanId,
                'rate_card_id' => $oldPolicy->rate_card_id, // May need different rate card
                'group_id' => $oldPolicy->group_id,
                'policy_type' => MedicalConstants::POLICY_TYPE_CORPORATE,
                'inception_date' => $effectiveCarbon,
                'expiry_date' => $oldPolicy->expiry_date,
                'billing_frequency' => $oldPolicy->billing_frequency,
                'status' => MedicalConstants::POLICY_STATUS_ACTIVE,
            ]);
        }

        // Calculate pro-rata refund for old policy
        $daysRemaining = $effectiveCarbon->diffInDays($oldPolicy->expiry_date);
        $totalDays = Carbon::parse($oldPolicy->inception_date)->diffInDays($oldPolicy->expiry_date);
        $proRataRefund = ($member->total_premium / $totalDays) * $daysRemaining;

        // Calculate pro-rata premium for new policy
        $newMemberPremium = $this->premiumService->calculatePolicyMemberPremium($member, $newPolicy);
        $proRataPremium = ($newMemberPremium / $totalDays) * $daysRemaining;

        // Update member
        $member->update([
            'policy_id' => $newPolicy->id,
            'cover_start_date' => $effectiveCarbon,
            'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
        ]);

        // Recalculate premiums
        $this->premiumService->calculatePolicyPremium($oldPolicy);
        $this->premiumService->calculatePolicyPremium($newPolicy);

        return [
            'success' => true,
            'member_id' => $member->id,
            'old_policy_id' => $oldPolicy->id,
            'new_policy_id' => $newPolicy->id,
            'old_plan_id' => $oldPolicy->plan_id,
            'new_plan_id' => $newPlanId,
            'effective_date' => $effectiveDate,
            'pro_rata_refund' => round($proRataRefund, 2),
            'pro_rata_premium' => round($proRataPremium, 2),
            'premium_difference' => round($proRataPremium - $proRataRefund, 2),
            'reason' => $reason,
        ];
    }

    /**
     * Get plan distribution summary for a group.
     */
    public function getGroupPlanDistribution(string $groupId): array
    {
        $group = Group::with(['applications.plan', 'policies.plan'])->findOrFail($groupId);

        $distribution = [
            'applications' => [],
            'policies' => [],
        ];

        // Application members distribution
        foreach ($group->applications as $app) {
            $planName = $app->plan->name ?? 'Unknown';
            $memberCount = $app->activeMembers()->count();

            if (!isset($distribution['applications'][$planName])) {
                $distribution['applications'][$planName] = [
                    'plan_id' => $app->plan_id,
                    'plan_name' => $planName,
                    'member_count' => 0,
                    'total_premium' => 0,
                ];
            }

            $distribution['applications'][$planName]['member_count'] += $memberCount;
            $distribution['applications'][$planName]['total_premium'] += $app->total_premium;
        }

        // Policy members distribution
        foreach ($group->policies as $policy) {
            $planName = $policy->plan->name ?? 'Unknown';
            $memberCount = $policy->members()->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)->count();

            if (!isset($distribution['policies'][$planName])) {
                $distribution['policies'][$planName] = [
                    'plan_id' => $policy->plan_id,
                    'plan_name' => $planName,
                    'member_count' => 0,
                    'total_premium' => 0,
                ];
            }

            $distribution['policies'][$planName]['member_count'] += $memberCount;
            $distribution['policies'][$planName]['total_premium'] += $policy->total_premium;
        }

        return [
            'group_id' => $groupId,
            'group_name' => $group->name,
            'distribution' => $distribution,
            'total_applications' => count($group->applications),
            'total_policies' => count($group->policies),
        ];
    }
}
