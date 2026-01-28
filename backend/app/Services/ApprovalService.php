<?php

namespace App\Services;

use App\Models\ApprovalGroup;
use App\Models\ApprovalGroupMember;
use App\Models\ApprovalHistory;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Notifications\ApprovalRequiredNotification;
use App\Notifications\ApprovalCompletedNotification;
use App\Notifications\ApprovalRejectedNotification;
use App\Notifications\ApprovalReturnedNotification;
use App\Notifications\ApprovalProgressNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Exception;

class ApprovalService
{
    // =========================================================================
    // APPROVAL GROUP MANAGEMENT
    // =========================================================================

    /**
     * Create a new approval group
     */
    public function createGroup(array $data, User $creator): ApprovalGroup
    {
        return ApprovalGroup::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Update an approval group
     */
    public function updateGroup(ApprovalGroup $group, array $data, User $updater): ApprovalGroup
    {
        $group->update([
            'name' => $data['name'] ?? $group->name,
            'code' => $data['code'] ?? $group->code,
            'description' => $data['description'] ?? $group->description,
            'is_active' => $data['is_active'] ?? $group->is_active,
            'updated_by' => $updater->id,
        ]);

        return $group->fresh();
    }

    /**
     * Add members to a group
     */
    public function addGroupMembers(ApprovalGroup $group, array $userIds, User $addedBy): array
    {
        $added = [];
        foreach ($userIds as $userId) {
            $member = ApprovalGroupMember::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'user_id' => $userId,
                ],
                [
                    'added_by' => $addedBy->id,
                ]
            );
            $added[] = $member;
        }
        return $added;
    }

    /**
     * Remove a member from a group
     */
    public function removeGroupMember(ApprovalGroup $group, string $userId): bool
    {
        return $group->memberships()->where('user_id', $userId)->delete() > 0;
    }

    // =========================================================================
    // APPROVAL WORKFLOW MANAGEMENT
    // =========================================================================

    /**
     * Create a new approval workflow
     */
    public function createWorkflow(array $data, User $creator): ApprovalWorkflow
    {
        return ApprovalWorkflow::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'entity_type' => $data['entity_type'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Update an approval workflow
     */
    public function updateWorkflow(ApprovalWorkflow $workflow, array $data, User $updater): ApprovalWorkflow
    {
        $workflow->update([
            'name' => $data['name'] ?? $workflow->name,
            'code' => $data['code'] ?? $workflow->code,
            'entity_type' => $data['entity_type'] ?? $workflow->entity_type,
            'description' => $data['description'] ?? $workflow->description,
            'is_active' => $data['is_active'] ?? $workflow->is_active,
            'updated_by' => $updater->id,
        ]);

        return $workflow->fresh();
    }

    /**
     * Add a step to a workflow
     */
    public function addWorkflowStep(ApprovalWorkflow $workflow, array $data): ApprovalStep
    {
        // Get the next step order
        $maxOrder = $workflow->steps()->max('step_order') ?? 0;

        return ApprovalStep::create([
            'workflow_id' => $workflow->id,
            'group_id' => $data['group_id'],
            'step_order' => $data['step_order'] ?? ($maxOrder + 1),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a workflow step
     */
    public function updateWorkflowStep(ApprovalStep $step, array $data): ApprovalStep
    {
        $step->update([
            'group_id' => $data['group_id'] ?? $step->group_id,
            'step_order' => $data['step_order'] ?? $step->step_order,
            'name' => $data['name'] ?? $step->name,
            'description' => $data['description'] ?? $step->description,
            'is_active' => $data['is_active'] ?? $step->is_active,
        ]);

        return $step->fresh();
    }

    /**
     * Reorder workflow steps
     */
    public function reorderWorkflowSteps(ApprovalWorkflow $workflow, array $stepIds): void
    {
        DB::transaction(function () use ($workflow, $stepIds) {
            foreach ($stepIds as $order => $stepId) {
                ApprovalStep::where('id', $stepId)
                    ->where('workflow_id', $workflow->id)
                    ->update(['step_order' => $order + 1]);
            }
        });
    }

    /**
     * Delete a workflow step
     */
    public function deleteWorkflowStep(ApprovalStep $step): bool
    {
        // Check if step has any pending requests
        if ($step->pendingRequests()->exists()) {
            throw new Exception('Cannot delete step with pending approval requests');
        }

        return $step->delete();
    }

    // =========================================================================
    // APPROVAL REQUEST MANAGEMENT
    // =========================================================================

    /**
     * Initiate an approval request for an entity
     */
    public function initiateApproval(Model $entity, User $initiator, ?ApprovalWorkflow $workflow = null): ApprovalRequest
    {
        // Check if entity uses the trait
        if (!method_exists($entity, 'getApprovalEntityType')) {
            throw new Exception('Entity must use HasApprovalWorkflow trait');
        }

        $entityType = $entity->getApprovalEntityType();

        // Find workflow if not provided
        if (!$workflow) {
            $workflow = ApprovalWorkflow::active()
                ->forEntity($entityType)
                ->first();
        }

        if (!$workflow) {
            throw new Exception("No active workflow found for entity type: {$entityType}");
        }

        // Check if workflow has steps
        if (!$workflow->hasSteps()) {
            throw new Exception("Workflow '{$workflow->name}' has no active steps configured");
        }

        // Check for existing pending request
        $existingRequest = ApprovalRequest::forEntity($entityType, $entity->id)
            ->pending()
            ->first();

        if ($existingRequest) {
            throw new Exception('Entity already has a pending approval request');
        }

        // Create the approval request
        $firstStep = $workflow->getFirstStep();

        $request = DB::transaction(function () use ($workflow, $entityType, $entity, $initiator, $firstStep) {
            $request = ApprovalRequest::create([
                'workflow_id' => $workflow->id,
                'entity_type' => $entityType,
                'entity_id' => $entity->id,
                'current_step_id' => $firstStep->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'initiated_by' => $initiator->id,
            ]);

            return $request;
        });

        // Notify the first step's group members
        $this->notifyGroupMembers($firstStep->group, $request, 'approval_required');

        return $request;
    }

    /**
     * Approve a request at the current step
     */
    public function approve(ApprovalRequest $request, User $approver, ?string $comments = null): ApprovalRequest
    {
        if (!$request->isPending()) {
            throw new Exception('Request is not pending');
        }

        if (!$request->userCanApprove($approver)) {
            throw new Exception('User is not authorized to approve at this step');
        }

        $currentStep = $request->currentStep;
        $isLastStep = $currentStep->isLastStep();

        return DB::transaction(function () use ($request, $approver, $comments, $currentStep, $isLastStep) {
            // Record the approval in history
            ApprovalHistory::create([
                'request_id' => $request->id,
                'step_id' => $currentStep->id,
                'action' => ApprovalHistory::ACTION_APPROVED,
                'actioned_by' => $approver->id,
                'comments' => $comments,
                'actioned_at' => now(),
            ]);

            if ($isLastStep) {
                // Final approval - complete the request
                $request->update([
                    'status' => ApprovalRequest::STATUS_APPROVED,
                    'current_step_id' => null,
                    'completed_at' => now(),
                ]);

                // Trigger entity callback
                $entity = $request->entity;
                if ($entity && method_exists($entity, 'onApprovalCompleted')) {
                    $entity->onApprovalCompleted($request);
                }

                // Notify initiator of completion
                $this->notifyUser($request->initiator, $request, 'approval_completed');
            } else {
                // Move to next step
                $nextStep = $currentStep->getNextStep();
                $request->update([
                    'current_step_id' => $nextStep->id,
                ]);

                // Notify next step's group members
                $this->notifyGroupMembers($nextStep->group, $request, 'approval_required');

                // Notify initiator of progress
                $this->notifyUser($request->initiator, $request, 'approval_progress');
            }

            return $request->fresh();
        });
    }

    /**
     * Reject a request
     */
    public function reject(ApprovalRequest $request, User $rejector, string $reason): ApprovalRequest
    {
        if (!$request->isPending()) {
            throw new Exception('Request is not pending');
        }

        if (!$request->userCanApprove($rejector)) {
            throw new Exception('User is not authorized to reject at this step');
        }

        $currentStep = $request->currentStep;

        return DB::transaction(function () use ($request, $rejector, $reason, $currentStep) {
            // Record the rejection in history
            ApprovalHistory::create([
                'request_id' => $request->id,
                'step_id' => $currentStep->id,
                'action' => ApprovalHistory::ACTION_REJECTED,
                'actioned_by' => $rejector->id,
                'comments' => $reason,
                'actioned_at' => now(),
            ]);

            // Update request status
            $request->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'current_step_id' => null,
                'completed_at' => now(),
                'final_remarks' => $reason,
            ]);

            // Trigger entity callback
            $entity = $request->entity;
            if ($entity && method_exists($entity, 'onApprovalRejected')) {
                $entity->onApprovalRejected($request, $reason);
            }

            // Notify initiator
            $this->notifyUser($request->initiator, $request, 'approval_rejected');

            return $request->fresh();
        });
    }

    /**
     * Return a request for amendment
     */
    public function returnForAmendment(ApprovalRequest $request, User $returner, string $reason): ApprovalRequest
    {
        if (!$request->isPending()) {
            throw new Exception('Request is not pending');
        }

        if (!$request->userCanApprove($returner)) {
            throw new Exception('User is not authorized to return at this step');
        }

        $currentStep = $request->currentStep;

        return DB::transaction(function () use ($request, $returner, $reason, $currentStep) {
            // Record the return in history
            ApprovalHistory::create([
                'request_id' => $request->id,
                'step_id' => $currentStep->id,
                'action' => ApprovalHistory::ACTION_RETURNED,
                'actioned_by' => $returner->id,
                'comments' => $reason,
                'actioned_at' => now(),
            ]);

            // Update request status
            $request->update([
                'status' => ApprovalRequest::STATUS_RETURNED,
                'final_remarks' => $reason,
            ]);

            // Trigger entity callback
            $entity = $request->entity;
            if ($entity && method_exists($entity, 'onApprovalReturned')) {
                $entity->onApprovalReturned($request, $reason);
            }

            // Notify initiator
            $this->notifyUser($request->initiator, $request, 'approval_returned');

            return $request->fresh();
        });
    }

    /**
     * Resubmit an entity after amendment
     */
    public function resubmitAfterAmendment(Model $entity, User $initiator): ApprovalRequest
    {
        $entityType = $entity->getApprovalEntityType();

        // Find the returned request
        $returnedRequest = ApprovalRequest::forEntity($entityType, $entity->id)
            ->where('status', ApprovalRequest::STATUS_RETURNED)
            ->latest()
            ->first();

        if (!$returnedRequest) {
            throw new Exception('No returned approval request found for this entity');
        }

        // Get the workflow
        $workflow = $returnedRequest->workflow;
        $firstStep = $workflow->getFirstStep();

        return DB::transaction(function () use ($returnedRequest, $workflow, $entityType, $entity, $initiator, $firstStep) {
            // Mark old request as cancelled (keep history)
            $returnedRequest->update([
                'status' => ApprovalRequest::STATUS_CANCELLED,
                'final_remarks' => 'Resubmitted after amendment',
            ]);

            // Create new request
            $newRequest = ApprovalRequest::create([
                'workflow_id' => $workflow->id,
                'entity_type' => $entityType,
                'entity_id' => $entity->id,
                'current_step_id' => $firstStep->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'initiated_by' => $initiator->id,
            ]);

            // Notify the first step's group members
            $this->notifyGroupMembers($firstStep->group, $newRequest, 'approval_required');

            return $newRequest;
        });
    }

    /**
     * Cancel an approval request
     */
    public function cancelApproval(ApprovalRequest $request, User $user, ?string $reason = null): bool
    {
        if (!$request->isPending()) {
            return false;
        }

        $request->update([
            'status' => ApprovalRequest::STATUS_CANCELLED,
            'completed_at' => now(),
            'final_remarks' => $reason ?? 'Cancelled by user',
        ]);

        return true;
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Get pending approvals for a user
     */
    public function getPendingApprovalsForUser(User $user, ?string $entityType = null)
    {
        $query = ApprovalRequest::canBeApprovedBy($user)
            ->with(['workflow', 'currentStep.group', 'initiator']);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->latest()->get();
    }

    /**
     * Get approval history for a user (actions they've taken)
     */
    public function getApprovalHistoryForUser(User $user, ?int $limit = 50)
    {
        return ApprovalHistory::where('actioned_by', $user->id)
            ->with(['request.workflow', 'step'])
            ->latest('actioned_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all approval requests for an entity
     */
    public function getApprovalsForEntity(string $entityType, string $entityId)
    {
        return ApprovalRequest::forEntity($entityType, $entityId)
            ->with(['workflow', 'currentStep', 'histories.actionedBy', 'histories.step'])
            ->latest()
            ->get();
    }

    // =========================================================================
    // NOTIFICATION METHODS
    // =========================================================================

    /**
     * Notify all members of a group
     */
    protected function notifyGroupMembers(ApprovalGroup $group, ApprovalRequest $request, string $type): void
    {
        $members = $group->members()->where('is_active', true)->get();

        foreach ($members as $member) {
            $this->notifyUser($member, $request, $type);
        }
    }

    /**
     * Notify a single user
     */
    protected function notifyUser(User $user, ApprovalRequest $request, string $type): void
    {
        try {
            $notification = match ($type) {
                'approval_required' => new ApprovalRequiredNotification($request),
                'approval_completed' => new ApprovalCompletedNotification($request),
                'approval_rejected' => new ApprovalRejectedNotification($request),
                'approval_returned' => new ApprovalReturnedNotification($request),
                'approval_progress' => new ApprovalProgressNotification($request),
                default => null,
            };

            if ($notification) {
                $user->notify($notification);
            }
        } catch (Exception $e) {
            // Log error but don't fail the approval process
            Log::error('Failed to send approval notification', [
                'user_id' => $user->id,
                'request_id' => $request->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
