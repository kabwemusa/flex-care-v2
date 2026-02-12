<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\Application;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;

class MemberPortalController extends Controller
{
    use ApiResponse;

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Get dashboard summary.
     * GET /v1/medical/member/portal/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');
        $member->loadMissing([
            'policy.plan',
            'policy.scheme',
            'dependents',
        ]);

        $policy = $member->policy;

        // Get recent claims
        $recentClaims = $policy ? Claim::where('policy_id', $policy->id)
            ->where(function ($q) use ($member) {
                $q->where('member_id', $member->id)
                  ->orWhereIn('member_id', $member->dependents->pluck('id'));
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'claim_number' => $c->claim_number,
                'status' => $c->status,
                'status_label' => $c->status_label,
                'claimed_amount' => $c->claimed_amount,
                'approved_amount' => $c->approved_amount,
                'claim_date' => $c->claim_date?->toDateString(),
            ]) : [];

        // Calculate benefit usage summary (simplified)
        $benefitSummary = [];
        if ($policy && $policy->plan) {
            // TODO: Implement actual benefit tracking
            $benefitSummary = [
                'outpatient' => ['used' => 0, 'limit' => 100000, 'percentage' => 0],
                'inpatient' => ['used' => 0, 'limit' => 500000, 'percentage' => 0],
                'maternity' => ['used' => 0, 'limit' => 150000, 'percentage' => 0],
            ];
        }

