<?php

namespace Modules\Medical\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Models\Endorsement;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PolicyAddon;

class EndorsementService
{
    public function __construct(
        protected PolicyService $policyService,
        protected PremiumService $premiumService,
        protected ProRatingService $proRatingService
    ) {}

    // =========================================================================
    // ENDORSEMENT CREATION
    // =========================================================================

    /**
     * Create a new endorsement.
     */
    public function createEndorsement(array $data): Endorsement
    {
        return DB::transaction(function () use ($data) {
            $policy = Policy::findOrFail($data['policy_id']);

            // Validate policy can have endorsements
            $this->validatePolicyForEndorsement($policy);

            // Validate effective date is within policy period
            if (!empty($data['effective_date'])) {
                $effectiveDate = Carbon::parse($data['effective_date']);
                $this->validateEffectiveDateForPolicy($effectiveDate, $policy);
            }

            // Set request date if not provided
            if (empty($data['request_date'])) {
                $data['request_date'] = now();
            }

            // Create endorsement
            $endorsement = Endorsement::create($data);

            // Capture before snapshot
            $this->captureBeforeSnapshot($endorsement, $policy);

            // Calculate financial impact based on type
            $this->calculateFinancialImpact($endorsement, $policy);

            return $endorsement->fresh(['policy', 'member', 'addon', 'newPlan']);
        });
    }

    /**
     * Create an add member endorsement.
     */
    public function createAddMemberEndorsement(Policy $policy, array $memberData, Carbon|string $effectiveDate): Endorsement
    {
        return DB::transaction(function () use ($policy, $memberData, $effectiveDate) {
            $effectiveDate = Carbon::parse($effectiveDate);

            // Calculate expected premium
            $expectedPremium = $this->calculateMemberPremiumImpact($policy, $memberData, $effectiveDate);

            $endorsement = $this->createEndorsement([
                'policy_id' => $policy->id,
                'endorsement_type' => MedicalConstants::ENDORSEMENT_TYPE_ADD_MEMBER,
                'description' => 'Add new member: ' . ($memberData['first_name'] ?? '') . ' ' . ($memberData['last_name'] ?? ''),
                'effective_date' => $effectiveDate,
                'premium_adjustment' => $expectedPremium['prorated'],
                'prorated_amount' => $expectedPremium['prorated'],
                'generates_invoice' => true,
                'changes' => [
                    'member_data' => $memberData,
                    'premium_calculation' => $expectedPremium,
                ],
            ]);

            return $endorsement;
        });
    }

    /**
     * Create a remove member endorsement.
     */
    public function createRemoveMemberEndorsement(Policy $policy, Member $member, string $reason, Carbon|string $effectiveDate): Endorsement
    {
        return DB::transaction(function () use ($policy, $member, $reason, $effectiveDate) {
            $effectiveDate = Carbon::parse($effectiveDate);

            // Calculate refund amount
            $refundAmount = $this->calculateMemberRefund($member, $effectiveDate, $policy->expiry_date);

            $endorsement = $this->createEndorsement([
                'policy_id' => $policy->id,
                'member_id' => $member->id,
                'endorsement_type' => MedicalConstants::ENDORSEMENT_TYPE_REMOVE_MEMBER,
                'description' => 'Remove member: ' . $member->full_name . ' - ' . $reason,
                'effective_date' => $effectiveDate,
                'premium_adjustment' => -$refundAmount,
                'prorated_amount' => $refundAmount,
                'generates_refund' => $refundAmount > 0,
                'changes' => [
                    'member_id' => $member->id,
                    'member_name' => $member->full_name,
                    'member_number' => $member->member_number,
                    'reason' => $reason,
                    'refund_calculation' => [
                        'original_premium' => $member->premium,
                        'refund_amount' => $refundAmount,
                    ],
                ],
            ]);

            return $endorsement;
        });
    }

