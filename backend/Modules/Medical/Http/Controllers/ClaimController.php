<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\ClaimDocument;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Http\Requests\ClaimRequest;
use Modules\Medical\Http\Resources\ClaimResource;
use Modules\Medical\Http\Resources\ClaimListResource;
use Modules\Medical\Services\ClaimService;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Services\BenefitUtilizationService;
use App\Traits\ApiResponse;
use Throwable;
use Exception;


class ClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ClaimService $claimService
    ) {}

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * List all claims with filtering.
     * GET /v1/medical/claims
     */
    public function index(): JsonResponse
    {
        try {
            $query = Claim::query()
                ->with([
                    'policy:id,policy_number,holder_name,status',
                    'member:id,member_number,first_name,last_name,relationship',
                ]);

            // Search
            $query->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('claim_number', 'like', "%{$search}%")
                        ->orWhere('provider_name', 'like', "%{$search}%")
                        ->orWhere('primary_diagnosis', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($mq) use ($search) {
                            $mq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('member_number', 'like', "%{$search}%");
                        });
                });
            });

            // Filters
            $query->when(request('status'), fn($q, $v) => $q->where('status', $v))
                ->when(request('claim_type'), fn($q, $v) => $q->where('claim_type', $v))
                ->when(request('policy_id'), fn($q, $v) => $q->where('policy_id', $v))
                ->when(request('member_id'), fn($q, $v) => $q->where('member_id', $v))
                ->when(request('assigned_to'), fn($q, $v) => $q->where('assigned_to', $v))
                ->when(request('unassigned'), fn($q) => $q->whereNull('assigned_to'))
                ->when(request('flagged'), fn($q) => $q->where('is_flagged', true))
                ->when(request('high_priority'), fn($q) => $q->where('priority', '<=', 3));

            // Date filters
            $query->when(request('service_date_from'), fn($q, $v) =>
                $q->whereDate('service_date', '>=', $v)
            );
            $query->when(request('service_date_to'), fn($q, $v) =>
                $q->whereDate('service_date', '<=', $v)
            );
            $query->when(request('created_from'), fn($q, $v) =>
                $q->whereDate('created_at', '>=', $v)
            );
            $query->when(request('created_to'), fn($q, $v) =>
                $q->whereDate('created_at', '<=', $v)
            );

            // Sorting
            $allowedSorts = ['created_at', 'claim_number', 'service_date', 'claimed_amount', 'status', 'priority'];
            $sortBy = in_array(request('sort_by'), $allowedSorts) ? request('sort_by') : 'created_at';
            $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = min((int) request('per_page', 20), 100);
            $claims = $query->paginate($perPage);

            return $this->paginated(
                ClaimListResource::collection($claims),
                'Claims retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve claims: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get claims for a specific policy.
     * GET /v1/medical/policies/{policyId}/claims
     */
    public function forPolicy(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);

            $query = $this->claimService->getClaimsForPolicy($policy, request()->all())
                ->with(['member:id,member_number,first_name,last_name,relationship']);

            $claims = $query->paginate(request('per_page', 20));

            return $this->paginated(
                ClaimListResource::collection($claims),
                'Policy claims retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve claims: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get claims for a specific member.
     * GET /v1/medical/members/{memberId}/claims
     */
    public function forMember(string $memberId): JsonResponse
    {
        try {
            $member = Member::findOrFail($memberId);

            $query = $this->claimService->getClaimsForMember($member, request()->all())
                ->with(['policy:id,policy_number,holder_name']);

            $claims = $query->paginate(request('per_page', 20));

            return $this->paginated(
                ClaimListResource::collection($claims),
                'Member claims retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve claims: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check benefit eligibility before submitting a claim.
     * POST /v1/medical/claims/check-benefit-eligibility
     */
    public function checkBenefitEligibility(): JsonResponse
    {
        try {
            $validated = request()->validate([
                'member_id' => 'required|uuid|exists:med_members,id',
                'benefit_id' => 'required|uuid|exists:med_benefits,id',
                'amount' => 'required|numeric|min:0',
                'service_date' => 'nullable|date',
            ]);

            $member = Member::with(['policy.plan'])->findOrFail($validated['member_id']);
            $benefitService = app(BenefitUtilizationService::class);

            $serviceDate = isset($validated['service_date']) 
                ? \Carbon\Carbon::parse($validated['service_date']) 
                : null;

            $result = $benefitService->checkBenefitEligibility(
                $member,
                $validated['benefit_id'],
                $validated['amount'],
                $serviceDate
            );

            return $this->success($result, 'Benefit eligibility checked');
        } catch (ModelNotFoundException $e) {
            return $this->error('Member not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to check eligibility: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new claim.
     * POST /v1/medical/claims
     */
    public function store(ClaimRequest $request): JsonResponse
    {
        try {
            $claim = DB::transaction(function () use ($request) {
                return $this->claimService->createClaim($request->validated());
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim submitted successfully',
                201
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to create claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific claim.
     * GET /v1/medical/claims/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $claim = Claim::with([
                'policy:id,policy_number,holder_name,status,inception_date,expiry_date,plan_id,scheme_id',
                'policy.plan:id,name,code',
                'policy.scheme:id,name,code',
                'member',
                'lines.benefit:id,name,code',
                'documents' => fn($q) => $q->active()->orderBy('created_at', 'desc'),
                'notes' => fn($q) => $q->orderBy('created_at', 'desc')->limit(50),
            ])->findOrFail($id);

            return $this->success(
                new ClaimResource($claim),
                'Claim retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a claim.
     * PUT /v1/medical/claims/{id}
     */
    public function update(ClaimRequest $request, string $id): JsonResponse
    {
        try {
            $claim = DB::transaction(function () use ($request, $id) {
                $claim = Claim::findOrFail($id);

                if (!$claim->can_be_edited) {
                    throw new Exception('Claim cannot be edited in current status: ' . $claim->status);
                }

                $claim->update($request->validated());

                return $claim->fresh(['policy', 'member', 'lines']);
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to update claim: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // WORKFLOW ACTIONS
    // =========================================================================

    /**
     * Start reviewing a claim.
     * POST /v1/medical/claims/{id}/start-review
     */
    public function startReview(string $id): JsonResponse
    {
        try {
            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::with([
                    'policy:id,policy_number,holder_name,status,inception_date,expiry_date,plan_id,scheme_id',
                    'policy.plan:id,name,code',
                    'policy.scheme:id,name,code',
                    'member',
                    'lines.benefit:id,name,code',
                    'documents' => fn($q) => $q->active()->orderBy('created_at', 'desc'),
                    'notes' => fn($q) => $q->orderBy('created_at', 'desc')->limit(50),
                ])->findOrFail($id);
                $reviewerId = auth()->id() ?? request('reviewer_id');
                return $this->claimService->startReview($claim, $reviewerId);
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim review started'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to start review: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Request additional documents.
     * POST /v1/medical/claims/{id}/request-documents
     */
    public function requestDocuments(string $id): JsonResponse
    {
        try {
            request()->validate([
                'required_documents' => 'required|array|min:1',
                'required_documents.*' => 'string',
                'notes' => 'nullable|string|max:1000',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $requestedBy = auth()->id() ?? request('requested_by');
                return $this->claimService->requestDocuments(
                    $claim,
                    $requestedBy,
                    request('required_documents'),
                    request('notes')
                );
            });

            return $this->success(
                new ClaimResource($claim),
                'Document request sent'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to request documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Adjudicate claim lines.
     * POST /v1/medical/claims/{id}/adjudicate
     */
    public function adjudicate(string $id): JsonResponse
    {
        try {
            request()->validate([
                'line_decisions' => 'required|array|min:1',
                'line_decisions.*.line_id' => 'required|uuid',
                'line_decisions.*.action' => 'required|in:approve,reject',
                'line_decisions.*.approved_amount' => 'nullable|numeric|min:0',
                'line_decisions.*.copay_amount' => 'nullable|numeric|min:0',
                'line_decisions.*.deductible_amount' => 'nullable|numeric|min:0',
                'line_decisions.*.excess_amount' => 'nullable|numeric|min:0',
                'line_decisions.*.excluded_amount' => 'nullable|numeric|min:0',
                'line_decisions.*.reason' => 'nullable|string',
                'line_decisions.*.notes' => 'nullable|string',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $adjudicatorId = auth()->id() ?? request('adjudicator_id');
                return $this->claimService->adjudicateClaim($claim, request('line_decisions'), $adjudicatorId);
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim adjudicated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to adjudicate claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quick approve entire claim.
     * POST /v1/medical/claims/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $approverId = auth()->id() ?? request('approver_id');
                return $this->claimService->approveClaim($claim, $approverId, request('notes'));
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim approved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to approve claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a claim.
     * POST /v1/medical/claims/{id}/reject
     */
    public function reject(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string',
                'notes' => 'nullable|string|max:1000',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $rejectedBy = auth()->id() ?? request('rejected_by');
                return $this->claimService->rejectClaim(
                    $claim,
                    $rejectedBy,
                    request('reason'),
                    request('notes')
                );
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim rejected'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to reject claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record payment for a claim.
     * POST /v1/medical/claims/{id}/pay
     */
    public function pay(string $id): JsonResponse
    {
        try {
            request()->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string',
                'payment_reference' => 'nullable|string|max:50',
                'payment_date' => 'nullable|date',
                'paid_to' => 'nullable|string|in:provider,member',
                'bank_name' => 'nullable|string|max:100',
                'account_number' => 'nullable|string|max:50',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $processedBy = auth()->id() ?? request('processed_by');
                return $this->claimService->recordPayment($claim, request()->all(), $processedBy);
            });

            return $this->success(
                new ClaimResource($claim),
                'Payment recorded'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Close a claim.
     * POST /v1/medical/claims/{id}/close
     */
    public function close(string $id): JsonResponse
    {
        try {
            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $closedBy = auth()->id() ?? request('closed_by');
                return $this->claimService->closeClaim($claim, $closedBy, request('notes'));
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim closed'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to close claim: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign a claim to a user.
     * POST /v1/medical/claims/{id}/assign
     */
    public function assign(string $id): JsonResponse
    {
        try {
            request()->validate([
                'assigned_to' => 'required|string',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);
                $claim->assign(request('assigned_to'));
                return $claim->fresh();
            });

            return $this->success(
                new ClaimResource($claim),
                'Claim assigned'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to assign claim: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // CLAIM LINES
    // =========================================================================

    /**
     * Add a line to a claim.
     * POST /v1/medical/claims/{id}/lines
     */
    public function addLine(string $id): JsonResponse
    {
        try {
            request()->validate([
                'service_description' => 'required|string|max:255',
                'service_date' => 'nullable|date',
                'service_code' => 'nullable|string|max:30',
                'benefit_id' => 'nullable|uuid|exists:med_benefits,id',
                'quantity' => 'nullable|numeric|min:0.01',
                'unit' => 'nullable|string|max:20',
                'unit_price' => 'nullable|numeric|min:0',
                'claimed_amount' => 'required|numeric|min:0.01',
            ]);

            $claim = DB::transaction(function () use ($id) {
                $claim = Claim::findOrFail($id);

                if (!$claim->can_be_edited) {
                    throw new Exception('Cannot add lines to claim in current status');
                }

                $this->claimService->addClaimLines($claim, [request()->all()]);

                return $claim->fresh(['lines']);
            });

            return $this->success(
                new ClaimResource($claim),
                'Line added',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to add line: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    /**
     * Upload a document to a claim.
     * POST /v1/medical/claims/{id}/documents
     */
    public function uploadDocument(string $id): JsonResponse
    {
        try {
            request()->validate([
                'document_type' => 'required|string',
                'title' => 'required|string|max:255',
                'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            ]);

            $claim = Claim::findOrFail($id);
            $file = request()->file('file');
            $path = $file->store("claims/{$claim->id}", 'local');

            $document = ClaimDocument::create([
                'claim_id' => $id,
                'document_type' => request('document_type'),
                'title' => request('title'),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
                'upload_source' => MedicalConstants::SUBMISSION_CHANNEL_PORTAL,
            ]);

            return $this->success($document, 'Document uploaded', 201);
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to upload document: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // NOTES
    // =========================================================================

    /**
     * Add a note to a claim.
     * POST /v1/medical/claims/{id}/notes
     */
    public function addNote(string $id): JsonResponse
    {
        try {
            request()->validate([
                'content' => 'required|string|max:2000',
                'is_internal' => 'nullable|boolean',
            ]);

            $claim = Claim::findOrFail($id);

            $note = $claim->addNote(
                MedicalConstants::CLAIM_NOTE_COMMENT,
                request('content'),
                auth()->id(),
                ['is_internal' => request('is_internal', true)]
            );

            return $this->success($note, 'Note added', 201);
        } catch (ModelNotFoundException $e) {
            return $this->error('Claim not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to add note: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get claim statistics.
     * GET /v1/medical/claims/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->claimService->getStatistics(request()->all());

            return $this->success($stats, 'Statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available claim types.
     * GET /v1/medical/claims/types
     */
    public function types(): JsonResponse
    {
        return $this->success(MedicalConstants::CLAIM_TYPES, 'Claim types retrieved');
    }

    /**
     * Get available claim statuses.
     * GET /v1/medical/claims/statuses
     */
    public function statuses(): JsonResponse
    {
        return $this->success(MedicalConstants::CLAIM_STATUSES, 'Claim statuses retrieved');
    }

    /**
     * Get rejection reasons.
     * GET /v1/medical/claims/rejection-reasons
     */
    public function rejectionReasons(): JsonResponse
    {
        return $this->success(MedicalConstants::CLAIM_REJECTION_REASONS, 'Rejection reasons retrieved');
    }
}
