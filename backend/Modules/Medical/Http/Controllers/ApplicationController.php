<?php

namespace Modules\Medical\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Mail\QuoteEmail;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Http\Requests\ApplicationMemberRequest;
use Modules\Medical\Http\Requests\ApplicationRequest;
use Modules\Medical\Http\Resources\ApplicationListResource;
use Modules\Medical\Http\Resources\ApplicationMemberResource;
use Modules\Medical\Http\Resources\ApplicationResource;
use Modules\Medical\Http\Resources\PolicyResource;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationAddon;
use Modules\Medical\Models\ApplicationDocument;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\RateCard;
use Modules\Medical\Services\ApplicationService;
use Modules\Medical\Services\PremiumService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApplicationService $applicationService,
        protected PremiumService $premiumService,
    ) {}

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * List applications with filtering.
     * GET /v1/medical/applications
     */
    public function index(): JsonResponse
    {
        $query = Application::query()
            ->with([
                'scheme:id,code,name',
                'plan:id,code,name',
                'group:id,code,name',
            ])
            ->withCount('activeMembers');

        $query->when(request('search'), function ($q, $search) {
            $q->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        });

        $query->when(request('status'), fn ($q, $v) => $q->where('status', $v))
              ->when(request('application_type'), fn ($q, $v) => $q->where('application_type', $v))
              ->when(request('policy_type'), fn ($q, $v) => $q->where('policy_type', $v))
              ->when(request('scheme_id'), fn ($q, $v) => $q->where('scheme_id', $v))
              ->when(request('plan_id'), fn ($q, $v) => $q->where('plan_id', $v))
              ->when(request('group_id'), fn ($q, $v) => $q->where('group_id', $v));

        $query->when(request('pending_underwriting'), fn ($q) => $q->pendingUnderwriting())
              ->when(request('pending_conversion'), fn ($q) => $q->pendingConversion())
              ->when(request('expired'), fn ($q) => $q->expired())
              ->when(request('corporate_only'), fn ($q) => $q->corporate())
              ->when(request('individual_only'), fn ($q) => $q->individual());

        $query->when(request('created_from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
              ->when(request('created_to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        $allowedSorts = ['created_at', 'application_number', 'status'];
        $sortBy    = in_array(request('sort_by'), $allowedSorts) ? request('sort_by') : 'created_at';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage      = min((int) request('per_page', 20), 100);
        $applications = request()->has('cursor')
            ? $query->orderBy('id')->cursorPaginate($perPage)
            : $query->paginate($perPage);

        return $this->paginated(
            ApplicationListResource::collection($applications),
            'Applications retrieved'
        );
    }

    /**
     * Create a new application.
     * POST /v1/medical/applications
     */
    public function store(ApplicationRequest $request): JsonResponse
    {
        // Service already wraps creation in its own DB::transaction — no need to double-wrap.
        $application = $this->applicationService->createApplication($request->validated());

        return $this->success(new ApplicationResource($application), 'Application created', 201);
    }

    /**
     * Show application details.
     * GET /v1/medical/applications/{id}
     */
    public function show(string $id): JsonResponse
    {
        $application = Application::with([
            'scheme',
            'plan.planBenefits.benefit',
            'rateCard',
            'group.primaryContact',
            'renewalOfPolicy:id,policy_number',
            'convertedPolicy:id,policy_number',
            'activeAddons.addon',
            'documents' => fn ($q) => $q->active()->latest(),
        ])
        ->withCount(['activeMembers', 'principals', 'dependents'])
        ->findOrFail($id);

        return $this->success(new ApplicationResource($application), 'Application retrieved');
    }

    /**
     * Update application.
     * PUT /v1/medical/applications/{id}
     */
    public function update(ApplicationRequest $request, string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($request, $id) {
            $application = Application::findOrFail($id);

            if (!$application->can_be_edited) {
                throw new BusinessException('Application cannot be edited in current status.');
            }

            $application->update($request->validated());

            if ($request->hasAny(['plan_id', 'rate_card_id', 'billing_frequency'])) {
                $this->premiumService->calculateApplicationPremium($application);
            }

            return $application->fresh(['scheme', 'plan', 'rateCard', 'activeMembers', 'activeAddons']);
        });

        return $this->success(new ApplicationResource($application), 'Application updated');
    }

    /**
     * Delete application (drafts only).
     * DELETE /v1/medical/applications/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if (!$application->is_draft) {
                throw new BusinessException('Only draft applications can be deleted.');
            }

            $application->members()->delete();
            $application->addons()->delete();
            $application->documents()->delete();
            $application->delete();
        });

        return $this->success(null, 'Application deleted');
    }

    // =========================================================================
    // WORKFLOW ACTIONS
    // =========================================================================

    /**
     * Calculate/recalculate application premium.
     * POST /v1/medical/applications/{id}/calculate-premium
     */
    public function calculatePremium(string $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $this->ensureMandatoryAddons($application);
        $breakdown = $this->premiumService->calculateApplicationPremium($application);

        return $this->success($breakdown, 'Premium calculated');
    }

    /**
     * Mark application as quoted.
     * POST /v1/medical/applications/{id}/quote
     */
    public function markAsQuoted(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            return $this->applicationService->markAsQuoted(Application::findOrFail($id));
        });

        return $this->success(new ApplicationResource($application), 'Application marked as quoted');
    }

    /**
     * Download quote as PDF.
     * GET /v1/medical/applications/{id}/quote/download
     */
    public function downloadQuote(string $id): JsonResponse
    {
        $application = Application::with([
            'scheme', 'plan', 'rateCard', 'group', 'activeMembers', 'activeAddons.addon',
        ])->findOrFail($id);

        $allowedStatuses = [
            MedicalConstants::APPLICATION_STATUS_QUOTED,
            MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
            MedicalConstants::APPLICATION_STATUS_APPROVED,
        ];

        if (!in_array($application->status, $allowedStatuses)) {
            throw new BusinessException('Quote can only be downloaded after it has been generated.');
        }

        $activeMembers = $application->activeMembers;

        $quoteData = [
            'application_number' => $application->application_number,
            'application_type'   => $application->application_type,
            'policy_type'        => $application->policy_type,
            'quote_date'         => $application->quoted_at,
            'valid_until'        => $application->quote_valid_until,
            'applicant_name'     => $application->applicant_name ?? $application->contact_name,
            'contact_email'      => $application->contact_email,
            'contact_phone'      => $application->contact_phone,
            'group'              => $application->group ? [
                'name' => $application->group->name,
                'code' => $application->group->code,
            ] : null,
            'plan' => [
                'scheme' => $application->scheme->name ?? '',
                'name'   => $application->plan->name ?? '',
                'tier'   => $application->plan->tier_level ?? '',
            ],
            'member_summary' => [
                'total'      => $activeMembers->count(),
                'principals' => $activeMembers->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL)->count(),
                'spouses'    => $activeMembers->where('member_type', MedicalConstants::MEMBER_TYPE_SPOUSE)->count(),
                'children'   => $activeMembers->where('member_type', MedicalConstants::MEMBER_TYPE_CHILD)->count(),
                'parents'    => $activeMembers->where('member_type', MedicalConstants::MEMBER_TYPE_PARENT)->count(),
            ],
            'members' => $activeMembers->map(fn ($m) => [
                'name'              => $m->full_name,
                'member_type'       => $m->member_type,
                'member_type_label' => $m->member_type_label,
                'relationship'      => $m->relationship,
                'age'               => $m->age_at_inception ?? $m->age,
                'gender'            => $m->gender,
                'base_premium'      => (float) $m->base_premium,
                'loading_amount'    => (float) $m->loading_amount,
                'total_premium'     => (float) $m->total_premium,
                'has_loadings'      => !empty($m->applied_loadings),
                'loadings'          => collect($m->applied_loadings ?? [])->map(fn ($l) => [
                    'condition' => $l['condition_name'] ?? $l['name'] ?? 'Loading',
                    'type'      => $l['loading_type'] ?? 'fixed',
                    'value'     => $l['value'] ?? $l['loading_value'] ?? 0,
                    'amount'    => $l['loading_amount'] ?? 0,
                ])->values(),
            ])->values(),
            'addons' => $application->activeAddons->map(fn ($a) => [
                'name'         => $a->addon_name ?? ($a->addon->name ?? ''),
                'premium'      => (float) $a->premium,
                'is_mandatory' => $a->is_mandatory ?? false,
            ])->values(),
            'premium_breakdown' => [
                'base_premium'    => (float) $application->base_premium,
                'addon_premium'   => (float) $application->addon_premium,
                'loading_amount'  => (float) $application->loading_amount,
                'discount_amount' => (float) $application->discount_amount,
                'total_premium'   => (float) $application->total_premium,
                'tax_rate'        => config('medical.tax_rate', 0.05),
                'tax_amount'      => (float) $application->tax_amount,
                'gross_premium'   => (float) $application->gross_premium,
                'currency'        => $application->currency,
                'billing_frequency' => $application->billing_frequency,
            ],
            'policy_details' => [
                'policy_term_months' => $application->policy_term_months,
                'proposed_start_date' => $application->proposed_start_date,
                'proposed_end_date'   => $application->proposed_end_date,
            ],
        ];

        return response()->json($quoteData);
    }

    /**
     * Email quote to customer.
     * POST /v1/medical/applications/{id}/quote/email
     */
    public function emailQuote(string $id): JsonResponse
    {
        $application = Application::with(['scheme', 'plan', 'members', 'addons'])->findOrFail($id);

        $allowedStatuses = [
            MedicalConstants::APPLICATION_STATUS_QUOTED,
            MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
            MedicalConstants::APPLICATION_STATUS_APPROVED,
        ];

        if (!in_array($application->status, $allowedStatuses)) {
            throw new BusinessException('Quote can only be emailed after it has been generated.');
        }

        $validated = request()->validate([
            'email'   => 'required|email',
            'message' => 'nullable|string|max:1000',
        ]);

        Mail::to($validated['email'])->send(new QuoteEmail($application, $validated['message'] ?? null));

        return $this->success([
            'email'              => $validated['email'],
            'application_number' => $application->application_number,
            'sent_at'            => now()->toIso8601String(),
        ], 'Quote email sent successfully');
    }

    /**
     * Submit application for underwriting.
     * POST /v1/medical/applications/{id}/submit
     */
    public function submit(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            return $this->applicationService->submitForUnderwriting(
                Application::findOrFail($id),
                request()->user()
            );
        });

        return $this->success(new ApplicationResource($application), 'Application submitted for underwriting');
    }

    /**
     * Start underwriting process.
     * POST /v1/medical/applications/{id}/start-underwriting
     */
    public function startUnderwriting(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            $application    = Application::findOrFail($id);
            $underwriterId  = request('underwriter_id') ?? Str::uuid()->toString();

            return $this->applicationService->startUnderwriting($application, $underwriterId);
        });

        return $this->success(new ApplicationResource($application), 'Underwriting started');
    }

    /**
     * Regenerate quote after underwriting changes.
     * POST /v1/medical/applications/{id}/regenerate-quote
     */
    public function regenerateQuote(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if (!in_array($application->status, [
                MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
                MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            ])) {
                throw new BusinessException('Can only regenerate quote during underwriting.');
            }

            $this->premiumService->calculateApplicationPremium($application);

            $application->update([
                'quoted_at'         => now(),
                'quote_valid_until' => now()->addDays(config('medical.quote.validity_days', 14)),
            ]);

            return $application->fresh(['scheme', 'plan', 'rateCard', 'activeMembers', 'activeAddons']);
        });

        return $this->success(new ApplicationResource($application), 'Quote regenerated with updated terms');
    }

    /**
     * Refer application for further review.
     * POST /v1/medical/applications/{id}/refer
     */
    public function refer(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if (!request('reason')) {
                throw new BusinessException('Referral reason is required.');
            }

            return $this->applicationService->referApplication(
                $application,
                request('underwriter_id') ?? $application->underwriter_id,
                request('reason')
            );
        });

        return $this->success(new ApplicationResource($application), 'Application referred for review');
    }

    // =========================================================================
    // APPROVAL WORKFLOW ENDPOINTS
    // =========================================================================

    /**
     * Get approval status for an application.
     * GET /v1/medical/applications/{id}/approval-status
     */
    public function approvalStatus(string $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $status      = $this->applicationService->getApprovalStatus($application);

        return $this->success($status, 'Approval status retrieved');
    }

    /**
     * Approve application at current approval step.
     * POST /v1/medical/applications/{id}/approval/approve
     */
    public function approvalApprove(string $id): JsonResponse
    {
        $result  = DB::transaction(fn () => $this->applicationService->approveApplicationStep(
            Application::findOrFail($id),
            request()->user(),
            request('comments')
        ));

        $message = $result['is_final'] ? 'Application fully approved' : 'Step approved, moved to next step';

        return $this->success([
            'application'     => new ApplicationResource($result['application']),
            'approval_status' => $result['application']->getApprovalProgress(),
            'is_final'        => $result['is_final'],
        ], $message);
    }

    /**
     * Reject application at current approval step.
     * POST /v1/medical/applications/{id}/approval/reject
     */
    public function approvalReject(string $id): JsonResponse
    {
        if (!request('reason')) {
            throw new BusinessException('Rejection reason is required.');
        }

        $result = DB::transaction(fn () => $this->applicationService->rejectApplicationStep(
            Application::findOrFail($id),
            request()->user(),
            request('reason')
        ));

        return $this->success([
            'application'     => new ApplicationResource($result['application']),
            'approval_status' => $result['application']->getApprovalProgress(),
        ], 'Application rejected');
    }

    /**
     * Return application for amendment at current approval step.
     * POST /v1/medical/applications/{id}/approval/return
     */
    public function approvalReturn(string $id): JsonResponse
    {
        if (!request('reason')) {
            throw new BusinessException('Return reason is required.');
        }

        $result = DB::transaction(fn () => $this->applicationService->returnApplicationStep(
            Application::findOrFail($id),
            request()->user(),
            request('reason')
        ));

        return $this->success([
            'application'     => new ApplicationResource($result['application']),
            'approval_status' => $result['application']->getApprovalProgress(),
        ], 'Application returned for amendment');
    }

    /**
     * Check if current user can approve the application.
     * GET /v1/medical/applications/{id}/can-approve
     */
    public function canApprove(string $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        return $this->success([
            'can_approve'     => $this->applicationService->canUserApprove($application, request()->user()),
            'approval_status' => $application->getApprovalProgress(),
        ], 'Approval permission checked');
    }

    /**
     * Customer accepts the quote.
     * POST /v1/medical/applications/{id}/accept
     */
    public function accept(string $id): JsonResponse
    {
        $application = DB::transaction(fn () => $this->applicationService->acceptQuote(
            Application::findOrFail($id),
            request('acceptance_reference')
        ));

        return $this->success(new ApplicationResource($application), 'Quote accepted');
    }

    /**
     * Convert application to policy.
     * POST /v1/medical/applications/{id}/convert
     */
    public function convert(string $id): JsonResponse
    {
        $policy = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);
            $issuedBy    = Auth::user()->id ?? $application->underwriter_id;

            return $this->applicationService->convertToPolicy($application, $issuedBy);
        });

        return $this->success(new PolicyResource($policy), 'Application converted to policy', 201);
    }

    /**
     * Cancel application.
     * POST /v1/medical/applications/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if ($application->is_converted) {
                throw new BusinessException('Cannot cancel a converted application.');
            }

            $application->cancel(request('reason'));

            return $application->fresh();
        });

        return $this->success(new ApplicationResource($application), 'Application cancelled');
    }

    // =========================================================================
    // MEMBERS
    // =========================================================================

    /**
     * List application members.
     * GET /v1/medical/applications/{id}/members
     */
    public function members(string $id): JsonResponse
    {
        $members = ApplicationMember::where('application_id', $id)
            ->with(['principal:id,first_name,last_name'])
            ->orderBy('member_type')
            ->orderBy('created_at')
            ->paginate(request('per_page', 20));

        return $this->paginated(ApplicationMemberResource::collection($members), 'Members retrieved');
    }

    /**
     * Add member to application.
     * POST /v1/medical/applications/{id}/members
     */
    public function addMember(ApplicationMemberRequest $request, string $id): JsonResponse
    {
        $member = DB::transaction(function () use ($request, $id) {
            $application = Application::findOrFail($id);

            if (!$application->can_be_edited) {
                throw new BusinessException('Cannot add members to application in current status.');
            }

            $data                   = $request->validated();
            $data['application_id'] = $id;

            if (!empty($data['date_of_birth'])) {
                $data['age_at_inception'] = Carbon::parse($data['date_of_birth'])
                    ->diffInYears($application->proposed_start_date);
            }

            $member = ApplicationMember::create($data);

            $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
            $application->updateMemberCounts();
            $this->premiumService->calculateApplicationPremium($application);

            return $member->fresh(['application', 'principal']);
        });

        return $this->success(new ApplicationMemberResource($member), 'Member added', 201);
    }

    /**
     * Update application member.
     * PUT /v1/medical/applications/{appId}/members/{memberId}
     */
    public function updateMember(ApplicationMemberRequest $request, string $appId, string $memberId): JsonResponse
    {
        $member = DB::transaction(function () use ($request, $appId, $memberId) {
            $member      = ApplicationMember::where('application_id', $appId)->findOrFail($memberId);
            $application = $member->application;

            if (!$application->can_be_edited) {
                throw new BusinessException('Cannot update members in current application status.');
            }

            $member->update($request->validated());

            if ($request->hasAny(['date_of_birth', 'member_type', 'gender'])) {
                $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
                $this->premiumService->calculateApplicationPremium($application);
            }

            return $member->fresh(['principal']);
        });

        return $this->success(new ApplicationMemberResource($member), 'Member updated');
    }

    /**
     * Remove member from application.
     * DELETE /v1/medical/applications/{appId}/members/{memberId}
     */
    public function removeMember(string $appId, string $memberId): JsonResponse
    {
        DB::transaction(function () use ($appId, $memberId) {
            $member      = ApplicationMember::where('application_id', $appId)->findOrFail($memberId);
            $application = $member->application;

            if (!$application->can_be_edited) {
                throw new BusinessException('Cannot remove members in current application status.');
            }

            if ($member->is_principal) {
                ApplicationMember::where('principal_member_id', $member->id)->delete();
            }

            $member->delete();
            $application->updateMemberCounts();
            $this->premiumService->calculateApplicationPremium($application);
        });

        return $this->success(null, 'Member removed');
    }

    // =========================================================================
    // MEMBER UNDERWRITING
    // =========================================================================

    /**
     * Apply underwriting decision to member.
     * POST /v1/medical/applications/{appId}/members/{memberId}/underwrite
     */
    public function underwriteMember(string $appId, string $memberId): JsonResponse
    {
        $member = DB::transaction(function () use ($appId, $memberId) {
            $member      = ApplicationMember::where('application_id', $appId)->findOrFail($memberId);
            $application = $member->application;

            if (!$application->can_be_underwritten) {
                throw new BusinessException('Application is not in underwriting status.');
            }

            $decision = request('decision');
            if (!in_array($decision, ['approve', 'decline', 'terms'])) {
                throw new BusinessException('Invalid decision. Must be: approve, decline, or terms.');
            }

            return $this->applicationService->applyMemberUnderwritingDecision(
                $member,
                $decision,
                request('underwriter_id') ?? Auth::user()->id,
                request('loadings', []),
                request('exclusions', []),
                request('discounts', []),
                request('notes')
            );
        });

        return $this->success(new ApplicationMemberResource($member), 'Underwriting decision applied');
    }

    /**
     * Add loading to application member.
     * POST /v1/medical/applications/{appId}/members/{memberId}/loadings
     */
    public function addMemberLoading(string $appId, string $memberId): JsonResponse
    {
        $member = DB::transaction(function () use ($appId, $memberId) {
            $member = ApplicationMember::where('application_id', $appId)->findOrFail($memberId);

            request()->validate([
                'condition_name' => 'required|string|max:255',
                'loading_type'   => 'required|in:percentage,fixed',
                'value'          => 'required|numeric|min:0',
                'icd10_code'     => 'nullable|string|max:20',
                'duration_type'  => 'nullable|in:permanent,temporary',
                'duration_months'=> 'nullable|integer|min:1',
                'notes'          => 'nullable|string',
            ]);

            $member->addLoading([
                'condition_name' => request('condition_name'),
                'loading_type'   => request('loading_type'),
                'value'          => request('value'),
                'icd10_code'     => request('icd10_code'),
                'duration_type'  => request('duration_type', 'permanent'),
                'duration_months'=> request('duration_months'),
                'notes'          => request('notes'),
            ]);

            $this->premiumService->calculateApplicationPremium($member->application);

            return $member->fresh();
        });

        return $this->success(new ApplicationMemberResource($member), 'Loading added');
    }

    /**
     * Add exclusion to application member.
     * POST /v1/medical/applications/{appId}/members/{memberId}/exclusions
     */
    public function addMemberExclusion(string $appId, string $memberId): JsonResponse
    {
        $member = DB::transaction(function () use ($appId, $memberId) {
            $member = ApplicationMember::where('application_id', $appId)->findOrFail($memberId);

            request()->validate([
                'exclusion_name' => 'required|string|max:255',
                'exclusion_type' => 'nullable|in:condition,benefit,procedure',
                'benefit_id'     => 'nullable|uuid|exists:med_benefits,id',
                'icd10_codes'    => 'nullable|array',
                'description'    => 'nullable|string',
                'is_permanent'   => 'nullable|boolean',
                'notes'          => 'nullable|string',
            ]);

            $member->addExclusion([
                'exclusion_name' => request('exclusion_name'),
                'exclusion_type' => request('exclusion_type', 'condition'),
                'benefit_id'     => request('benefit_id'),
                'icd10_codes'    => request('icd10_codes'),
                'description'    => request('description'),
                'is_permanent'   => request('is_permanent', true),
                'notes'          => request('notes'),
            ]);

            return $member->fresh();
        });

        return $this->success(new ApplicationMemberResource($member), 'Exclusion added');
    }

    // =========================================================================
    // ADDONS
    // =========================================================================

    /**
     * Add addon to application.
     * POST /v1/medical/applications/{id}/addons
     */
    public function addAddon(string $id): JsonResponse
    {
        $addon = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if (!$application->can_be_edited) {
                throw new BusinessException('Cannot add addons in current status.');
            }

            request()->validate([
                'addon_id'      => 'required|uuid|exists:med_addons,id',
                'addon_rate_id' => 'nullable|uuid|exists:med_addon_rates,id',
            ]);

            if ($application->addons()->where('addon_id', request('addon_id'))->exists()) {
                throw new BusinessException('Addon already added to this application.');
            }

            $addon = ApplicationAddon::create([
                'application_id' => $id,
                'addon_id'       => request('addon_id'),
                'addon_rate_id'  => request('addon_rate_id'),
            ]);

            $this->premiumService->calculateApplicationPremium($application);

            return $addon->fresh(['addon']);
        });

        return $this->success($addon, 'Addon added', 201);
    }

    /**
     * Remove addon from application.
     * DELETE /v1/medical/applications/{id}/addons/{addonId}
     */
    public function removeAddon(string $id, string $addonId): JsonResponse
    {
        DB::transaction(function () use ($id, $addonId) {
            $application = Application::findOrFail($id);

            if (!$application->can_be_edited) {
                throw new BusinessException('Cannot remove addons in current status.');
            }

            $appAddon    = $application->addons()->findOrFail($addonId);
            $isMandatory = PlanAddon::where('plan_id', $application->plan_id)
                ->where('addon_id', $appAddon->addon_id)
                ->where('is_active', true)
                ->mandatory()
                ->exists();

            if ($isMandatory) {
                throw new BusinessException('Mandatory addons cannot be removed.');
            }

            $appAddon->delete();
            $this->premiumService->calculateApplicationPremium($application);
        });

        return $this->success(null, 'Addon removed');
    }

    // =========================================================================
    // PROMO CODE
    // =========================================================================

    /**
     * Apply promo code to application.
     * POST /v1/medical/applications/{id}/promo-code
     */
    public function applyPromoCode(string $id): JsonResponse
    {
        $application = DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);

            if (!request('code')) {
                throw new BusinessException('Promo code is required.');
            }

            return $this->applicationService->applyPromoCode($application, request('code'));
        });

        return $this->success(new ApplicationResource($application), 'Promo code applied');
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    /**
     * List application documents.
     * GET /v1/medical/applications/{id}/documents
     */
    public function documents(string $id): JsonResponse
    {
        $documents = ApplicationDocument::where('application_id', $id)
            ->with('member:id,first_name,last_name')
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return $this->success($documents, 'Documents retrieved');
    }

    /**
     * Upload document.
     * POST /v1/medical/applications/{id}/documents
     */
    public function uploadDocument(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $application = Application::findOrFail($id);
            $request     = request();

            $request->validate([
                'document_type'          => 'required|string',
                'title'                  => 'required|string|max:255',
                'file'                   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
                'application_member_id'  => 'nullable|uuid|exists:med_application_members,id',
            ]);

            $file = $request->file('file');
            $path = $file->store("applications/{$application->id}", 'local');

            $doc = ApplicationDocument::create([
                'application_id'        => $id,
                'application_member_id' => $request->application_member_id,
                'document_type'         => $request->document_type,
                'title'                 => $request->title,
                'file_path'             => $path,
                'file_name'             => $file->getClientOriginalName(),
                'mime_type'             => $file->getMimeType(),
                'file_size'             => $file->getSize(),
            ]);

            return $this->success($doc, 'Document uploaded', 201);
        });
    }

    /**
     * Download/view application document.
     * GET /v1/medical/applications/{id}/documents/{documentId}/download
     */
    public function downloadDocument(string $id, string $documentId): StreamedResponse|JsonResponse
    {
        $document = ApplicationDocument::where('application_id', $id)
            ->where('id', $documentId)
            ->active()
            ->firstOrFail();

        if (!Storage::disk('local')->exists($document->file_path)) {
            return $this->error('File not found on disk.', 404);
        }

        $disposition = request()->query('inline') === 'true' ? 'inline' : 'attachment';

        return Storage::disk('local')->download(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type'        => $document->mime_type,
                'Content-Disposition' => "{$disposition}; filename=\"{$document->file_name}\"",
            ]
        );
    }

    // =========================================================================
    // RENEWAL & QUOTES
    // =========================================================================

    /**
     * Create renewal application from policy.
     * POST /v1/medical/policies/{policyId}/renewal-application
     */
    public function createRenewalApplication(string $policyId): JsonResponse
    {
        $application = DB::transaction(fn () => $this->applicationService->createRenewalApplication(
            Policy::findOrFail($policyId),
            request()->all()
        ));

        return $this->success(new ApplicationResource($application), 'Renewal application created', 201);
    }

    /**
     * Generate quick quote (without creating application).
     * POST /v1/medical/quote
     */
    public function generateQuote(HttpRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'rate_card_id'               => 'required|uuid|exists:med_rate_cards,id',
            'billing_frequency'          => 'nullable|in:monthly,quarterly,semi_annual,annual',
            'members'                    => 'required|array|min:1',
            'members.*.date_of_birth'    => 'required|date',
            'members.*.member_type'      => 'required|string',
            'members.*.gender'           => 'nullable|in:M,F',
            'members.*.age'              => 'nullable|integer',
            'addons'                     => 'nullable|array',
            'addons.*.addon_id'          => 'required|uuid|exists:med_addons,id',
        ]);

        $rateCard    = RateCard::findOrFail($validated['rate_card_id']);
        $membersData = array_map(function ($m) {
            if (!isset($m['age']) && isset($m['date_of_birth'])) {
                $m['age'] = Carbon::parse($m['date_of_birth'])->age;
            }

            return $m;
        }, $validated['members']);

        $addonIds = array_column($validated['addons'] ?? [], 'addon_id');
        $quote    = $this->premiumService->calculateQuote($rateCard, $membersData, $addonIds);

        $quote['billing_frequency'] = $validated['billing_frequency'] ?? 'monthly';
        $quote['period_premium']    = $this->premiumService->periodize(
            $quote['gross_premium'] ?? $quote['total_premium'],
            $quote['billing_frequency']
        );

        return $this->success($quote, 'Quote generated');
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get application statistics.
     * GET /v1/medical/applications/stats
     */
    public function stats(): JsonResponse
    {
        $stats = Application::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when status = ? then 1 end) as draft', [MedicalConstants::APPLICATION_STATUS_DRAFT])
            ->selectRaw('count(case when status = ? then 1 end) as quoted', [MedicalConstants::APPLICATION_STATUS_QUOTED])
            ->selectRaw('count(case when status = ? then 1 end) as submitted', [MedicalConstants::APPLICATION_STATUS_SUBMITTED])
            ->selectRaw('count(case when status = ? then 1 end) as underwriting', [MedicalConstants::APPLICATION_STATUS_UNDERWRITING])
            ->selectRaw('count(case when status = ? then 1 end) as approved', [MedicalConstants::APPLICATION_STATUS_APPROVED])
            ->selectRaw('count(case when status = ? then 1 end) as accepted', [MedicalConstants::APPLICATION_STATUS_ACCEPTED])
            ->first()
            ->toArray();

        $stats['total_quoted_premium'] = Application::validQuotes()->sum('gross_premium');

        return $this->success($stats, 'Statistics retrieved');
    }

    // =========================================================================
    // CORPORATE CENSUS IMPORT
    // =========================================================================

    /**
     * Parse and validate a census CSV/Excel file.
     * POST /v1/medical/applications/import-census
     */
    public function importCensus(\Modules\Medical\Http\Requests\CensusBulkImportRequest $request): JsonResponse
    {
        /** @var \Modules\Medical\Services\CensusImportService $censusService */
        $censusService = app(\Modules\Medical\Services\CensusImportService::class);

        $result = $censusService->parseCensusFile($request->file('file'));

        if ($request->boolean('validate_only')) {
            return $this->success(
                new \Modules\Medical\Http\Resources\CensusImportResource($result),
                'Census file validated'
            );
        }

        if (!$result['success']) {
            return $this->error('Census file validation failed.', 422, [
                'validation_errors' => $result['errors'],
            ]);
        }

        $cacheKey = 'census_import_' . $request->user()->id . '_' . time();
        cache()->put($cacheKey, [
            'group_id' => $request->group_id,
            'data'     => $result['data'],
            'summary'  => $result['summary'],
        ], now()->addHours(2));

        return $this->success([
            'import_key' => $cacheKey,
            'summary'    => $result['summary'],
            'preview'    => array_slice($result['data'], 0, 5),
            'errors'     => $result['errors'],
        ], 'Census parsed. Please review any errors before continuing.');
    }

    /**
     * Create application from imported census.
     * POST /v1/medical/applications/create-from-census
     */
    public function createFromCensus(HttpRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'import_key'        => 'required|string',
            'scheme_id'         => 'required|exists:med_schemes,id',
            'plan_id'           => 'required|exists:med_plans,id',
            'rate_card_id'      => 'required|exists:med_rate_cards,id',
            'inception_date'    => 'required|date|after_or_equal:today',
            'billing_frequency' => 'required|in:' . implode(',', array_keys(MedicalConstants::BILLING_FREQUENCIES)),
        ]);

        $censusData = cache()->get($validated['import_key']);

        if (!$censusData) {
            throw new BusinessException('Census data not found or expired. Please re-upload the file.', 404);
        }

        $application = DB::transaction(function () use ($validated, $censusData) {
            /** @var \Modules\Medical\Services\CensusImportService $censusService */
            $censusService = app(\Modules\Medical\Services\CensusImportService::class);
            $membersData   = $censusService->transformToMemberData($censusData['data'], $censusData['group_id']);

            $application = $this->applicationService->createApplication([
                'application_type'   => MedicalConstants::APPLICATION_TYPE_NEW,
                'policy_type'        => MedicalConstants::POLICY_TYPE_CORPORATE,
                'scheme_id'          => $validated['scheme_id'],
                'plan_id'            => $validated['plan_id'],
                'rate_card_id'       => $validated['rate_card_id'],
                'group_id'           => $censusData['group_id'],
                'proposed_start_date'=> $validated['inception_date'],
                'billing_frequency'  => $validated['billing_frequency'],
                'status'             => MedicalConstants::APPLICATION_STATUS_DRAFT,
            ]);

            foreach ($membersData as $memberData) {
                $this->applicationService->addMember($application->id, $memberData);
            }

            $this->ensureMandatoryAddons($application);
            $this->premiumService->calculateApplicationPremium($application);
            $application->updateMemberCounts();

            cache()->forget($validated['import_key']);

            return $application;
        });

        $application->load(['scheme', 'plan', 'group', 'activeMembers' => fn ($q) => $q->orderBy('member_type')]);

        return $this->success(
            new ApplicationResource($application),
            'Application created from census with ' . count($application->activeMembers) . ' members',
            201
        );
    }

    /**
     * Create multiple applications from imported census based on plan mapping.
     * POST /v1/medical/applications/create-multi-plan-from-census
     */
    public function createMultiPlanFromCensus(HttpRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'import_key'        => 'required|string',
            'group_id'          => 'required|exists:med_corporate_groups,id',
            'scheme_id'         => 'required|exists:med_schemes,id',
            'rate_card_id'      => 'required|exists:med_rate_cards,id',
            'inception_date'    => 'required|date|after_or_equal:today',
            'billing_frequency' => 'required|in:' . implode(',', array_keys(MedicalConstants::BILLING_FREQUENCIES)),
            'plan_mapping'      => 'required|array|min:1',
            'plan_mapping.*'    => 'required|exists:med_plans,id',
            'mapping_type'      => 'required|in:salary_band,department,job_title',
        ]);

        $censusData = cache()->get($validated['import_key']);

        if (!$censusData) {
            throw new BusinessException('Census data not found or expired. Please re-upload the file.', 404);
        }

        /** @var \Modules\Medical\Services\PlanAssignmentService $planAssignmentService */
        $planAssignmentService = app(\Modules\Medical\Services\PlanAssignmentService::class);
        /** @var \Modules\Medical\Services\CensusImportService $censusService */
        $censusService = app(\Modules\Medical\Services\CensusImportService::class);

        $membersData = $censusService->transformToMemberData($censusData['data'], $censusData['group_id']);

        $result = $planAssignmentService->assignMembersToPlans(
            $membersData,
            $validated['plan_mapping'],
            $validated['group_id'],
            [
                'scheme_id'           => $validated['scheme_id'],
                'rate_card_id'        => $validated['rate_card_id'],
                'proposed_start_date' => $validated['inception_date'],
                'billing_frequency'   => $validated['billing_frequency'],
            ]
        );

        cache()->forget($validated['import_key']);

        return $this->success(
            $result,
            "{$result['plans_used']} applications created from census with {$result['total_members']} members",
            201
        );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Ensure all mandatory plan addons are present on the application.
     */
    private function ensureMandatoryAddons(Application $application): void
    {
        $mandatoryAddonIds = PlanAddon::where('plan_id', $application->plan_id)
            ->where('is_active', true)
            ->mandatory()
            ->pluck('addon_id')
            ->toArray();

        if (empty($mandatoryAddonIds)) {
            return;
        }

        $existingAddonIds = $application->addons()->pluck('addon_id')->toArray();

        foreach (array_diff($mandatoryAddonIds, $existingAddonIds) as $addonId) {
            ApplicationAddon::create([
                'application_id' => $application->id,
                'addon_id'       => $addonId,
            ]);
        }
    }
}
