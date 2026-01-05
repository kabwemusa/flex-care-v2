<?php

namespace Modules\Medical\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PromoCode;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Models\Member;

class PolicyService
{
    public function __construct(
        protected PremiumService $premiumService
    ) {}

    /**
     * Renew a policy.
     */
 
    public function applyPromoCode(Policy $policy, string $code): bool
    {
        $promoCode = PromoCode::byCode($code)
            ->usable()
            ->first();

        if (!$promoCode) {
            throw new \Exception('Invalid or expired promo code');
        }

        // Validate eligibility
        if (!$promoCode->isEligibleForScheme($policy->scheme_id)) {
            throw new \Exception('Promo code not valid for this scheme');
        }

        if (!$promoCode->isEligibleForPlan($policy->plan_id)) {
            throw new \Exception('Promo code not valid for this plan');
        }

        // Apply
        $policy->promo_code_id = $promoCode->id;
        
        // Get discount rule and calculate discount
        $discountRule = $promoCode->discountRule;
        if ($discountRule) {
            $discountAmount = $discountRule->calculateAdjustment($policy->base_premium);
            $policy->discount_amount = ($policy->discount_amount ?? 0) + $discountAmount;
            
            // Track applied discounts
            $appliedDiscounts = $policy->applied_discounts ?? [];
            $appliedDiscounts[] = [
                'type' => 'promo_code',
                'code' => $code,
                'discount_rule_id' => $discountRule->id,
                'amount' => $discountAmount,
                'applied_at' => now()->toISOString(),
            ];
            $policy->applied_discounts = $appliedDiscounts;
        }

        $policy->save();

        // Increment usage
        $promoCode->incrementUsage();

        return true;
    }


//================================


// =========================================================================
// CREATION (Direct/Migration)
// =========================================================================

public function createPolicy(array $data): Policy
{
    return DB::transaction(function () use ($data) {
        $policy = Policy::create($data);

        if (!empty($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                $policy->policyAddons()->create([
                    'addon_id' => $addon['addon_id'],
                    'addon_rate_id' => $addon['addon_rate_id'] ?? null,
                    'premium' => $addon['premium'] ?? 0,
                    'is_active' => true
                ]);
            }
        }

        // Calculate totals
        $this->premiumService->calculatePolicyPremium($policy);
        
        return $policy;
    });
}

// =========================================================================
// LIFECYCLE MANAGEMENT
// =========================================================================

public function activatePolicy(Policy $policy): Policy
{
    if (!$policy->canActivate()) {
        throw new Exception('Policy cannot be activated. Check status and member count.');
    }

    $policy->activate();

    // Activate all pending members
    $policy->members()
        ->where('status', MedicalConstants::MEMBER_STATUS_PENDING)
        ->update([
            'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
            'status_changed_at' => now(),
            'cover_start_date' => $policy->inception_date,
            'cover_end_date' => $policy->expiry_date,
        ]);

    return $policy->fresh();
}

public function suspendPolicy(Policy $policy, string $reason): Policy
{
    if (!$policy->is_active) {
        throw new Exception('Only active policies can be suspended');
    }

    $policy->suspend($reason);

    // Suspend active members
    $policy->members()->active()->update([
        'status' => MedicalConstants::MEMBER_STATUS_SUSPENDED,
        'status_changed_at' => now(),
        'status_reason' => 'Policy suspended: ' . $reason,
    ]);

    return $policy->fresh();
}

public function reinstatePolicy(Policy $policy): Policy
{
    if ($policy->status !== MedicalConstants::POLICY_STATUS_SUSPENDED) {
        throw new Exception('Only suspended policies can be reinstated');
    }

    $policy->update(['status' => MedicalConstants::POLICY_STATUS_ACTIVE]);

    // Reactivate members suspended due to policy suspension
    $policy->members()
        ->where('status', MedicalConstants::MEMBER_STATUS_SUSPENDED)
        ->where('status_reason', 'like', 'Policy suspended%')
        ->update([
            'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
            'status_changed_at' => now(),
            'status_reason' => null,
        ]);

    return $policy->fresh();
}

public function cancelPolicy(Policy $policy, string $reason): Policy
{
    if (in_array($policy->status, [MedicalConstants::POLICY_STATUS_CANCELLED, MedicalConstants::POLICY_STATUS_EXPIRED])) {
        throw new Exception('Policy is already cancelled or expired');
    }

    $policy->cancel($reason, 'system');

    // Terminate members
    $policy->members()
        ->whereNotIn('status', [MedicalConstants::MEMBER_STATUS_TERMINATED, MedicalConstants::MEMBER_STATUS_DECEASED])
        ->update([
            'status' => MedicalConstants::MEMBER_STATUS_TERMINATED,
            'status_changed_at' => now(),
            'terminated_at' => now(),
            'termination_reason' => 'Policy Cancelled: ' . $reason,
            'cover_end_date' => now(), // Pro-rata stop
            'card_status' => MedicalConstants::CARD_STATUS_BLOCKED,
        ]);

    return $policy->fresh();
}

// =========================================================================
// MID-TERM ADJUSTMENTS (MTA)
// =========================================================================

/**
 * Add a member to an active policy (Mid-term addition).
 */
public function addMemberToPolicy(Policy $policy, array $memberData): Member
{
    if (!$policy->is_active) {
        throw new Exception('Cannot add members to inactive policy');
    }

    return DB::transaction(function () use ($policy, $memberData) {
        $startDate = isset($memberData['join_date']) 
            ? Carbon::parse($memberData['join_date']) 
            : now();

        // Ensure start date is within policy term
        if ($startDate->lt($policy->inception_date)) $startDate = $policy->inception_date;
        if ($startDate->gt($policy->expiry_date)) throw new Exception("Join date cannot be after policy expiry");

        // Create Member
        $member = new Member($memberData);
        $member->policy_id = $policy->id;
        $member->scheme_id = $policy->scheme_id;
        $member->plan_id = $policy->plan_id;
        $member->status = MedicalConstants::MEMBER_STATUS_ACTIVE;
        $member->cover_start_date = $startDate;
        $member->cover_end_date = $policy->expiry_date;
        
        // Calculate age at join date for accurate pricing
        $member->age_at_inception = $member->date_of_birth->diffInYears($startDate);
        
        $member->save();

        // Calculate Premium
        // 1. Get Full Annual/Term Premium
        $basePremium = $this->premiumService->calculatePolicyMemberPremium($member, $policy);
        
        // 2. Prorate if joining mid-term
        $totalDays = $policy->inception_date->diffInDays($policy->expiry_date);
        $daysCovered = $startDate->diffInDays($policy->expiry_date);
        
        if ($totalDays > 0 && $daysCovered < $totalDays) {
            // Apply proration factor
            $factor = $daysCovered / $totalDays;
            $proratedPremium = round($basePremium * $factor, 2);
            
            // Update member with prorated amount for this term
            $member->base_premium = $proratedPremium;
            $member->total_premium = $proratedPremium; // + loadings if any
            $member->save();
        }

        // Update Policy Totals
        $this->premiumService->calculatePolicyPremium($policy);

        return $member;
    });
}

/**
 * Terminate a member from policy (Mid-term removal).
 */
public function terminateMemberFromPolicy(Policy $policy, string $memberId, string $reason, ?string $date = null): Member
{
    return DB::transaction(function () use ($policy, $memberId, $reason, $date) {
        $member = $policy->members()->findOrFail($memberId);
        $termDate = $date ? Carbon::parse($date) : now();

        if ($termDate->lt($policy->inception_date)) throw new Exception("Termination date cannot be before policy start");
        
        $member->update([
            'status' => MedicalConstants::MEMBER_STATUS_TERMINATED,
            'status_changed_at' => now(),
            'terminated_at' => $termDate,
            'termination_reason' => $reason,
            'cover_end_date' => $termDate,
            'card_status' => MedicalConstants::CARD_STATUS_BLOCKED,
        ]);

        // Note: Refund logic would go here (Credit Note generation)
        // For now, we just update the policy totals to reflect future reality if needed, 
        // though usually realized premium remains until credit note is issued.
        
        return $member;
    });
}

// =========================================================================
// RENEWAL
// =========================================================================

public function renewPolicy(Policy $oldPolicy, array $overrides = []): Policy
{
    // ... (Existing renewal logic validation) ...

    return DB::transaction(function () use ($oldPolicy, $overrides) {
        $newInception = $oldPolicy->expiry_date->copy()->addDay();
        $termMonths = $overrides['policy_term_months'] ?? $oldPolicy->policy_term_months;
        $newExpiry = $newInception->copy()->addMonths($termMonths)->subDay();

        $newPolicy = $oldPolicy->replicate(['id', 'policy_number', 'created_at', 'updated_at', 'renewed_to_policy_id']);
        $newPolicy->fill([
            'inception_date' => $newInception,
            'expiry_date' => $newExpiry,
            'renewal_date' => $newExpiry,
            'status' => MedicalConstants::POLICY_STATUS_DRAFT,
            'previous_policy_id' => $oldPolicy->id,
            'renewal_count' => $oldPolicy->renewal_count + 1,
        ]);
        
        // Apply Overrides
        if (isset($overrides['plan_id'])) $newPolicy->plan_id = $overrides['plan_id'];
        if (isset($overrides['rate_card_id'])) $newPolicy->rate_card_id = $overrides['rate_card_id'];
        
        $newPolicy->save();

        // Map Old ID -> New ID for Principals
        $memberMap = [];

        // 1. Copy Principals
        foreach ($oldPolicy->members()->active()->principals()->get() as $oldMember) {
            $newMember = $this->copyMemberForRenewal($newPolicy, $oldMember, null);
            $memberMap[$oldMember->id] = $newMember->id;
            
            if (!$newPolicy->principal_member_id) {
                $newPolicy->setPrincipalMember($newMember);
            }
        }

        // 2. Copy Dependents
        foreach ($oldPolicy->members()->active()->dependents()->get() as $oldMember) {
            $newPrincipalId = $memberMap[$oldMember->principal_member_id] ?? null;
            $this->copyMemberForRenewal($newPolicy, $oldMember, $newPrincipalId);
        }

        // 3. Copy Addons
        foreach ($oldPolicy->policyAddons as $addon) {
            $newPolicy->policyAddons()->create([
                'addon_id' => $addon->addon_id,
                'addon_rate_id' => $addon->addon_rate_id,
                'is_active' => true
            ]);
        }

        // Update Old Policy
        $oldPolicy->update([
            'status' => MedicalConstants::POLICY_STATUS_RENEWED,
            'renewed_to_policy_id' => $newPolicy->id
        ]);

        // Calculate New Premiums
        $this->premiumService->calculatePolicyPremium($newPolicy);

        return $newPolicy->fresh();
    });
}

protected function copyMemberForRenewal(Policy $newPolicy, Member $oldMember, ?string $newPrincipalId): Member
{
    $newMember = $oldMember->replicate([
        'id', 'policy_id', 'principal_member_id', 'created_at', 'updated_at',
        'status', 'card_number', 'card_status' // Reset these
    ]);

    $newMember->policy_id = $newPolicy->id;
    $newMember->principal_member_id = $newPrincipalId;
    $newMember->status = MedicalConstants::MEMBER_STATUS_PENDING;
    $newMember->cover_start_date = $newPolicy->inception_date;
    $newMember->cover_end_date = $newPolicy->expiry_date;
    
    // Recalculate Age
    $newMember->age_at_inception = $newMember->date_of_birth->diffInYears($newPolicy->inception_date);
    
    $newMember->save();

    // Copy Risk Data (Loadings/Exclusions)
    $this->copyMemberRiskData($oldMember, $newMember);

    return $newMember;
}

protected function copyMemberRiskData(Member $source, Member $target): void
{
    // Loadings
    foreach ($source->activeLoadings as $loading) {
        // Only copy permanent or unexpired loadings
        if ($loading->is_permanent || ($loading->end_date && $loading->end_date > $target->cover_start_date)) {
            $newLoading = $loading->replicate(['id', 'member_id', 'created_at', 'updated_at']);
            $newLoading->member_id = $target->id;
            $newLoading->start_date = $target->cover_start_date; // Reset start date to new policy start
            $newLoading->save();
        }
    }

    // Exclusions
    foreach ($source->activeExclusions as $exclusion) {
        if ($exclusion->is_permanent || ($exclusion->end_date && $exclusion->end_date > $target->cover_start_date)) {
            $newExclusion = $exclusion->replicate(['id', 'member_id', 'created_at', 'updated_at']);
            $newExclusion->member_id = $target->id;
            $newExclusion->start_date = $target->cover_start_date;
            $newExclusion->save();
        }
    }
    
    $target->recalculateLoadings();
}


    /**
     * Generate policy certificate.
     */
    public function generateCertificate(Policy $policy): string
    {
        // This would integrate with a PDF generation service
        // For now, return a placeholder
        return "certificates/policy_{$policy->policy_number}.pdf";
    }

    /**
     * Generate member schedule.
     */
    public function generateMemberSchedule(Policy $policy): string
    {
        // This would integrate with a PDF generation service
        return "schedules/schedule_{$policy->policy_number}.pdf";
    }

    /**
     * Check if policy can be activated.
     */
    public function canActivate(Policy $policy): array
    {
        $issues = [];

        if ($policy->underwriting_status !== MedicalConstants::UW_STATUS_APPROVED) {
            $issues[] = 'Policy must be underwriting approved';
        }

        if ($policy->member_count === 0) {
            $issues[] = 'Policy must have at least one member';
        }

        if ($policy->is_corporate && !$policy->group_id) {
            $issues[] = 'Corporate policy must be linked to a group';
        }

        if (!$policy->is_corporate && !$policy->principal_member_id) {
            $issues[] = 'Individual/Family policy must have a principal member';
        }

        return [
            'can_activate' => empty($issues),
            'issues' => $issues,
        ];
    }
}