    /**
     * Create an add addon endorsement.
     */
    public function createAddAddonEndorsement(Policy $policy, string $addonId, Carbon|string $effectiveDate): Endorsement
    {
        return DB::transaction(function () use ($policy, $addonId, $effectiveDate) {
            $effectiveDate = Carbon::parse($effectiveDate);

            // Calculate addon premium
            $addonPremium = $this->calculateAddonPremiumImpact($policy, $addonId, $effectiveDate);

            $endorsement = $this->createEndorsement([
                'policy_id' => $policy->id,
                'addon_id' => $addonId,
                'endorsement_type' => MedicalConstants::ENDORSEMENT_TYPE_ADD_ADDON,
                'description' => 'Add add-on to policy',
                'effective_date' => $effectiveDate,
                'premium_adjustment' => $addonPremium['prorated'],
                'prorated_amount' => $addonPremium['prorated'],
                'generates_invoice' => true,
                'changes' => [
                    'addon_id' => $addonId,
                    'premium_calculation' => $addonPremium,
                ],
            ]);

            return $endorsement;
        });
    }

    /**
     * Create a plan change endorsement.
     */
    public function createPlanChangeEndorsement(Policy $policy, string $newPlanId, Carbon|string $effectiveDate): Endorsement
    {
        return DB::transaction(function () use ($policy, $newPlanId, $effectiveDate) {
            $effectiveDate = Carbon::parse($effectiveDate);

            // Calculate premium difference
            $premiumDiff = $this->calculatePlanChangePremiumImpact($policy, $newPlanId, $effectiveDate);

            $isUpgrade = $premiumDiff['difference'] > 0;
            $endorsementType = $isUpgrade
                ? MedicalConstants::ENDORSEMENT_TYPE_UPGRADE_PLAN
                : MedicalConstants::ENDORSEMENT_TYPE_DOWNGRADE_PLAN;

            $endorsement = $this->createEndorsement([
                'policy_id' => $policy->id,
                'new_plan_id' => $newPlanId,
                'endorsement_type' => $endorsementType,
                'description' => ($isUpgrade ? 'Upgrade' : 'Downgrade') . ' plan',
                'effective_date' => $effectiveDate,
                'premium_adjustment' => $premiumDiff['prorated_difference'],
                'prorated_amount' => abs($premiumDiff['prorated_difference']),
                'generates_invoice' => $isUpgrade,
                'generates_refund' => !$isUpgrade,
                'changes' => [
                    'old_plan_id' => $policy->plan_id,
                    'new_plan_id' => $newPlanId,
                    'premium_calculation' => $premiumDiff,
                ],
            ]);

            return $endorsement;
        });
    }

    // =========================================================================
    // WORKFLOW ACTIONS
    // =========================================================================

    /**
     * Approve an endorsement.
     */
    public function approveEndorsement(Endorsement $endorsement, string $approvedBy): Endorsement
    {
        if (!$endorsement->can_be_approved) {
            throw new Exception('Endorsement cannot be approved in its current state');
        }

        $endorsement->approve($approvedBy);

        return $endorsement->fresh();
    }

    /**
     * Reject an endorsement.
     */
    public function rejectEndorsement(Endorsement $endorsement, string $rejectedBy, string $reason): Endorsement
    {
        if (!$endorsement->can_be_rejected) {
            throw new Exception('Endorsement cannot be rejected in its current state');
        }

        $endorsement->reject($rejectedBy, $reason);

        return $endorsement->fresh();
    }

