<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Endorsement;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Http\Requests\EndorsementRequest;
use Modules\Medical\Http\Resources\EndorsementResource;
use Modules\Medical\Services\EndorsementService;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class EndorsementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EndorsementService $endorsementService
    ) {}

    /**
     * List all endorsements with filtering.
     * GET /v1/medical/endorsements
     */
    public function index(): JsonResponse
    {
        try {
            $query = Endorsement::query()
                ->with(['policy:id,policy_number,holder_name,status', 'member:id,member_number,first_name,last_name']);

            // Filters
            if ($policyId = request('policy_id')) {
                $query->where('policy_id', $policyId);
            }

            if ($status = request('status')) {
                $query->where('status', $status);
            }

            if ($type = request('endorsement_type')) {
                $query->where('endorsement_type', $type);
            }

            // Search
            if ($search = request('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('endorsement_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Date range
            if ($fromDate = request('from_date')) {
                $query->where('effective_date', '>=', $fromDate);
            }

            if ($toDate = request('to_date')) {
                $query->where('effective_date', '<=', $toDate);
            }

            // Sorting
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $endorsements = $query->paginate(request('per_page', 20));

            return $this->paginated(
                EndorsementResource::collection($endorsements),
                'Endorsements retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve endorsements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get endorsements for a specific policy.
     * GET /v1/medical/policies/{policyId}/endorsements
     */
    public function forPolicy(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);

            $query = $this->endorsementService->getEndorsementsForPolicy($policy, request()->all())
                ->with(['member:id,member_number,first_name,last_name', 'addon:id,name,code', 'newPlan:id,name,code']);

            $endorsements = $query->paginate(request('per_page', 20));

            return $this->paginated(
                EndorsementResource::collection($endorsements),
                'Policy endorsements retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve endorsements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new endorsement.
     * POST /v1/medical/endorsements
     */
    public function store(EndorsementRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $endorsementType = $data['endorsement_type'];

            $endorsement = DB::transaction(function () use ($data, $endorsementType) {
                $policy = Policy::findOrFail($data['policy_id']);

                // Route to appropriate creation method based on type
                switch ($endorsementType) {
                    case MedicalConstants::ENDORSEMENT_TYPE_ADD_MEMBER:
                        return $this->endorsementService->createAddMemberEndorsement(
                            $policy,
                            $data['member_data'],
                            $data['effective_date']
                        );

                    case MedicalConstants::ENDORSEMENT_TYPE_REMOVE_MEMBER:
                        $member = Member::findOrFail($data['member_id']);
                        return $this->endorsementService->createRemoveMemberEndorsement(
                            $policy,
                            $member,
                            $data['reason'],
                            $data['effective_date']
                        );

                    case MedicalConstants::ENDORSEMENT_TYPE_ADD_ADDON:
                        return $this->endorsementService->createAddAddonEndorsement(
                            $policy,
                            $data['addon_id'],
                            $data['effective_date']
                        );

                    case MedicalConstants::ENDORSEMENT_TYPE_UPGRADE_PLAN:
                    case MedicalConstants::ENDORSEMENT_TYPE_DOWNGRADE_PLAN:
                        return $this->endorsementService->createPlanChangeEndorsement(
                            $policy,
                            $data['new_plan_id'],
                            $data['effective_date']
                        );

                    default:
                        // Generic endorsement creation
                        return $this->endorsementService->createEndorsement($data);
                }
            });

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement created',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Resource not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to create endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific endorsement.
     * GET /v1/medical/endorsements/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $endorsement = Endorsement::with([
                'policy:id,policy_number,holder_name,status,plan_id,scheme_id',
                'policy.scheme:id,name,code',
                'policy.plan:id,name,code',
                'member',
                'addon',
                'newPlan',
            ])->findOrFail($id);

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve an endorsement.
     * POST /v1/medical/endorsements/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $endorsement = DB::transaction(function () use ($id) {
                $endorsement = Endorsement::findOrFail($id);

                $approvedBy = auth()->id() ?? 'system';

                return $this->endorsementService->approveEndorsement($endorsement, $approvedBy);
            });

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement approved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to approve endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject an endorsement.
     * POST /v1/medical/endorsements/{id}/reject
     */
    public function reject(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string|max:255',
            ]);

            $endorsement = DB::transaction(function () use ($id) {
                $endorsement = Endorsement::findOrFail($id);

                $rejectedBy = auth()->id() ?? 'system';
                $reason = request('reason');

                return $this->endorsementService->rejectEndorsement($endorsement, $rejectedBy, $reason);
            });

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement rejected'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to reject endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process an approved endorsement.
     * POST /v1/medical/endorsements/{id}/process
     */
    public function process(string $id): JsonResponse
    {
        try {
            $endorsement = DB::transaction(function () use ($id) {
                $endorsement = Endorsement::findOrFail($id);

                $processedBy = auth()->id() ?? 'system';

                return $this->endorsementService->processEndorsement($endorsement, $processedBy);
            });

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement processed successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to process endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel an endorsement.
     * POST /v1/medical/endorsements/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $endorsement = DB::transaction(function () use ($id) {
                $endorsement = Endorsement::findOrFail($id);

                $cancelledBy = auth()->id() ?? 'system';
                $reason = request('reason');

                return $this->endorsementService->cancelEndorsement($endorsement, $cancelledBy, $reason);
            });

            return $this->success(
                new EndorsementResource($endorsement),
                'Endorsement cancelled'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to cancel endorsement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get endorsement statistics.
     * GET /v1/medical/endorsements/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Endorsement::count(),
                'pending' => Endorsement::pending()->count(),
                'approved' => Endorsement::approved()->count(),
                'processed' => Endorsement::processed()->count(),
                'rejected' => Endorsement::rejected()->count(),
                'cancelled' => Endorsement::cancelled()->count(),
                'by_type' => Endorsement::selectRaw('endorsement_type, count(*) as count')
                    ->groupBy('endorsement_type')
                    ->pluck('count', 'endorsement_type'),
                'total_premium_adjustments' => Endorsement::processed()->sum('premium_adjustment'),
            ];

            return $this->success($stats, 'Statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available endorsement types.
     * GET /v1/medical/endorsements/types
     */
    public function types(): JsonResponse
    {
        return $this->success(
            MedicalConstants::ENDORSEMENT_TYPES,
            'Endorsement types retrieved'
        );
    }

    /**
     * Get available endorsement statuses.
     * GET /v1/medical/endorsements/statuses
     */
    public function statuses(): JsonResponse
    {
        return $this->success(
            MedicalConstants::ENDORSEMENT_STATUSES,
            'Endorsement statuses retrieved'
        );
    }
}
