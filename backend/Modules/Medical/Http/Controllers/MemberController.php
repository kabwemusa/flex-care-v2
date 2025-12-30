<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\MemberLoading;
use Modules\Medical\Models\MemberExclusion;
use Modules\Medical\Models\MemberDocument;
use Modules\Medical\Http\Requests\MemberRequest;
use Modules\Medical\Http\Requests\MemberLoadingRequest;
use Modules\Medical\Http\Requests\MemberExclusionRequest;
use Modules\Medical\Http\Resources\MemberResource;
use Modules\Medical\Http\Resources\MemberListResource;
use Modules\Medical\Http\Resources\MemberLoadingResource;
use Modules\Medical\Http\Resources\MemberExclusionResource;
use Modules\Medical\Http\Resources\MemberDocumentResource;
use Modules\Medical\Services\PremiumCalculator;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class MemberController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PremiumCalculator $premiumCalculator
    ) {}

    /**
     * List members with filtering.
     * GET /v1/medical/members
     */
    public function index(): JsonResponse
    {
        try {
            $query = Member::query()
                ->with(['policy:id,policy_number,status', 'principal:id,first_name,last_name']);

            // Search
            if ($search = request('search')) {
                $query->search($search);
            }

            // Filters
            if ($status = request('status')) {
                $query->where('status', $status);
            }

            if ($memberType = request('member_type')) {
                $query->where('member_type', $memberType);
            }

            if ($policyId = request('policy_id')) {
                $query->where('policy_id', $policyId);
            }

            if (request('principals_only')) {
                $query->principals();
            }

            if (request('dependents_only')) {
                $query->dependents();
            }

            if (request('active_only')) {
                $query->active();
            }

            if (request('in_waiting_period')) {
                $query->inWaitingPeriod();
            }

            // Sorting
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $members = $query->paginate(request('per_page', 20));

            return $this->success(
                MemberListResource::collection($members),
                'Members retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve members: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new member (principal or dependent).
     * POST /v1/medical/members
     */
    public function store(MemberRequest $request): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($request) {
                $policy = Policy::with('plan')->findOrFail($request->policy_id);

                if (!$policy->canAddMember()) {
                    throw new Exception('Cannot add members to this policy. Check policy status.', 422);
                }

                // Validate member type limits
                $plan = $policy->plan;
                if ($request->member_type !== MedicalConstants::MEMBER_TYPE_PRINCIPAL) {
                    $currentDependents = $policy->members()->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL)->count();
                    if ($currentDependents >= $plan->max_dependents) {
                        throw new Exception("Maximum dependents ({$plan->max_dependents}) reached for this plan", 422);
                    }
                }

                $data = $request->validated();
                
                // Set cover dates if not provided
                $data['cover_start_date'] = $data['cover_start_date'] ?? now();
                $data['cover_end_date'] = $data['cover_end_date'] ?? $policy->expiry_date;

                $member = Member::create($data);

                // Calculate waiting period
                $waitingEndDate = $member->calculateWaitingPeriodEndDate();
                if ($waitingEndDate) {
                    $member->waiting_period_end_date = $waitingEndDate;
                    $member->save();
                }

                // Calculate member premium
                $this->premiumCalculator->calculateMemberPremium($member);

                // Update policy counts
                $policy->updateMemberCounts();

                // Recalculate policy premium
                $this->premiumCalculator->calculate($policy);

                return $member;
            });

            $member->load(['policy', 'principal']);

            return $this->success(
                new MemberResource($member),
                'Member created',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Show member details.
     * GET /v1/medical/members/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $member = Member::with([
                'policy.scheme',
                'policy.plan',
                'principal',
                'dependents',
                'loadings' => fn($q) => $q->orderBy('status')->orderBy('created_at', 'desc'),
                'exclusions' => fn($q) => $q->orderBy('status')->orderBy('created_at', 'desc'),
                'documents' => fn($q) => $q->active()->latest(),
            ])
            ->withCount(['dependents', 'activeLoadings', 'activeExclusions'])
            ->findOrFail($id);

            return $this->success(
                new MemberResource($member),
                'Member retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve member details', 500);
        }
    }

    /**
     * Update member.
     * PUT /v1/medical/members/{id}
     */
    public function update(MemberRequest $request, string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($request, $id) {
                $member = Member::findOrFail($id);
                $member->update($request->validated());

                // Recalculate if premium-affecting fields changed
                if ($request->hasAny(['date_of_birth', 'salary_band'])) {
                    $this->premiumCalculator->calculateMemberPremium($member);
                    $this->premiumCalculator->calculate($member->policy);
                }

                return $member->fresh(['policy', 'principal']);
            });

            return $this->success(
                new MemberResource($member),
                'Member updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update member: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete member.
     * DELETE /v1/medical/members/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);

                if ($member->is_principal && $member->dependents()->exists()) {
                    throw new Exception('Cannot delete principal with dependents. Remove dependents first.', 422);
                }

                $policy = $member->policy;
                $member->delete();

                // Update policy
                $policy->updateMemberCounts();
                $this->premiumCalculator->calculate($policy);
            });

            return $this->success(null, 'Member deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // STATUS MANAGEMENT
    // =========================================================================

    /**
     * Activate member.
     * POST /v1/medical/members/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);
                $member->activate();
                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member activated'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to activate member', 500);
        }
    }

    /**
     * Suspend member.
     * POST /v1/medical/members/{id}/suspend
     */
    public function suspend(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);
                $member->suspend(request('reason'));

                // If principal, suspend dependents too
                if ($member->is_principal && request('include_dependents', false)) {
                    $member->dependents()->active()->each(fn($dep) => $dep->suspend('Principal suspended'));
                }

                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member suspended'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to suspend member', 500);
        }
    }

    /**
     * Terminate member.
     * POST /v1/medical/members/{id}/terminate
     */
    public function terminate(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);

                $reason = request('reason', 'terminated');
                $notes = request('notes');

                $member->terminate($reason, $notes);

                // If principal, terminate dependents too
                if ($member->is_principal) {
                    $member->dependents()
                        ->whereNotIn('status', [MedicalConstants::MEMBER_STATUS_TERMINATED, MedicalConstants::MEMBER_STATUS_DECEASED])
                        ->each(fn($dep) => $dep->terminate('principal_terminated', 'Principal member terminated'));
                }

                // Update policy
                $member->policy->updateMemberCounts();
                $this->premiumCalculator->calculate($member->policy);

                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member terminated'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to terminate member', 500);
        }
    }

    /**
     * Mark member as deceased.
     * POST /v1/medical/members/{id}/deceased
     */
    public function markDeceased(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);
                $member->markDeceased(request('notes'));

                // Update policy
                $member->policy->updateMemberCounts();
                $this->premiumCalculator->calculate($member->policy);

                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member marked as deceased'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to update member status', 500);
        }
    }

    // =========================================================================
    // CARD MANAGEMENT
    // =========================================================================

    /**
     * Issue member card.
     * POST /v1/medical/members/{id}/issue-card
     */
    public function issueCard(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);

                if ($member->card_number) {
                    throw new Exception('Member already has a card', 422);
                }

                if (!$member->is_active) {
                    throw new Exception('Cannot issue card to inactive member', 422);
                }

                $member->issueCard();
                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Card issued'
            );
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate member card.
     * POST /v1/medical/members/{id}/activate-card
     */
    public function activateCard(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);

                if (!$member->activateCard()) {
                    throw new Exception('Card cannot be activated', 422);
                }
                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Card activated'
            );
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Block member card.
     * POST /v1/medical/members/{id}/block-card
     */
    public function blockCard(string $id): JsonResponse
    {
        try {
            $member = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);
                $member->blockCard(request('reason'));
                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Card blocked'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to block card', 500);
        }
    }

    /**
     * Issue cards for all members of a policy.
     * POST /v1/medical/policies/{policyId}/issue-cards
     */
    public function issueCardsForPolicy(string $policyId): JsonResponse
    {
        try {
            $issued = DB::transaction(function () use ($policyId) {
                $policy = Policy::findOrFail($policyId);

                $members = $policy->members()
                    ->active()
                    ->whereNull('card_number')
                    ->get();

                $count = 0;
                foreach ($members as $member) {
                    $member->issueCard();
                    $count++;
                }
                return $count;
            });

            return $this->success(['issued_count' => $issued], "{$issued} cards issued");
        } catch (Throwable $e) {
            return $this->error('Failed to issue cards', 500);
        }
    }

    // =========================================================================
    // LOADINGS
    // =========================================================================

    /**
     * List member loadings.
     * GET /v1/medical/members/{id}/loadings
     */
    public function loadings(string $id): JsonResponse
    {
        try {
            $loadings = MemberLoading::where('member_id', $id)
                ->with('loadingRule')
                ->orderBy('status')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success(
                MemberLoadingResource::collection($loadings),
                'Loadings retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve loadings', 500);
        }
    }

    /**
     * Add loading to member.
     * POST /v1/medical/members/{id}/loadings
     */
    public function addLoading(MemberLoadingRequest $request, string $id): JsonResponse
    {
        try {
            $loading = DB::transaction(function () use ($request, $id) {
                $member = Member::findOrFail($id);

                $data = $request->validated();
                $data['member_id'] = $id;

                // Calculate loading amount if percentage
                if ($data['loading_type'] === MedicalConstants::LOADING_TYPE_PERCENTAGE) {
                    $data['loading_amount'] = round($member->premium * ($data['loading_value'] / 100), 2);
                } else {
                    $data['loading_amount'] = $data['loading_value'] ?? 0;
                }

                $loading = MemberLoading::create($data);

                // Update member loading amount
                $member->loading_amount = $member->activeLoadings()->sum('loading_amount');
                $member->has_pre_existing_conditions = true;
                $member->save();

                // Recalculate policy premium
                $this->premiumCalculator->calculate($member->policy);

                return $loading;
            });

            return $this->success(
                new MemberLoadingResource($loading),
                'Loading added',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to add loading', 500);
        }
    }

    /**
     * Remove/waive loading.
     * DELETE /v1/medical/members/{memberId}/loadings/{loadingId}
     */
    public function removeLoading(string $memberId, string $loadingId): JsonResponse
    {
        try {
            DB::transaction(function () use ($memberId, $loadingId) {
                $loading = MemberLoading::where('member_id', $memberId)
                    ->findOrFail($loadingId);

                $member = $loading->member;
                
                $loading->remove(request('reason'));

                // Update member
                $member->loading_amount = $member->activeLoadings()->sum('loading_amount');
                $member->save();

                // Recalculate policy premium
                $this->premiumCalculator->calculate($member->policy);
            });

            return $this->success(null, 'Loading removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Loading not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to remove loading', 500);
        }
    }

    // =========================================================================
    // EXCLUSIONS
    // =========================================================================

    /**
     * List member exclusions.
     * GET /v1/medical/members/{id}/exclusions
     */
    public function exclusions(string $id): JsonResponse
    {
        try {
            $exclusions = MemberExclusion::where('member_id', $id)
                ->with('benefit')
                ->orderBy('status')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success(
                MemberExclusionResource::collection($exclusions),
                'Exclusions retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve exclusions', 500);
        }
    }

    /**
     * Add exclusion to member.
     * POST /v1/medical/members/{id}/exclusions
     */
    public function addExclusion(MemberExclusionRequest $request, string $id): JsonResponse
    {
        try {
            $exclusion = DB::transaction(function () use ($request, $id) {
                $member = Member::findOrFail($id);

                $data = $request->validated();
                $data['member_id'] = $id;

                $exclusion = MemberExclusion::create($data);

                // Update member flags
                $member->has_pre_existing_conditions = true;
                $member->save();

                return $exclusion;
            });

            return $this->success(
                new MemberExclusionResource($exclusion),
                'Exclusion added',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to add exclusion', 500);
        }
    }

    /**
     * Remove exclusion.
     * DELETE /v1/medical/members/{memberId}/exclusions/{exclusionId}
     */
    public function removeExclusion(string $memberId, string $exclusionId): JsonResponse
    {
        try {
            DB::transaction(function () use ($memberId, $exclusionId) {
                $exclusion = MemberExclusion::where('member_id', $memberId)
                    ->findOrFail($exclusionId);

                $exclusion->remove(request('reason'));
            });

            return $this->success(null, 'Exclusion removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Exclusion not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to remove exclusion', 500);
        }
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    /**
     * List member documents.
     * GET /v1/medical/members/{id}/documents
     */
    public function documents(string $id): JsonResponse
    {
        try {
            $documents = MemberDocument::where('member_id', $id)
                ->active()
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success(
                MemberDocumentResource::collection($documents),
                'Documents retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve documents', 500);
        }
    }

    /**
     * Upload member document.
     * POST /v1/medical/members/{id}/documents
     */
    public function uploadDocument(string $id): JsonResponse
    {
        try {
            $document = DB::transaction(function () use ($id) {
                $member = Member::findOrFail($id);

                request()->validate([
                    'document_type' => 'required|string',
                    'title' => 'required|string|max:255',
                    'file' => 'required|file|max:10240',
                    'expiry_date' => 'nullable|date',
                ]);

                $file = request()->file('file');
                $path = $file->store("members/{$member->id}/documents", 'private');

                return $member->documents()->create([
                    'document_type' => request('document_type'),
                    'title' => request('title'),
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'expiry_date' => request('expiry_date'),
                ]);
            });

            return $this->success(
                new MemberDocumentResource($document),
                'Document uploaded',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('File upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify document.
     * POST /v1/medical/members/{memberId}/documents/{documentId}/verify
     */
    public function verifyDocument(string $memberId, string $documentId): JsonResponse
    {
        try {
            $document = DB::transaction(function () use ($memberId, $documentId) {
                $document = MemberDocument::where('member_id', $memberId)
                    ->findOrFail($documentId);
                $document->verify();
                return $document->fresh();
            });

            return $this->success(
                new MemberDocumentResource($document),
                'Document verified'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Document not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to verify document', 500);
        }
    }

    // =========================================================================
    // DEPENDENTS
    // =========================================================================

    /**
     * Add dependent to principal.
     * POST /v1/medical/members/{id}/dependents
     */
    public function addDependent(MemberRequest $request, string $id): JsonResponse
    {
        try {
            $dependent = DB::transaction(function () use ($request, $id) {
                $principal = Member::findOrFail($id);

                if (!$principal->is_principal) {
                    throw new Exception('Can only add dependents to principal members', 422);
                }

                $plan = $principal->policy->plan;
                if ($principal->dependents()->count() >= $plan->max_dependents) {
                    throw new Exception("Maximum dependents ({$plan->max_dependents}) reached", 422);
                }

                $data = $request->validated();
                $data['policy_id'] = $principal->policy_id;
                $data['principal_id'] = $principal->id;
                $data['cover_start_date'] = $data['cover_start_date'] ?? now();
                $data['cover_end_date'] = $principal->cover_end_date;

                $dependent = Member::create($data);

                // Calculate waiting period
                $waitingEndDate = $dependent->calculateWaitingPeriodEndDate();
                if ($waitingEndDate) {
                    $dependent->waiting_period_end_date = $waitingEndDate;
                    $dependent->save();
                }

                // Calculate premium
                $this->premiumCalculator->calculateMemberPremium($dependent);
                $principal->policy->updateMemberCounts();
                $this->premiumCalculator->calculate($principal->policy);

                return $dependent->load(['policy', 'principal']);
            });

            return $this->success(
                new MemberResource($dependent),
                'Dependent added',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Principal member not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * List dependents of a principal.
     * GET /v1/medical/members/{id}/dependents
     */
    public function dependents(string $id): JsonResponse
    {
        try {
            $dependents = Member::where('principal_id', $id)
                ->with('policy')
                ->orderBy('member_type')
                ->orderBy('created_at')
                ->get();

            return $this->success(
                MemberResource::collection($dependents),
                'Dependents retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve dependents', 500);
        }
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Check member eligibility for claims.
     * GET /v1/medical/members/{id}/eligibility
     */
    public function checkEligibility(string $id): JsonResponse
    {
        try {
            $member = Member::with(['policy', 'activeExclusions.benefit'])
                ->findOrFail($id);

            $eligibility = [
                'is_eligible' => $member->canMakeClaim(),
                'member_status' => $member->status,
                'policy_status' => $member->policy->status,
                'has_cover' => $member->has_cover,
                'in_waiting_period' => $member->is_in_waiting_period,
                'waiting_days_remaining' => $member->waiting_days_remaining,
                'cover_start_date' => $member->cover_start_date,
                'cover_end_date' => $member->cover_end_date,
                'card_status' => $member->card_status,
                'excluded_benefits' => $member->activeExclusions->pluck('benefit.name'),
            ];

            if (!$eligibility['is_eligible']) {
                $reasons = [];
                if (!$member->is_active) $reasons[] = 'Member is not active';
                if (!$member->has_cover) $reasons[] = 'No active cover';
                if ($member->is_in_waiting_period) $reasons[] = 'In waiting period';
                if ($member->policy->status !== MedicalConstants::POLICY_STATUS_ACTIVE) $reasons[] = 'Policy is not active';
                $eligibility['ineligibility_reasons'] = $reasons;
            }

            return $this->success($eligibility, 'Eligibility checked');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to check eligibility', 500);
        }
    }

    /**
     * Get member statistics.
     * GET /v1/medical/members/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Member::count(),
                'active' => Member::active()->count(),
                'pending' => Member::pending()->count(),
                'suspended' => Member::where('status', MedicalConstants::MEMBER_STATUS_SUSPENDED)->count(),
                'principals' => Member::principals()->count(),
                'dependents'=> Member::withCount('dependents')->get(), // Note: This might be heavy if not aggregated, but kept as per original intent
                'in_waiting_period' => Member::inWaitingPeriod()->count(),
                'cards_pending' => Member::where('card_status', MedicalConstants::CARD_STATUS_PENDING)->count(),
            ];

            return $this->success($stats, 'Statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics', 500);
        }
    }
}