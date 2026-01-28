<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    use ApiResponse;

    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Get pending approvals for the current user
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $entityType = $request->get('entity_type');

            $approvals = $this->approvalService->getPendingApprovalsForUser(
                $request->user(),
                $entityType
            );

            // Transform for response
            $data = $approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'workflow' => [
                        'id' => $approval->workflow->id,
                        'name' => $approval->workflow->name,
                    ],
                    'entity_type' => $approval->entity_type,
                    'entity_id' => $approval->entity_id,
                    'entity_reference' => $this->getEntityReference($approval),
                    'current_step' => [
                        'id' => $approval->currentStep->id,
                        'name' => $approval->currentStep->name,
                        'group' => $approval->currentStep->group->name,
                    ],
                    'initiated_by' => [
                        'id' => $approval->initiator->id,
                        'name' => $approval->initiator->username,
                    ],
                    'created_at' => $approval->created_at,
                    'progress' => [
                        'current' => $approval->getCurrentStepNumber(),
                        'total' => $approval->getTotalSteps(),
                        'percentage' => $approval->getProgressPercentage(),
                    ],
                ];
            });

            return $this->success($data, 'Pending approvals retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching pending approvals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch pending approvals', 500);
        }
    }

    /**
     * Get approval history for the current user
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);

            $history = $this->approvalService->getApprovalHistoryForUser(
                $request->user(),
                $limit
            );

            // Transform for response
            $data = $history->map(function ($item) {
                return [
                    'id' => $item->id,
                    'action' => $item->action,
                    'action_label' => $item->getActionLabel(),
                    'comments' => $item->comments,
                    'actioned_at' => $item->actioned_at,
                    'step' => [
                        'id' => $item->step->id,
                        'name' => $item->step->name,
                    ],
                    'request' => [
                        'id' => $item->request->id,
                        'entity_type' => $item->request->entity_type,
                        'entity_id' => $item->request->entity_id,
                        'workflow_name' => $item->request->workflow->name,
                    ],
                ];
            });

            return $this->success($data, 'Approval history retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching approval history: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval history', 500);
        }
    }

    /**
     * Get details of a specific approval request
     */
    public function show(string $id): JsonResponse
    {
        try {
            $approval = ApprovalRequest::with([
                'workflow',
                'currentStep.group',
                'initiator',
                'histories' => function ($q) {
                    $q->with(['actionedBy', 'step'])->orderBy('actioned_at', 'desc');
                }
            ])->findOrFail($id);

            $data = [
                'id' => $approval->id,
                'status' => $approval->status,
                'status_label' => ApprovalRequest::getStatuses()[$approval->status] ?? $approval->status,
                'workflow' => [
                    'id' => $approval->workflow->id,
                    'name' => $approval->workflow->name,
                    'entity_type' => $approval->workflow->entity_type,
                ],
                'entity_type' => $approval->entity_type,
                'entity_id' => $approval->entity_id,
                'entity_reference' => $this->getEntityReference($approval),
                'current_step' => $approval->currentStep ? [
                    'id' => $approval->currentStep->id,
                    'name' => $approval->currentStep->name,
                    'order' => $approval->currentStep->step_order,
                    'group' => [
                        'id' => $approval->currentStep->group->id,
                        'name' => $approval->currentStep->group->name,
                    ],
                ] : null,
                'initiated_by' => [
                    'id' => $approval->initiator->id,
                    'name' => $approval->initiator->username,
                    'email' => $approval->initiator->email,
                ],
                'progress' => [
                    'current' => $approval->getCurrentStepNumber(),
                    'total' => $approval->getTotalSteps(),
                    'percentage' => $approval->getProgressPercentage(),
                ],
                'final_remarks' => $approval->final_remarks,
                'created_at' => $approval->created_at,
                'completed_at' => $approval->completed_at,
                'histories' => $approval->histories->map(function ($h) {
                    return [
                        'id' => $h->id,
                        'action' => $h->action,
                        'action_label' => $h->getActionLabel(),
                        'comments' => $h->comments,
                        'actioned_at' => $h->actioned_at,
                        'actioned_by' => [
                            'id' => $h->actionedBy->id,
                            'name' => $h->actionedBy->username,
                        ],
                        'step' => [
                            'id' => $h->step->id,
                            'name' => $h->step->name,
                        ],
                    ];
                }),
            ];

            return $this->success($data, 'Approval request retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval request not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching approval request: ' . $e->getMessage(), [
                'approval_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval request', 500);
        }
    }

    /**
     * Approve a request
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'comments' => 'nullable|string|max:2000',
            ]);

            $approval = $this->approvalService->approve(
                $approval,
                $request->user(),
                $validated['comments'] ?? null
            );

            return $this->success([
                'id' => $approval->id,
                'status' => $approval->status,
                'message' => $approval->isApproved()
                    ? 'Request fully approved'
                    : 'Step approved, moved to next step',
            ], 'Approval successful');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval request not found', 404);
        } catch (\Exception $e) {
            Log::error('Error approving request: ' . $e->getMessage(), [
                'approval_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Reject a request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'reason' => 'required|string|max:2000',
            ]);

            $approval = $this->approvalService->reject(
                $approval,
                $request->user(),
                $validated['reason']
            );

            return $this->success([
                'id' => $approval->id,
                'status' => $approval->status,
            ], 'Request rejected');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval request not found', 404);
        } catch (\Exception $e) {
            Log::error('Error rejecting request: ' . $e->getMessage(), [
                'approval_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Return a request for amendment
     */
    public function returnForAmendment(Request $request, string $id): JsonResponse
    {
        try {
            $approval = ApprovalRequest::findOrFail($id);

            $validated = $request->validate([
                'reason' => 'required|string|max:2000',
            ]);

            $approval = $this->approvalService->returnForAmendment(
                $approval,
                $request->user(),
                $validated['reason']
            );

            return $this->success([
                'id' => $approval->id,
                'status' => $approval->status,
            ], 'Request returned for amendment');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval request not found', 404);
        } catch (\Exception $e) {
            Log::error('Error returning request: ' . $e->getMessage(), [
                'approval_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get entity reference string
     */
    protected function getEntityReference(ApprovalRequest $approval): string
    {
        $entity = $approval->entity;

        if ($entity) {
            if (isset($entity->application_number)) {
                return "Application #{$entity->application_number}";
            }
            if (isset($entity->claim_number)) {
                return "Claim #{$entity->claim_number}";
            }
            if (isset($entity->invoice_number)) {
                return "Invoice #{$entity->invoice_number}";
            }
            if (isset($entity->policy_number)) {
                return "Policy #{$entity->policy_number}";
            }
            if (isset($entity->reference_number)) {
                return "#{$entity->reference_number}";
            }
        }

        $entityType = ucfirst(str_replace('_', ' ', $approval->entity_type));
        return "{$entityType} #{$approval->entity_id}";
    }
}