    /**
     * Process an approved endorsement.
     */
    public function processEndorsement(Endorsement $endorsement, string $processedBy): Endorsement
    {
        if (!$endorsement->can_be_processed) {
            throw new Exception('Endorsement must be approved before processing');
        }

        return DB::transaction(function () use ($endorsement, $processedBy) {
            $policy = $endorsement->policy;

            // Execute the endorsement based on type
            switch ($endorsement->endorsement_type) {
                case MedicalConstants::ENDORSEMENT_TYPE_ADD_MEMBER:
                    $this->processAddMember($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_REMOVE_MEMBER:
                    $this->processRemoveMember($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_ADD_ADDON:
                    $this->processAddAddon($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_REMOVE_ADDON:
                    $this->processRemoveAddon($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_UPGRADE_PLAN:
                case MedicalConstants::ENDORSEMENT_TYPE_DOWNGRADE_PLAN:
                    $this->processPlanChange($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_CHANGE_DETAILS:
                    $this->processDetailChange($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_CANCELLATION:
                    $this->processCancellation($endorsement, $policy);
                    break;

                case MedicalConstants::ENDORSEMENT_TYPE_REINSTATEMENT:
                    $this->processReinstatement($endorsement, $policy);
                    break;
            }

            // Capture after snapshot
            $this->captureAfterSnapshot($endorsement, $policy->fresh());

            // Mark as processed
            $endorsement->markAsProcessed($processedBy);

            // Recalculate policy premiums
            $this->premiumService->calculatePolicyPremium($policy);

            return $endorsement->fresh(['policy', 'member', 'addon', 'newPlan']);
        });
    }

    /**
     * Cancel an endorsement.
     */
    public function cancelEndorsement(Endorsement $endorsement, string $cancelledBy, ?string $reason = null): Endorsement
    {
        if (!$endorsement->can_be_cancelled) {
            throw new Exception('Endorsement cannot be cancelled in its current state');
        }

        $endorsement->cancel($cancelledBy, $reason);

        return $endorsement->fresh();
    }

    // =========================================================================
    // PROCESS HANDLERS
    // =========================================================================

    protected function processAddMember(Endorsement $endorsement, Policy $policy): void
    {
        $changes = $endorsement->changes;
        $memberData = $changes['member_data'] ?? [];

        if (empty($memberData)) {
            throw new Exception('Member data not found in endorsement');
        }

        // Use PolicyService to add the member
        $member = $this->policyService->addMemberToPolicy(
            $policy,
            $memberData,
            $endorsement->effective_date
        );

        // Update endorsement with member reference
        $endorsement->member_id = $member->id;
        $endorsement->save();
    }

    protected function processRemoveMember(Endorsement $endorsement, Policy $policy): void
    {
        $member = $endorsement->member;

        if (!$member) {
            throw new Exception('Member not found for endorsement');
        }

        $changes = $endorsement->changes;
        $reason = $changes['reason'] ?? 'Endorsement removal';

        // Use PolicyService to terminate the member
        $this->policyService->terminateMemberFromPolicy(
            $policy,
            $member->id,
            $reason,
            $endorsement->effective_date->toDateString()
        );
    }

    protected function processAddAddon(Endorsement $endorsement, Policy $policy): void
    {
        $addonId = $endorsement->addon_id;

        if (!$addonId) {
            throw new Exception('Addon ID not found in endorsement');
        }

        // Check if addon already exists on policy
        $existingAddon = $policy->policyAddons()
            ->where('addon_id', $addonId)
            ->where('is_active', true)
            ->first();

        if ($existingAddon) {
            throw new Exception('Addon already exists on this policy');
        }

        // Add the addon to policy
        $policy->policyAddons()->create([
            'addon_id' => $addonId,
            'premium' => $endorsement->premium_adjustment,
            'is_active' => true,
            'effective_date' => $endorsement->effective_date,
        ]);
    }

    protected function processRemoveAddon(Endorsement $endorsement, Policy $policy): void
    {
        $addonId = $endorsement->addon_id;

        if (!$addonId) {
            throw new Exception('Addon ID not found in endorsement');
        }

        // Find and deactivate the addon
        $policyAddon = $policy->policyAddons()
            ->where('addon_id', $addonId)
            ->where('is_active', true)
            ->first();

        if (!$policyAddon) {
            throw new Exception('Active addon not found on this policy');
        }

        $policyAddon->update([
            'is_active' => false,
            'end_date' => $endorsement->effective_date,
        ]);
    }

    protected function processPlanChange(Endorsement $endorsement, Policy $policy): void
    {
        $newPlanId = $endorsement->new_plan_id;

        if (!$newPlanId) {
            throw new Exception('New plan ID not found in endorsement');
        }

        // Update policy plan
        $policy->update([
            'plan_id' => $newPlanId,
        ]);

        // Recalculate all member premiums based on new plan
        foreach ($policy->activeMembers as $member) {
            $newPremium = $this->premiumService->calculatePolicyMemberPremium($member, $policy);

            // Pro-rate from effective date
            $proratedPremium = $this->proRatingService->calculateProRata(
                $newPremium,
                $endorsement->effective_date,
                $policy->expiry_date
            );

            $member->update(['premium' => $proratedPremium]);
        }
    }

    protected function processDetailChange(Endorsement $endorsement, Policy $policy): void
    {
        $changes = $endorsement->changes;

        if (empty($changes)) {
            return;
        }

        // Apply changes to policy or member
        if (isset($changes['policy_updates'])) {
            $policy->update($changes['policy_updates']);
        }

        if (isset($changes['member_updates']) && $endorsement->member_id) {
            $member = $endorsement->member;
            $member->update($changes['member_updates']);
        }
    }

    protected function processCancellation(Endorsement $endorsement, Policy $policy): void
    {
        $changes = $endorsement->changes;
        $reason = $changes['reason'] ?? 'Policy cancelled via endorsement';

        $this->policyService->cancelPolicy($policy, $reason);
    }

    protected function processReinstatement(Endorsement $endorsement, Policy $policy): void
    {
        $this->policyService->reinstatePolicy($policy);
    }

    // =========================================================================
    // CALCULATION HELPERS
    // =========================================================================

    protected function calculateMemberPremiumImpact(Policy $policy, array $memberData, Carbon $effectiveDate): array
    {
        // Create a temporary member to calculate premium
        $tempMember = new Member($memberData);
        $tempMember->policy_id = $policy->id;

        $annualPremium = $this->premiumService->calculatePolicyMemberPremium($tempMember, $policy);
        $proratedPremium = $this->proRatingService->calculateProRata($annualPremium, $effectiveDate, $policy->expiry_date);
        $daysRemaining = $this->proRatingService->calculateDaysRemaining($effectiveDate, $policy->expiry_date);

        return [
            'annual' => $annualPremium,
            'prorated' => $proratedPremium,
            'days_remaining' => $daysRemaining,
            'effective_date' => $effectiveDate->toDateString(),
        ];
    }

    protected function calculateMemberRefund(Member $member, Carbon $terminationDate, Carbon $policyExpiry): float
    {
        if ($terminationDate->gte($policyExpiry)) {
            return 0;
        }

        $daysRemaining = $this->proRatingService->calculateDaysRemaining($terminationDate, $policyExpiry);
        $totalDays = $this->proRatingService->calculateDaysRemaining($member->cover_start_date, $policyExpiry);

        if ($totalDays <= 0) {
            return 0;
        }

        $dailyRate = $member->premium / $totalDays;

        return round($dailyRate * $daysRemaining, 2);
    }

    protected function calculateAddonPremiumImpact(Policy $policy, string $addonId, Carbon $effectiveDate): array
    {
        // This would need to look up addon pricing
        // For now, return placeholder
        return [
            'annual' => 0,
            'prorated' => 0,
            'days_remaining' => $this->proRatingService->calculateDaysRemaining($effectiveDate, $policy->expiry_date),
        ];
    }

    protected function calculatePlanChangePremiumImpact(Policy $policy, string $newPlanId, Carbon $effectiveDate): array
    {
        // Calculate current vs new plan premium
        // This would need to recalculate all members under new plan
        $currentTotal = (float) $policy->base_premium;
        $newTotal = 0; // Would need to calculate based on new plan rates

        $difference = $newTotal - $currentTotal;
        $proratedDifference = $this->proRatingService->calculateProRata(
            abs($difference),
            $effectiveDate,
            $policy->expiry_date
        );

        return [
            'current_premium' => $currentTotal,
            'new_premium' => $newTotal,
            'difference' => $difference,
            'prorated_difference' => $difference >= 0 ? $proratedDifference : -$proratedDifference,
        ];
    }

    protected function calculateFinancialImpact(Endorsement $endorsement, Policy $policy): void
    {
        // Financial impact is calculated during specific endorsement creation
        // This method can be used for validation or recalculation if needed
    }

    // =========================================================================
    // SNAPSHOT HELPERS
    // =========================================================================

    protected function captureBeforeSnapshot(Endorsement $endorsement, Policy $policy): void
    {
        $snapshot = [
            'policy' => [
                'status' => $policy->status,
                'plan_id' => $policy->plan_id,
                'base_premium' => $policy->base_premium,
                'addon_premium' => $policy->addon_premium,
                'loading_amount' => $policy->loading_amount,
                'discount_amount' => $policy->discount_amount,
                'total_premium' => $policy->total_premium,
                'gross_premium' => $policy->gross_premium,
                'member_count' => $policy->member_count,
            ],
            'captured_at' => now()->toISOString(),
        ];

        // Include member data if endorsement relates to a member
        if ($endorsement->member_id) {
            $member = $endorsement->member;
            $snapshot['member'] = [
                'id' => $member->id,
                'status' => $member->status,
                'premium' => $member->premium,
                'loading_amount' => $member->loading_amount,
            ];
        }

        $endorsement->captureBeforeSnapshot($snapshot);
    }

    protected function captureAfterSnapshot(Endorsement $endorsement, Policy $policy): void
    {
        $snapshot = [
            'policy' => [
                'status' => $policy->status,
                'plan_id' => $policy->plan_id,
                'base_premium' => $policy->base_premium,
                'addon_premium' => $policy->addon_premium,
                'loading_amount' => $policy->loading_amount,
                'discount_amount' => $policy->discount_amount,
                'total_premium' => $policy->total_premium,
                'gross_premium' => $policy->gross_premium,
                'member_count' => $policy->member_count,
            ],
            'captured_at' => now()->toISOString(),
        ];

        // Include member data if endorsement relates to a member
        if ($endorsement->member_id) {
            $member = $endorsement->member->fresh();
            $snapshot['member'] = [
                'id' => $member->id,
                'status' => $member->status,
                'premium' => $member->premium,
                'loading_amount' => $member->loading_amount,
            ];
        }

        $endorsement->captureAfterSnapshot($snapshot);
    }

    // =========================================================================
    // VALIDATION HELPERS
    // =========================================================================

    protected function validatePolicyForEndorsement(Policy $policy): void
    {
        $allowedStatuses = [
            MedicalConstants::POLICY_STATUS_ACTIVE,
            MedicalConstants::POLICY_STATUS_SUSPENDED,
        ];

        if (!in_array($policy->status, $allowedStatuses)) {
            throw new Exception('Endorsements can only be created for active or suspended policies');
        }
    }

    protected function validateEffectiveDateForPolicy(Carbon $effectiveDate, Policy $policy): void
    {
        // Effective date cannot be before policy inception
        if ($effectiveDate->lt($policy->inception_date)) {
            throw new Exception(
                'Effective date (' . $effectiveDate->toDateString() . ') cannot be before policy start date (' . $policy->inception_date->toDateString() . ')'
            );
        }

        // Effective date cannot be after policy expiry
        if ($effectiveDate->gt($policy->expiry_date)) {
            throw new Exception(
                'Effective date (' . $effectiveDate->toDateString() . ') cannot be after policy expiry date (' . $policy->expiry_date->toDateString() . ')'
            );
        }
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    /**
     * Get endorsements for a policy.
     */
    public function getEndorsementsForPolicy(Policy $policy, array $filters = [])
    {
        $query = Endorsement::forPolicy($policy->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (!empty($filters['from_date'])) {
            $query->effectiveAfter($filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->effectiveBefore($filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get pending endorsements count for a policy.
     */
    public function getPendingEndorsementsCount(Policy $policy): int
    {
        return Endorsement::forPolicy($policy->id)->pending()->count();
    }
}
