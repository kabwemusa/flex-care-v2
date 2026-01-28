<?php

namespace App\Traits;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasApprovalWorkflow
 *
 * Add this trait to any model that needs approval workflow support.
 * The model must implement getApprovalEntityType() to return its entity type string.
 *
 * Usage:
 * 1. Add the trait to your model
 * 2. Implement getApprovalEntityType() method
 * 3. Optionally implement onApprovalCompleted() for callback when approved
 * 4. Optionally implement onApprovalRejected() for callback when rejected
 * 5. Optionally implement onApprovalReturned() for callback when returned
 */
trait HasApprovalWorkflow
{
    /**
     * Get the entity type for approval workflow
     * Override this in your model
     */
    abstract public function getApprovalEntityType(): string;

    /**
     * Get all approval requests for this entity
     */
    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'entity', 'entity_type', 'entity_id');
    }

    /**
     * Get the current/latest approval request
     */
    public function getCurrentApprovalRequest(): ?ApprovalRequest
    {
        return $this->approvalRequests()
            ->latest()
            ->first();
    }

    /**
     * Get the active (pending) approval request
     */
    public function getActiveApprovalRequest(): ?ApprovalRequest
    {
        return $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->latest()
            ->first();
    }

    /**
     * Check if entity has a pending approval request
     */
    public function hasPendingApproval(): bool
    {
        return $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * Check if entity was approved
     */
    public function wasApproved(): bool
    {
        return $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Check if entity was rejected
     */
    public function wasRejected(): bool
    {
        return $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_REJECTED)
            ->exists();
    }

    /**
     * Check if entity was returned for amendment
     */
    public function wasReturned(): bool
    {
        return $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_RETURNED)
            ->exists();
    }

    /**
     * Submit entity for approval
     *
     * @param User $initiator The user submitting for approval
     * @param ApprovalWorkflow|null $workflow Optional specific workflow (auto-detects if null)
     * @return ApprovalRequest
     * @throws \Exception If no workflow found or already pending
     */
    public function submitForApproval(User $initiator, ?ApprovalWorkflow $workflow = null): ApprovalRequest
    {
        $service = app(ApprovalService::class);
        return $service->initiateApproval($this, $initiator, $workflow);
    }

    /**
     * Resubmit entity after amendment (after being returned)
     */
    public function resubmitForApproval(User $initiator): ApprovalRequest
    {
        $service = app(ApprovalService::class);
        return $service->resubmitAfterAmendment($this, $initiator);
    }

    /**
     * Cancel the current approval request
     */
    public function cancelApproval(User $user, ?string $reason = null): bool
    {
        $service = app(ApprovalService::class);
        $request = $this->getActiveApprovalRequest();

        if (!$request) {
            return false;
        }

        return $service->cancelApproval($request, $user, $reason);
    }

    /**
     * Get the workflow for this entity type
     */
    public function getApprovalWorkflow(): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::active()
            ->forEntity($this->getApprovalEntityType())
            ->first();
    }

    /**
     * Check if a workflow exists for this entity type
     */
    public function hasApprovalWorkflow(): bool
    {
        return $this->getApprovalWorkflow() !== null;
    }

    /**
     * Get approval progress info
     */
    public function getApprovalProgress(): array
    {
        $request = $this->getCurrentApprovalRequest();

        if (!$request) {
            return [
                'has_request' => false,
                'status' => null,
                'current_step' => null,
                'total_steps' => 0,
                'progress_percentage' => 0,
                'histories' => [],
            ];
        }

        return [
            'has_request' => true,
            'request_id' => $request->id,
            'status' => $request->status,
            'current_step' => $request->currentStep ? [
                'id' => $request->currentStep->id,
                'name' => $request->currentStep->name,
                'order' => $request->currentStep->step_order,
                'group' => $request->currentStep->group->name,
            ] : null,
            'current_step_number' => $request->getCurrentStepNumber(),
            'total_steps' => $request->getTotalSteps(),
            'progress_percentage' => $request->getProgressPercentage(),
            'initiated_by' => $request->initiator ? [
                'id' => $request->initiator->id,
                'name' => $request->initiator->username,
            ] : null,
            'initiated_at' => $request->created_at,
            'completed_at' => $request->completed_at,
        ];
    }

    /**
     * Callback when approval is completed (all steps approved)
     * Override this in your model to handle approval completion
     */
    public function onApprovalCompleted(ApprovalRequest $request): void
    {
        // Override in model to handle approval completion
        // Example: $this->update(['status' => 'approved']);
    }

    /**
     * Callback when approval is rejected
     * Override this in your model to handle rejection
     */
    public function onApprovalRejected(ApprovalRequest $request, string $reason): void
    {
        // Override in model to handle rejection
        // Example: $this->update(['status' => 'rejected']);
    }

    /**
     * Callback when approval is returned for amendment
     * Override this in your model to handle return
     */
    public function onApprovalReturned(ApprovalRequest $request, string $reason): void
    {
        // Override in model to handle return for amendment
        // Example: $this->update(['status' => 'draft']);
    }
}
