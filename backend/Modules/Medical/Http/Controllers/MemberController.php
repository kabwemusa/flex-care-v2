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
use Modules\Medical\Services\PremiumService;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;
use Modules\Medical\Services\MemberService;

class MemberController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MemberService $memberService
    ) {}

    public function index(): JsonResponse
    {
        // Base query with search + non-type filters (used for accurate KPI counts)
        $baseQuery = Member::query()
            ->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('member_number', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('national_id', 'like', "%{$search}%")
                      ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(request('policy_id'), fn ($q, $v) => $q->where('policy_id', $v));

        // Accurate KPI counts (not restricted by member_type filter)
        $principalCount = (clone $baseQuery)->principals()->count();
        $dependentCount = (clone $baseQuery)->dependents()->count();

        // Full query for paginated results (adds member_type filter + eager loads)
        $query = (clone $baseQuery)
            ->with(['policy:id,policy_number,status', 'principal:id,first_name,last_name'])
            ->when(request('member_type'), fn ($q, $v) => $q->where('member_type', $v));

        if (request('principals_only')) $query->principals();
        if (request('dependents_only')) $query->dependents();

        $paginator = $query->paginate(request('per_page', 20));

        return response()->json([
            'status'  => 'success',
            'message' => 'Members retrieved',
            'data'    => MemberListResource::collection($paginator->items()),
            'meta'    => [
                'current_page'    => $paginator->currentPage(),
                'last_page'       => $paginator->lastPage(),
                'per_page'        => $paginator->perPage(),
                'total'           => $paginator->total(),
                'from'            => $paginator->firstItem(),
                'to'              => $paginator->lastItem(),
                'principal_count' => $principalCount,
                'dependent_count' => $dependentCount,
            ],
            'links'   => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function store(MemberRequest $request): JsonResponse
    {
        try {
            $member = $this->memberService->createMember($request->validated());
            
            return $this->success(
                new MemberResource($member->load(['policy', 'principal'])),
                'Member created',
                201
            );
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $member = Member::with([
                'policy.scheme', 'policy.plan', 'principal', 'dependents',
                'activeLoadings', 'activeExclusions', 'documents'
            ])->findOrFail($id);

            return $this->success(new MemberResource($member), 'Member retrieved');
        } catch (Throwable $e) {
            return $this->error('Member not found', 404);
        }
    }

    public function update(MemberRequest $request, string $id): JsonResponse
    {
        try {
            $member = Member::findOrFail($id);
            $updatedMember = $this->memberService->updateMember($member, $request->validated());

            return $this->success(new MemberResource($updatedMember), 'Member updated');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            // Note: In insurance, we rarely "Delete". We "Terminate".
            // But if it's a draft or error, we might delete.
            $member = Member::findOrFail($id);
            if ($member->is_principal && $member->dependents()->exists()) {
                return $this->error('Cannot delete principal with dependents.', 422);
            }
            $this->memberService->terminateMember($member, 'Deleted via API');
            $member->delete(); // Soft delete usually
            
            return $this->success(null, 'Member deleted');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // RISK MANAGEMENT
    // =========================================================================

    public function addLoading(MemberLoadingRequest $request, string $id): JsonResponse
    {
        try {
            $member = Member::findOrFail($id);
            $loading = $this->memberService->addLoading($member, $request->validated());
            
            return $this->success(new MemberLoadingResource($loading), 'Loading applied', 201);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function removeLoading(string $memberId, string $loadingId): JsonResponse
    {
        try {
            $loading = MemberLoading::where('member_id', $memberId)->findOrFail($loadingId);
            $this->memberService->removeLoading($loading, request('reason', 'Manual removal'));
            
            return $this->success(null, 'Loading removed');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // CARDS
    // =========================================================================

    public function issueCard(string $id): JsonResponse
    {
        try {
            $member = Member::findOrFail($id);
            $member = $this->memberService->issueCard($member);
            return $this->success(new MemberResource($member), 'Card issued');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    public function uploadDocument(string $id): JsonResponse
    {
        try {
            $request = request();
            $request->validate([
                'document_type' => 'required|string',
                'title' => 'required|string',
                'file' => 'required|file|max:10240',
            ]);

            $member = Member::findOrFail($id);
            $doc = $this->memberService->uploadDocument($member, $request->all(), $request->file('file'));

            return $this->success(new MemberDocumentResource($doc), 'Document uploaded', 201);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * List members with filtering.
     * GET /v1/medical/members
     */
    // public function index(): JsonResponse
    // {
    //     try {
    //         $query = Member::query()
    //             ->with(['policy:id,policy_number,status', 'principal:id,first_name,last_name']);

    //         // Search
    //         if ($search = request('search')) {
    //             $query->search($search);
    //         }

    //         // Filters
    //         if ($status = request('status')) {
    //             $query->where('status', $status);
    //         }

    //         if ($memberType = request('member_type')) {
    //             $query->where('member_type', $memberType);
    //         }

    //         if ($policyId = request('policy_id')) {
    //             $query->where('policy_id', $policyId);
    //         }

    //         if (request('principals_only')) {
    //             $query->principals();
    //         }

    //         if (request('dependents_only')) {
    //             $query->dependents();
    //         }

    //         if (request('active_only')) {
    //             $query->active();
    //         }

    //         if (request('in_waiting_period')) {
    //             $query->inWaitingPeriod();
    //         }

    //         // Sorting
    //         $sortBy = request('sort_by', 'created_at');
    //         $sortOrder = request('sort_order', 'desc');
    //         $query->orderBy($sortBy, $sortOrder);

    //         $members = $query->paginate(request('per_page', 20));

    //         return $this->success(
    //             MemberListResource::collection($members),
    //             'Members retrieved'
    //         );
    //     } catch (Throwable $e) {
    //         return $this->error('Failed to retrieve members: ' . $e->getMessage(), 500);
    //     }
    // }

    /**
     * Create a new member (principal or dependent).
     * POST /v1/medical/members
     */
   

    /**
     * Show member details.
     * GET /v1/medical/members/{id}
     */
    

    /**
     * Update member.
     * PUT /v1/medical/members/{id}
     */
   

    /**
     * Delete member.
     * DELETE /v1/medical/members/{id}
     */

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
                $effectiveDate = request('effective_date');

                $member->terminate($reason, $notes);

                // If principal, terminate dependents too
                if ($member->is_principal && request('include_dependents', false)) {
                    $member->dependents()
                        ->whereNotIn('status', [MedicalConstants::MEMBER_STATUS_TERMINATED, MedicalConstants::MEMBER_STATUS_DECEASED])
                        ->each(fn($dep) => $dep->terminate('principal_terminated', 'Principal member terminated'));
                }

                // Update policy member counts
                if ($member->policy) {
                    $member->policy->updateMemberCounts();
                }

                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member terminated'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to terminate member: ' . $e->getMessage(), 500);
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

                // Update policy member counts
                if ($member->policy) {
                    $member->policy->updateMemberCounts();
                }

                return $member->fresh();
            });

            return $this->success(
                new MemberResource($member),
                'Member marked as deceased'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to update member status: ' . $e->getMessage(), 500);
        }
    }

    // // =========================================================================
    // // CARD MANAGEMENT
    // // =========================================================================

    // /**
    //  * Issue member card.
    //  * POST /v1/medical/members/{id}/issue-card
    //  */
    // public function issueCard(string $id): JsonResponse
    // {
    //     try {
    //         $member = DB::transaction(function () use ($id) {
    //             $member = Member::findOrFail($id);

    //             if ($member->card_number) {
    //                 throw new Exception('Member already has a card', 422);
    //             }

    //             if (!$member->is_active) {
    //                 throw new Exception('Cannot issue card to inactive member', 422);
    //             }

    //             $member->issueCard();
    //             return $member->fresh();
    //         });

    //         return $this->success(
    //             new MemberResource($member),
    //             'Card issued'
    //         );
    //     } catch (Throwable $e) {
    //         $code = $e->getCode() === 422 ? 422 : 500;
    //         return $this->error($e->getMessage(), $code);
    //     }
    // }

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
     * Get member benefit balances.
     * GET /v1/medical/members/{id}/benefits
     */
    public function benefitBalances(string $id): JsonResponse
    {
        try {
            $member = Member::with(['policy.plan'])->findOrFail($id);
            
            $benefitService = app(\Modules\Medical\Services\BenefitUtilizationService::class);
            $summary = $benefitService->getMemberBenefitSummary($member);

            return $this->success($summary, 'Benefit balances retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefit balances: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get benefit utilization history for a member.
     * GET /v1/medical/members/{id}/benefits/{benefitId}/history
     */
    public function benefitHistory(string $id, string $benefitId): JsonResponse
    {
        try {
            $member = Member::findOrFail($id);
            
            $utilization = \Modules\Medical\Models\MemberBenefitUtilization::where('member_id', $member->id)
                ->where('benefit_id', $benefitId)
                ->currentPeriod()
                ->with(['benefit', 'history.claim:id,claim_number,service_date'])
                ->first();

            if (!$utilization) {
                return $this->error('Benefit utilization not found', 404);
            }

            $data = [
                'utilization' => [
                    'id' => $utilization->id,
                    'benefit_name' => $utilization->benefit?->name,
                    'limit_type' => $utilization->limit_type,
                    'limit' => $utilization->formatted_limit,
                    'used' => $utilization->formatted_used,
                    'remaining' => $utilization->formatted_remaining,
                    'utilization_percentage' => $utilization->utilization_percentage,
                    'is_exhausted' => $utilization->is_exhausted,
                    'period_start' => $utilization->period_start->format('Y-m-d'),
                    'period_end' => $utilization->period_end->format('Y-m-d'),
                ],
                'history' => $utilization->history->map(fn($h) => [
                    'id' => $h->id,
                    'transaction_type' => $h->transaction_type,
                    'transaction_type_label' => $h->transaction_type_label,
                    'amount_change' => $h->amount_change,
                    'count_change' => $h->count_change,
                    'days_change' => $h->days_change,
                    'balance_amount' => $h->balance_amount,
                    'claim_number' => $h->claim?->claim_number,
                    'notes' => $h->notes,
                    'created_at' => $h->created_at->format('Y-m-d H:i:s'),
                ]),
            ];

            return $this->success($data, 'Benefit history retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve benefit history: ' . $e->getMessage(), 500);
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