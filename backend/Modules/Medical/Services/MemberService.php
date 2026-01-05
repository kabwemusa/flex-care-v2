<?php

namespace Modules\Medical\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\MemberLoading;
use Modules\Medical\Models\MemberExclusion;
use Modules\Medical\Models\MemberDocument;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;
use Exception;

class MemberService
{
    public function __construct(
        protected PremiumService $premiumService
    ) {}

    // =========================================================================
    // CRUD & ONBOARDING
    // =========================================================================

    public function createMember(array $data): Member
    {
        return DB::transaction(function () use ($data) {
            $policy = Policy::with('plan')->findOrFail($data['policy_id']);

            if (!$policy->canAddMember()) {
                throw new Exception('Cannot add members to this policy (Status: ' . $policy->status . ')');
            }

            // validate Plan Limits for Dependents
            if (($data['member_type'] ?? '') !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                $currentDependents = $policy->members()
                    ->where('principal_member_id', $data['principal_id'] ?? null)
                    ->count();
                
                if ($currentDependents >= $policy->plan->max_dependents) {
                    throw new Exception("Maximum dependents limit ({$policy->plan->max_dependents}) reached for this family.");
                }
            }

            // Defaults
            $data['cover_start_date'] = $data['cover_start_date'] ?? now();
            $data['cover_end_date'] = $data['cover_end_date'] ?? $policy->expiry_date;
            $data['status'] = MedicalConstants::MEMBER_STATUS_ACTIVE;

            $member = Member::create($data);

            // 1. Calculate Waiting Periods (Real-world logic)
            $this->applyWaitingPeriods($member);

            // 2. Calculate Premium (Prorated)
            $this->premiumService->calculatePolicyMemberPremium($member, $policy);

            // 3. Update Policy Totals
            $this->premiumService->calculatePolicyPremium($policy);

            return $member;
        });
    }

    public function updateMember(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data) {
            // Check if age/gender/plan parameters changed
            $recalcRequired = false;
            if (
                (isset($data['date_of_birth']) && $data['date_of_birth'] !== $member->date_of_birth?->toDateString()) ||
                (isset($data['gender']) && $data['gender'] !== $member->gender) || 
                (isset($data['salary_band']) && $data['salary_band'] !== $member->salary_band)
            ) {
                $recalcRequired = true;
            }

            $member->update($data);

            if ($recalcRequired) {
                // Update age (if virtual accessor doesn't handle it dynamically for DB persistence)
                if (isset($data['date_of_birth'])) {
                    $member->age = Carbon::parse($data['date_of_birth'])->age;
                    $member->save();
                }

                $this->premiumService->calculatePolicyMemberPremium($member);
                $this->premiumService->calculatePolicyPremium($member->policy);
            }

            return $member;
        });
    }

    // =========================================================================
    // RISK MANAGEMENT (Loadings & Exclusions)
    // =========================================================================

    public function addLoading(Member $member, array $data): MemberLoading
    {
        return DB::transaction(function () use ($member, $data) {
            // Logic: Percentage Loadings are based on Base Premium
            if ($data['loading_type'] === MedicalConstants::LOADING_TYPE_PERCENTAGE) {
                $data['loading_amount'] = round($member->base_premium * ($data['loading_value'] / 100), 2);
            } else {
                $data['loading_amount'] = $data['loading_value'];
            }

            $loading = $member->loadings()->create($data);

            // Flag member risk
            $member->update(['has_pre_existing_conditions' => true]);

            // Recalculate Premiums
            $this->premiumService->calculatePolicyMemberPremium($member);
            $this->premiumService->calculatePolicyPremium($member->policy);

            return $loading;
        });
    }

    public function removeLoading(MemberLoading $loading, string $reason): void
    {
        DB::transaction(function () use ($loading, $reason) {
            $member = $loading->member;
            $loading->remove($reason); // Custom method on model to set end_date/status

            $this->premiumService->calculatePolicyMemberPremium($member);
            $this->premiumService->calculatePolicyPremium($member->policy);
        });
    }

    // =========================================================================
    // LIFECYCLE (Termination & Cards)
    // =========================================================================

    public function terminateMember(Member $member, string $reason, ?string $notes = null): Member
    {
        return DB::transaction(function () use ($member, $reason, $notes) {
            $member->terminate($reason, $notes);

            // Real-world rule: If Principal dies/leaves, dependents usually terminate OR 
            // the spouse must be promoted to Principal (Succession). 
            // For this implementation, we terminate dependents to be safe.
            if ($member->is_principal) {
                $member->dependents()
                    ->active()
                    ->each(function($dep) use ($reason) {
                        $dep->terminate('Principal Terminated', "Auto-terminated due to principal: $reason");
                    });
            }

            // Update Policy Counts & Premium
            $member->policy->updateMemberCounts();
            $this->premiumService->calculatePolicyPremium($member->policy);

            return $member->fresh();
        });
    }

    public function issueCard(Member $member): Member
    {
        if ($member->card_number) {
            throw new Exception("Member already has a card: {$member->card_number}");
        }

        // Generate Format: SCHEME-PLAN-YEAR-RANDOM
        // e.g., AME-GLD-2025-123456
        $schemeCode = strtoupper(substr($member->policy->scheme->code ?? 'GEN', 0, 3));
        $planCode = strtoupper(substr($member->policy->plan->code ?? 'STD', 0, 3));
        $year = now()->year;
        $unique = str_pad($member->id, 6, '0', STR_PAD_LEFT); // simplified unique logic
        
        $cardNum = "{$schemeCode}-{$planCode}-{$year}-{$unique}";

        $member->update([
            'card_number' => $cardNum,
            'card_status' => MedicalConstants::CARD_STATUS_ACTIVE,
            'card_issued_date' => now(),
            'card_expiry_date' => $member->policy->expiry_date,
        ]);

        return $member;
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    public function uploadDocument(Member $member, array $data, $file): MemberDocument
    {
        $path = $file->store("members/{$member->id}/documents", 'private');

        return $member->documents()->create([
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_active' => true
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function applyWaitingPeriods(Member $member): void
    {
        // Check Plan rules
        // e.g., if Plan has general waiting period of 3 months
        $plan = $member->policy->plan;
        
        if ($plan && $plan->waiting_period_months > 0) {
            $endDate = $member->cover_start_date->copy()->addMonths($plan->waiting_period_months);
            
            $member->is_in_waiting_period = true;
            $member->waiting_period_end_date = $endDate;
            $member->save();
        }
    }
}