        return $this->success([
            'member' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'card_number' => $member->card_number,
                'card_status' => $member->card_status,
                'card_status_label' => $member->card_status_label,
                'is_in_waiting_period' => $member->is_in_waiting_period,
                'waiting_days_remaining' => $member->waiting_days_remaining,
            ],
            'policy' => $policy ? [
                'id' => $policy->id,
                'policy_number' => $policy->policy_number,
                'status' => $policy->status,
                'status_label' => $policy->status_label,
                'plan_name' => $policy->plan?->name,
                'scheme_name' => $policy->scheme?->name,
                'inception_date' => $policy->inception_date?->toDateString(),
                'expiry_date' => $policy->expiry_date?->toDateString(),
                'days_until_expiry' => $policy->expiry_date?->diffInDays(now(), false),
            ] : null,
            'dependents' => $member->dependents->map(fn($d) => [
                'id' => $d->id,
                'full_name' => $d->full_name,
                'relationship' => $d->relationship_label,
                'age' => $d->age,
                'status' => $d->status,
                'card_status' => $d->card_status,
            ]),
            'recent_claims' => $recentClaims,
            'benefit_summary' => $benefitSummary,
            'alerts' => $this->getAlerts($member, $policy),
        ], 'Dashboard loaded');
    }

    /**
     * Generate alerts for member.
     */
    protected function getAlerts(Member $member, ?Policy $policy): array
    {
        $alerts = [];

        // Card not active
        if ($member->card_status !== MedicalConstants::CARD_STATUS_ACTIVE) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Card Not Active',
                'message' => 'Your member card is not yet active. Contact support if needed.',
            ];
        }

        // In waiting period
        if ($member->is_in_waiting_period) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Waiting Period',
                'message' => "You're in the waiting period. {$member->waiting_days_remaining} days remaining.",
            ];
        }

        // Policy expiring soon
        if ($policy && $policy->expiry_date) {
            $daysUntilExpiry = now()->diffInDays($policy->expiry_date, false);
            if ($daysUntilExpiry > 0 && $daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Policy Expiring Soon',
                    'message' => "Your policy expires in {$daysUntilExpiry} days. Contact us to renew.",
                ];
            }
        }

        // No password set (encourage security)
        if (!$member->portal_password) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Set a Password',
                'message' => 'Set a password for faster login next time.',
                'action' => 'set_password',
            ];
        }

        return $alerts;
    }

    // =========================================================================
    // POLICIES
    // =========================================================================

    /**
     * Get policy details.
     * GET /v1/medical/member/portal/policy
     */
    public function policy(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');
        $member->loadMissing([
            'policy.plan.planBenefits.benefit.category',
            'policy.plan.planAddons.addon',
            'policy.scheme',
            'dependents',
        ]);

        $policy = $member->policy;

        if (!$policy) {
            return $this->error('No active policy found', 404);
        }

        return $this->success([
            'policy' => [
                'id' => $policy->id,
                'policy_number' => $policy->policy_number,
                'status' => $policy->status,
                'status_label' => $policy->status_label,
                'inception_date' => $policy->inception_date?->toDateString(),
                'expiry_date' => $policy->expiry_date?->toDateString(),
                'premium' => $policy->total_premium,
                'premium_frequency' => $policy->premium_frequency,
                'currency' => $policy->currency ?? 'KES',
            ],
            'plan' => $policy->plan ? [
                'id' => $policy->plan->id,
                'name' => $policy->plan->name,
                'tier_level' => $policy->plan->tier_level,
                'plan_type' => $policy->plan->plan_type,
                'network_type' => $policy->plan->network_type,
            ] : null,
            'scheme' => $policy->scheme ? [
                'id' => $policy->scheme->id,
                'name' => $policy->scheme->name,
            ] : null,
            'benefits' => $policy->plan?->planBenefits->map(fn($pb) => [
                'name' => $pb->benefit?->name,
                'category' => $pb->benefit?->category?->name,
                'is_covered' => $pb->is_covered,
                'limit' => $pb->formatted_display_value,
                'copay' => $pb->copay_display,
            ]) ?? [],
            'members' => collect([$member, ...$member->dependents])->map(fn($m) => [
                'id' => $m->id,
                'member_number' => $m->member_number,
                'full_name' => $m->full_name,
                'relationship' => $m->relationship_label ?? 'Principal',
                'member_type' => $m->member_type_label,
                'date_of_birth' => $m->date_of_birth?->toDateString(),
                'age' => $m->age,
                'card_number' => $m->card_number,
                'card_status' => $m->card_status,
                'card_status_label' => $m->card_status_label,
                'status' => $m->status,
            ]),
        ], 'Policy details retrieved');
    }

    /**
     * Get ID card data for a member.
     * GET /v1/medical/member/portal/id-card/{memberId?}
     */
    public function idCard(Request $request, ?string $memberId = null): JsonResponse
    {
        $member = $request->attributes->get('member');
        $member->loadMissing(['policy.plan', 'policy.scheme', 'dependents']);

        // If memberId provided, verify it's the principal or a dependent
        $targetMember = $member;
        if ($memberId) {
            if ($memberId === $member->id) {
                $targetMember = $member;
            } else {
                $targetMember = $member->dependents->firstWhere('id', $memberId);
                if (!$targetMember) {
                    return $this->error('Member not found', 404);
                }
            }
        }

        if (!$targetMember->card_number) {
            return $this->error('Card not yet issued', 400);
        }

        $policy = $member->policy;

        return $this->success([
            'card' => [
                'member_number' => $targetMember->member_number,
                'card_number' => $targetMember->card_number,
                'full_name' => $targetMember->full_name,
                'date_of_birth' => $targetMember->date_of_birth?->format('d M Y'),
                'gender' => $targetMember->gender_label,
                'relationship' => $targetMember->relationship_label ?? 'Principal',
                'card_issued_date' => $targetMember->card_issued_date?->format('d M Y'),
                'card_expiry_date' => $targetMember->card_expiry_date?->format('d M Y'),
            ],
            'policy' => [
                'policy_number' => $policy->policy_number,
                'plan_name' => $policy->plan?->name,
                'scheme_name' => $policy->scheme?->name,
                'inception_date' => $policy->inception_date?->format('d M Y'),
                'expiry_date' => $policy->expiry_date?->format('d M Y'),
            ],
            'issuer' => [
                'name' => 'FlexCare Insurance',
                'contact' => '+254 700 000 000',
                'email' => 'support@flexcare.co.ke',
                'website' => 'www.flexcare.co.ke',
            ],
        ], 'ID card data retrieved');
    }

    // =========================================================================
    // CLAIMS
    // =========================================================================

    /**
     * List claims.
     * GET /v1/medical/member/portal/claims
     */
    public function claims(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');
        $member->loadMissing(['policy', 'dependents']);

        $policy = $member->policy;

        if (!$policy) {
            return $this->success(['claims' => []], 'No policy found');
        }

        $memberIds = collect([$member->id, ...$member->dependents->pluck('id')]);

        $claims = Claim::where('policy_id', $policy->id)
            ->whereIn('member_id', $memberIds)
            ->with('member:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->success([
            'claims' => $claims->items()->map(fn($c) => [
                'id' => $c->id,
                'claim_number' => $c->claim_number,
                'member_name' => $c->member?->short_name,
                'claim_type' => $c->claim_type,
                'claim_type_label' => $c->claim_type_label,
                'status' => $c->status,
                'status_label' => $c->status_label,
                'provider_name' => $c->provider_name,
                'diagnosis' => $c->diagnosis,
                'claimed_amount' => $c->claimed_amount,
                'approved_amount' => $c->approved_amount,
                'claim_date' => $c->claim_date?->toDateString(),
                'created_at' => $c->created_at?->toDateString(),
            ]),
            'pagination' => [
                'current_page' => $claims->currentPage(),
                'total_pages' => $claims->lastPage(),
                'total' => $claims->total(),
            ],
        ], 'Claims retrieved');
    }

    /**
     * Get claim details.
     * GET /v1/medical/member/portal/claims/{id}
     */
    public function claimDetail(Request $request, string $id): JsonResponse
    {
        $member = $request->attributes->get('member');
        $member->loadMissing(['policy', 'dependents']);

        $memberIds = collect([$member->id, ...$member->dependents->pluck('id')]);

        $claim = Claim::with(['member', 'claimLines', 'notes' => function ($q) {
            $q->where('is_internal', false)->orderBy('created_at', 'desc');
        }])
            ->where('policy_id', $member->policy?->id)
            ->whereIn('member_id', $memberIds)
            ->find($id);

        if (!$claim) {
            return $this->error('Claim not found', 404);
        }

        return $this->success([
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'member_name' => $claim->member?->full_name,
                'claim_type' => $claim->claim_type,
                'claim_type_label' => $claim->claim_type_label,
                'status' => $claim->status,
                'status_label' => $claim->status_label,
                'provider_name' => $claim->provider_name,
                'provider_type' => $claim->provider_type,
                'diagnosis' => $claim->diagnosis,
                'treatment_notes' => $claim->treatment_notes,
                'claimed_amount' => $claim->claimed_amount,
                'approved_amount' => $claim->approved_amount,
                'member_paid' => $claim->member_paid,
                'claim_date' => $claim->claim_date?->toDateString(),
                'admission_date' => $claim->admission_date?->toDateString(),
                'discharge_date' => $claim->discharge_date?->toDateString(),
                'created_at' => $claim->created_at?->toDateTimeString(),
            ],
            'lines' => $claim->claimLines->map(fn($l) => [
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit_amount' => $l->unit_amount,
                'claimed_amount' => $l->claimed_amount,
                'approved_amount' => $l->approved_amount,
                'rejection_reason' => $l->rejection_reason,
            ]),
            'notes' => $claim->notes->map(fn($n) => [
                'content' => $n->content,
                'created_at' => $n->created_at?->toDateTimeString(),
            ]),
        ], 'Claim details retrieved');
    }

    // =========================================================================
    // APPLICATIONS
    // =========================================================================

    /**
     * List member's applications.
     * GET /v1/medical/member/portal/applications
     */
    public function applications(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');

        // Find applications by email
        $applications = Application::where('email', $member->email)
            ->with('plan:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'application_number' => $a->application_number,
                'plan_name' => $a->plan?->name,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'total_premium' => $a->total_premium,
                'members_count' => $a->members_count,
                'created_at' => $a->created_at?->toDateString(),
            ]);

        return $this->success([
            'applications' => $applications,
        ], 'Applications retrieved');
    }

    // =========================================================================
    // PROFILE
    // =========================================================================

    /**
     * Get profile.
     * GET /v1/medical/member/portal/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');

        return $this->success([
            'profile' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'title' => $member->title,
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'gender' => $member->gender,
                'gender_label' => $member->gender_label,
                'marital_status' => $member->marital_status,
                'national_id' => $member->national_id ? $this->maskString($member->national_id) : null,
                'email' => $member->email,
                'phone' => $member->phone,
                'mobile' => $member->mobile,
                'address' => $member->address,
                'city' => $member->city,
            ],
            'security' => [
                'has_password' => $member->portal_password !== null,
                'password_set_at' => $member->portal_password_set_at?->toDateString(),
                'last_login_at' => $member->portal_last_login_at?->toDateTimeString(),
            ],
        ], 'Profile retrieved');
    }

    /**
     * Update profile (limited fields).
     * PUT /v1/medical/member/portal/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
        ]);

        $member = $request->attributes->get('member');

        $member->update($request->only(['phone', 'mobile', 'address', 'city']));

        return $this->success(null, 'Profile updated successfully');
    }

    /**
     * Mask sensitive string.
     */
    protected function maskString(string $value, int $showLast = 4): string
    {
        $length = strlen($value);
        if ($length <= $showLast) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - $showLast) . substr($value, -$showLast);
    }
}
