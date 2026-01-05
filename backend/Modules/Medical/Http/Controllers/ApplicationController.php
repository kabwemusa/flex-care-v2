<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Modules\Medical\Models\Application;
use Modules\Medical\Models\ApplicationMember;
use Modules\Medical\Models\ApplicationAddon;
use Modules\Medical\Models\ApplicationDocument;
use Modules\Medical\Models\Policy;
use Modules\Medical\Http\Requests\ApplicationRequest;
use Modules\Medical\Http\Requests\ApplicationMemberRequest;
use Modules\Medical\Http\Resources\ApplicationResource;
use Modules\Medical\Http\Resources\ApplicationListResource;
use Modules\Medical\Http\Resources\ApplicationMemberResource;
use Modules\Medical\Http\Resources\PolicyResource;
use Modules\Medical\Services\ApplicationService;
use Modules\Medical\Services\PremiumService;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;
use Illuminate\Http\Request as HttpRequest;
use Modules\Medical\Models\RateCard;
use Symfony\Component\HttpFoundation\Request;

class ApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApplicationService $applicationService,
        protected PremiumService $premiumService
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
        try {
            $query = Application::query()
                ->with(['scheme:id,code,name', 'plan:id,code,name', 'group:id,code,name'])
                ->withCount('activeMembers');

            // Search
            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('application_number', 'like', "%{$search}%")
                      ->orWhere('contact_name', 'like', "%{$search}%")
                      ->orWhere('contact_email', 'like', "%{$search}%");
                });
            }

            // Filters
            if ($status = request('status')) {
                $query->where('status', $status);
            }

            if ($applicationType = request('application_type')) {
                $query->where('application_type', $applicationType);
            }

            if ($policyType = request('policy_type')) {
                $query->where('policy_type', $policyType);
            }

            if ($schemeId = request('scheme_id')) {
                $query->where('scheme_id', $schemeId);
            }

            if ($planId = request('plan_id')) {
                $query->where('plan_id', $planId);
            }

            if ($groupId = request('group_id')) {
                $query->where('group_id', $groupId);
            }

            if (request('pending_underwriting')) {
                $query->pendingUnderwriting();
            }

            if (request('pending_conversion')) {
                $query->pendingConversion();
            }

            if (request('expired')) {
                $query->expired();
            }

            if (request('corporate_only')) {
                $query->corporate();
            }

            if (request('individual_only')) {
                $query->individual();
            }

            // Date filters
            if ($from = request('created_from')) {
                $query->where('created_at', '>=', $from);
            }
            if ($to = request('created_to')) {
                $query->where('created_at', '<=', $to);
            }

            // Sorting
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $applications = $query->paginate(request('per_page', 20));

            return $this->success(
                ApplicationListResource::collection($applications),
                'Applications retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve applications: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new application.
     * POST /v1/medical/applications
     */
    public function store(ApplicationRequest $request): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($request) {
                return $this->applicationService->createApplication($request->validated());
            });

            return $this->success(
                new ApplicationResource($application),
                'Application created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show application details.
     * GET /v1/medical/applications/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $application = Application::with([
                'scheme',
                'plan.planBenefits.benefit',
                'rateCard',
                'group.primaryContact',
                'renewalOfPolicy:id,policy_number',
                'convertedPolicy:id,policy_number',
                'activeMembers' => fn($q) => $q->orderBy('member_type')->orderBy('created_at'),
                'activeMembers.principal:id,first_name,last_name',
                'activeAddons.addon',
                'documents' => fn($q) => $q->active()->latest(),
            ])
            ->withCount(['activeMembers', 'principals', 'dependents'])
            ->findOrFail($id);

            return $this->success(
                new ApplicationResource($application),
                'Application retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update application.
     * PUT /v1/medical/applications/{id}
     */
    public function update(ApplicationRequest $request, string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($request, $id) {
                $application = Application::findOrFail($id);

                if (!$application->can_be_edited) {
                    throw new Exception('Application cannot be edited in current status', 422);
                }

                $application->update($request->validated());

                // Recalculate premium if relevant fields changed
                if ($request->hasAny(['plan_id', 'rate_card_id', 'billing_frequency'])) {
                    $this->premiumService->calculateApplicationPremium($application);
                }

                return $application->fresh([
                    'scheme', 'plan', 'rateCard', 'activeMembers', 'activeAddons'
                ]);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Delete application (drafts only).
     * DELETE /v1/medical/applications/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);

                if (!$application->is_draft) {
                    throw new Exception('Only draft applications can be deleted', 422);
                }

                // Delete related records
                $application->members()->delete();
                $application->addons()->delete();
                $application->documents()->delete();
                $application->delete();
            });

            return $this->success(null, 'Application deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
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
        try {
            $application = Application::findOrFail($id);

            $breakdown = $this->premiumService->calculateApplicationPremium($application);

            return $this->success($breakdown, 'Premium calculated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to calculate premium: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark application as quoted.
     * POST /v1/medical/applications/{id}/quote
     */
    public function markAsQuoted(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                return $this->applicationService->markAsQuoted($application);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application marked as quoted'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Download quote as PDF.
     * GET /v1/medical/applications/{id}/quote/download
     */
    public function downloadQuote(string $id)
    {
        try {
            $application = Application::with([
                'scheme',
                'plan',
                'rateCard',
                'group',
                'members',
                'addons.addon'
            ])->findOrFail($id);

            if ($application->status !== MedicalConstants::APPLICATION_STATUS_QUOTED) {
                return $this->error('Application must be in quoted status to download quote', 422);
            }

            // In a production environment, you would use a PDF generation library like dompdf or snappy
            // For now, we'll return JSON data that the frontend can use
            $quoteData = [
                'application_number' => $application->application_number,
                'quote_date' => $application->quoted_at,
                'valid_until' => $application->quote_valid_until,
                'applicant_name' => $application->applicant_name ?? $application->contact_name,
                'contact_email' => $application->contact_email,
                'contact_phone' => $application->contact_phone,
                'plan' => [
                    'scheme' => $application->scheme->name ?? '',
                    'name' => $application->plan->name ?? '',
                    'tier' => $application->plan->tier_level ?? '',
                ],
                'members' => $application->members->map(fn($m) => [
                    'name' => $m->full_name ?? ($m->first_name . ' ' . $m->last_name),
                    'age' => $m->age,
                    'gender' => $m->gender,
                    'relationship' => $m->relationship,
                    'premium' => $m->total_premium,
                ]),
                'addons' => $application->addons->map(fn($a) => [
                    'name' => $a->addon_name ?? $a->addon->name,
                    'premium' => $a->premium,
                ]),
                'premium_breakdown' => [
                    'base_premium' => $application->base_premium,
                    'addon_premium' => $application->addon_premium,
                    'loading_amount' => $application->loading_amount,
                    'discount_amount' => $application->discount_amount,
                    'total_premium' => $application->total_premium,
                    'tax_amount' => $application->tax_amount,
                    'gross_premium' => $application->gross_premium,
                    'currency' => $application->currency,
                    'billing_frequency' => $application->billing_frequency,
                ],
                'policy_details' => [
                    'policy_term_months' => $application->policy_term_months,
                    'proposed_start_date' => $application->proposed_start_date,
                    'proposed_end_date' => $application->proposed_end_date,
                ],
            ];

            // Return JSON for now - frontend will handle PDF generation
            return response()->json($quoteData);

        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to generate quote: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Email quote to customer.
     * POST /v1/medical/applications/{id}/quote/email
     */
    public function emailQuote(string $id): JsonResponse
    {
        try {
            $application = Application::with(['scheme', 'plan', 'members', 'addons'])->findOrFail($id);

            if ($application->status !== MedicalConstants::APPLICATION_STATUS_QUOTED) {
                return $this->error('Application must be in quoted status to email quote', 422);
            }

            $validated = request()->validate([
                'email' => 'required|email',
                'message' => 'nullable|string|max:1000',
            ]);

            // In production, you would queue an email job here
            // Mail::to($validated['email'])->queue(new QuoteEmail($application, $validated['message']));

            // For now, we'll just return success
            // You can implement actual email sending using Laravel's Mail facade and queues

            return $this->success([
                'email' => $validated['email'],
                'application_number' => $application->application_number,
                'sent_at' => now()->toIso8601String(),
            ], 'Quote email sent successfully');

        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to send quote email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit application for underwriting.
     * POST /v1/medical/applications/{id}/submit
     */
    public function submit(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                return $this->applicationService->submitForUnderwriting($application);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application submitted for underwriting'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Start underwriting process.
     * POST /v1/medical/applications/{id}/start-underwriting
     */
    public function startUnderwriting(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                $underwriterId = request('underwriter_id') ??  Str::uuid()->toString();
                return $this->applicationService->startUnderwriting($application, $underwriterId);
            });

            return $this->success(
                new ApplicationResource($application),
                'Underwriting started'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Approve application.
     * POST /v1/medical/applications/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                $underwriterId = request('underwriter_id') ?? $application->underwriter_id;
                $notes = request('notes');
                
                return $this->applicationService->approveApplication($application, $underwriterId, $notes);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application approved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Decline application.
     * POST /v1/medical/applications/{id}/decline
     */
    public function decline(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                
                $reason = request('reason');
                if (!$reason) {
                    throw new Exception('Decline reason is required', 422);
                }

                $underwriterId = request('underwriter_id')  ?? $application->underwriter_id;
                
                return $this->applicationService->declineApplication($application, $underwriterId, $reason);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application declined'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Refer application for further review.
     * POST /v1/medical/applications/{id}/refer
     */
    public function refer(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                
                $reason = request('reason');
                if (!$reason) {
                    throw new Exception('Referral reason is required', 422);
                }

                $underwriterId = request('underwriter_id')  ?? $application->underwriter_id;
                
                return $this->applicationService->referApplication($application, $underwriterId, $reason);
            });

            return $this->success(
                new ApplicationResource($application),
                'Application referred for review'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Customer accepts the quote.
     * POST /v1/medical/applications/{id}/accept
     */
    public function accept(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                $acceptanceReference = request('acceptance_reference');
                
                return $this->applicationService->acceptQuote($application, $acceptanceReference);
            });

            return $this->success(
                new ApplicationResource($application),
                'Quote accepted'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Convert application to policy.
     * POST /v1/medical/applications/{id}/convert
     */
    public function convert(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                $issuedBy = request('issued_by')  ?? $application->underwriter_id;
                
                return $this->applicationService->convertToPolicy($application, $issuedBy);
            });

            return $this->success(
                new PolicyResource($policy),
                'Application converted to policy',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to convert: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel application.
     * POST /v1/medical/applications/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);

                if ($application->is_converted) {
                    throw new Exception('Cannot cancel converted application', 422);
                }

                $application->cancel(request('reason'));
                return $application->fresh();
            });

            return $this->success(
                new ApplicationResource($application),
                'Application cancelled'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
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
        try {
            $members = ApplicationMember::where('application_id', $id)
                ->with(['principal:id,first_name,last_name'])
                ->orderBy('member_type')
                ->orderBy('created_at')
                ->get();

            return $this->success(
                ApplicationMemberResource::collection($members),
                'Members retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve members: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add member to application.
     * POST /v1/medical/applications/{id}/members
     */
    public function addMember(ApplicationMemberRequest $request, string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($request, $id) {
                $application = Application::findOrFail($id);

                if (!$application->can_be_edited) {
                    throw new Exception('Cannot add members to application in current status', 422);
                }

                $data = $request->validated();
                $data['application_id'] = $id;

                // Calculate age at inception
                if (!empty($data['date_of_birth'])) {
                    $data['age_at_inception'] = \Carbon\Carbon::parse($data['date_of_birth'])
                        ->diffInYears($application->proposed_start_date);
                }

                $member = ApplicationMember::create($data);

                // Calculate member premium
                $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);

                // Update application
                $application->updateMemberCounts();
                $this->premiumService->calculateApplicationPremium($application);

                return $member->fresh(['application', 'principal']);
            });

            return $this->success(
                new ApplicationMemberResource($member),
                'Member added',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Update application member.
     * PUT /v1/medical/applications/{appId}/members/{memberId}
     */
    public function updateMember(ApplicationMemberRequest $request, string $appId, string $memberId): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($request, $appId, $memberId) {
                $member = ApplicationMember::where('application_id', $appId)
                    ->findOrFail($memberId);

                $application = $member->application;

                if (!$application->can_be_edited) {
                    throw new Exception('Cannot update members in current application status', 422);
                }

                $member->update($request->validated());

                // Recalculate premium if needed
                if ($request->hasAny(['date_of_birth', 'member_type', 'gender'])) {
                    $this->premiumService->calculateApplicationMemberPremium($member, $application->rateCard);
                    $this->premiumService->calculateApplicationPremium($application);
                }

                return $member->fresh(['principal']);
            });

            return $this->success(
                new ApplicationMemberResource($member),
                'Member updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Remove member from application.
     * DELETE /v1/medical/applications/{appId}/members/{memberId}
     */
    public function removeMember(string $appId, string $memberId): JsonResponse
    {
        try {
            DB::transaction(function () use ($appId, $memberId) {
                $member = ApplicationMember::where('application_id', $appId)
                    ->findOrFail($memberId);

                $application = $member->application;

                if (!$application->can_be_edited) {
                    throw new Exception('Cannot remove members in current application status', 422);
                }

                // If principal, also remove dependents
                if ($member->is_principal) {
                    ApplicationMember::where('principal_member_id', $member->id)->delete();
                }

                $member->delete();

                // Update application
                $application->updateMemberCounts();
                $this->premiumService->calculateApplicationPremium($application);
            });

            return $this->success(null, 'Member removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
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
        try {
            $member = DB::transaction(function () use ($appId, $memberId) {
                $member = ApplicationMember::where('application_id', $appId)
                    ->findOrFail($memberId);

                $application = $member->application;

                if (!$application->can_be_underwritten) {
                    throw new Exception('Application is not in underwriting status', 422);
                }

                $decision = request('decision'); // 'approve', 'decline', 'terms'
                if (!in_array($decision, ['approve', 'decline', 'terms'])) {
                    throw new Exception('Invalid decision. Must be: approve, decline, or terms', 422);
                }

                $underwriterId = request('underwriter_id') ?? 'system';
                $loadings = request('loadings', []);
                $exclusions = request('exclusions', []);
                $notes = request('notes');

                return $this->applicationService->applyMemberUnderwritingDecision(
                    $member,
                    $decision,
                    $underwriterId,
                    $loadings,
                    $exclusions,
                    $notes
                );
            });

            return $this->success(
                new ApplicationMemberResource($member),
                'Underwriting decision applied'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Add loading to application member.
     * POST /v1/medical/applications/{appId}/members/{memberId}/loadings
     */
    public function addMemberLoading(string $appId, string $memberId): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($appId, $memberId) {
                $member = ApplicationMember::where('application_id', $appId)
                    ->findOrFail($memberId);

                request()->validate([
                    'condition_name' => 'required|string|max:255',
                    'loading_type' => 'required|in:percentage,fixed',
                    'value' => 'required|numeric|min:0',
                    'icd10_code' => 'nullable|string|max:20',
                    'duration_type' => 'nullable|string|in:permanent,temporary',
                    'duration_months' => 'nullable|integer|min:1',
                    'notes' => 'nullable|string',
                ]);

                $member->addLoading([
                    'condition_name' => request('condition_name'),
                    'loading_type' => request('loading_type'),
                    'value' => request('value'),
                    'icd10_code' => request('icd10_code'),
                    'duration_type' => request('duration_type', 'permanent'),
                    'duration_months' => request('duration_months'),
                    'notes' => request('notes'),
                ]);

                // Recalculate
                $this->premiumService->calculateApplicationPremium($member->application);

                return $member->fresh();
            });

            return $this->success(
                new ApplicationMemberResource($member),
                'Loading added'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Add exclusion to application member.
     * POST /v1/medical/applications/{appId}/members/{memberId}/exclusions
     */
    public function addMemberExclusion(string $appId, string $memberId): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($appId, $memberId) {
                $member = ApplicationMember::where('application_id', $appId)
                    ->findOrFail($memberId);

                request()->validate([
                    'exclusion_name' => 'required|string|max:255',
                    'exclusion_type' => 'nullable|string|in:condition,benefit,procedure',
                    'benefit_id' => 'nullable|uuid|exists:med_benefits,id',
                    'icd10_codes' => 'nullable|array',
                    'description' => 'nullable|string',
                    'is_permanent' => 'nullable|boolean',
                    'notes' => 'nullable|string',
                ]);

                $member->addExclusion([
                    'exclusion_name' => request('exclusion_name'),
                    'exclusion_type' => request('exclusion_type', 'condition'),
                    'benefit_id' => request('benefit_id'),
                    'icd10_codes' => request('icd10_codes'),
                    'description' => request('description'),
                    'is_permanent' => request('is_permanent', true),
                    'notes' => request('notes'),
                ]);

                return $member->fresh();
            });

            return $this->success(
                new ApplicationMemberResource($member),
                'Exclusion added'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
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
        try {
            $addon = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);

                if (!$application->can_be_edited) {
                    throw new Exception('Cannot add addons in current status', 422);
                }

                request()->validate([
                    'addon_id' => 'required|uuid|exists:med_addons,id',
                    'addon_rate_id' => 'nullable|uuid|exists:med_addon_rates,id',
                ]);

                // Check if already added
                if ($application->addons()->where('addon_id', request('addon_id'))->exists()) {
                    throw new Exception('Addon already added to this application', 422);
                }

                $addon = ApplicationAddon::create([
                    'application_id' => $id,
                    'addon_id' => request('addon_id'),
                    'addon_rate_id' => request('addon_rate_id'),
                ]);

                // Recalculate premium
                $this->premiumService->calculateApplicationPremium($application);

                return $addon->fresh(['addon']);
            });

            return $this->success($addon, 'Addon added', 201);
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Remove addon from application.
     * DELETE /v1/medical/applications/{id}/addons/{addonId}
     */
    public function removeAddon(string $id, string $addonId): JsonResponse
    {
        try {
            DB::transaction(function () use ($id, $addonId) {
                $application = Application::findOrFail($id);

                if (!$application->can_be_edited) {
                    throw new Exception('Cannot remove addons in current status', 422);
                }

                $appAddon = $application->addons()->findOrFail($addonId);
                $appAddon->delete();

                // Recalculate premium
                $this->premiumService->calculateApplicationPremium($application);
            });

            return $this->success(null, 'Addon removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Application or addon not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
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
        try {
            $application = DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);

                $code = request('code');
                if (!$code) {
                    throw new Exception('Promo code is required', 422);
                }

                return $this->applicationService->applyPromoCode($application, $code);
            });

            return $this->success(
                new ApplicationResource($application),
                'Promo code applied'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Application not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
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
        try {
            $documents = ApplicationDocument::where('application_id', $id)
                ->with('member:id,first_name,last_name')
                ->active()
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($documents, 'Documents retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve documents', 500);
        }
    }

    /**
     * Upload document.
     * POST /v1/medical/applications/{id}/documents
     */
    public function uploadDocument(string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $application = Application::findOrFail($id);
                $request = request(); // Get current request

                $request->validate([
                    'document_type' => 'required|string',
                    'title' => 'required|string|max:255',
                    'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx', // Added mimes security
                    'application_member_id' => 'nullable|uuid|exists:med_application_members,id',
                ]);

                $file = $request->file('file');
                // Store in private storage (storage/app/private/applications/{id})
                $path = $file->store("applications/{$application->id}", 'local'); 

                $doc = ApplicationDocument::create([
                    'application_id' => $id,
                    'application_member_id' => $request->application_member_id,
                    'document_type' => $request->document_type,
                    'title' => $request->title,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                return $this->success($doc, 'Document uploaded', 201);
            });
        } catch (Throwable $e) {
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
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
        try {
            $application = DB::transaction(function () use ($policyId) {
                $policy = Policy::findOrFail($policyId);
                return $this->applicationService->createRenewalApplication($policy, request()->all());
            });

            return $this->success(
                new ApplicationResource($application),
                'Renewal application created',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Generate quick quote (without creating application).
     * POST /v1/medical/quote
     */
    public function generateQuote(HttpRequest $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'rate_card_id' => 'required|uuid|exists:med_rate_cards,id',
                'billing_frequency' => 'nullable|in:monthly,quarterly,semi_annual,annual',
                
                'members' => 'required|array|min:1',
                'members.*.date_of_birth' => 'required|date', // Age calculated from this
                'members.*.member_type' => 'required|string',
                'members.*.gender' => 'nullable|in:M,F',
                'members.*.age' => 'nullable|integer', // Optional override
                
                'addons' => 'nullable|array',
                'addons.*.addon_id' => 'required|uuid|exists:med_addons,id',
            ]);

            $rateCard = RateCard::findOrFail($validated['rate_card_id']);
            
            // Map request data to what PremiumService expects
            $membersData = array_map(function($m) {
                if (!isset($m['age']) && isset($m['date_of_birth'])) {
                    $m['age'] = \Carbon\Carbon::parse($m['date_of_birth'])->age;
                }
                return $m;
            }, $validated['members']);

            $addonIds = array_column($validated['addons'] ?? [], 'addon_id');

            $quote = $this->premiumService->calculateQuote($rateCard, $membersData, $addonIds);

            // Add annualization info
            $quote['billing_frequency'] = $validated['billing_frequency'] ?? 'monthly';
            $quote['period_premium'] = $this->premiumService->periodize($quote['gross_premium'] ?? $quote['total_premium'], $quote['billing_frequency']);

            return $this->success($quote, 'Quote generated');
        } catch (Throwable $e) {
            return $this->error('Failed to generate quote: ' . $e->getMessage(), 500);
        }
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
        try {
            // optimized to single query
            $stats = Application::query()
                ->selectRaw("count(*) as total")
                ->selectRaw("count(case when status = ? then 1 end) as draft", [MedicalConstants::APPLICATION_STATUS_DRAFT])
                ->selectRaw("count(case when status = ? then 1 end) as quoted", [MedicalConstants::APPLICATION_STATUS_QUOTED])
                ->selectRaw("count(case when status = ? then 1 end) as submitted", [MedicalConstants::APPLICATION_STATUS_SUBMITTED])
                ->selectRaw("count(case when status = ? then 1 end) as underwriting", [MedicalConstants::APPLICATION_STATUS_UNDERWRITING])
                ->selectRaw("count(case when status = ? then 1 end) as approved", [MedicalConstants::APPLICATION_STATUS_APPROVED])
                ->selectRaw("count(case when status = ? then 1 end) as accepted", [MedicalConstants::APPLICATION_STATUS_ACCEPTED])
                // ->selectRaw("count(case when is_converted = 1 then 1 end) as converted")
                ->first()
                ->toArray();

            // Premium totals might need a separate query if table is large
            $premiumStats = Application::validQuotes()->sum('gross_premium');
            $stats['total_quoted_premium'] = $premiumStats;

            return $this->success($stats, 'Statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics', 500);
        }
    }
}
