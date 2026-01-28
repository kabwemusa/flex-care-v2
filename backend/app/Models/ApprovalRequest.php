<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalRequest extends Model implements Auditable
{
    use HasFactory, HasUuids;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'workflow_id',
        'entity_type',
        'entity_id',
        'current_step_id',
        'status',
        'initiated_by',
        'completed_at',
        'final_remarks',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // STATUS CONSTANTS
    // =========================================================================

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RETURNED = 'returned'; // Sent back for amendment
    public const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_RETURNED => 'Returned for Amendment',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the workflow this request uses
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * Get the current step
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'current_step_id');
    }

    /**
     * Get the user who initiated this request
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the approval history
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'request_id')->orderBy('created_at');
    }

    /**
     * Get the entity (polymorphic)
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: Pending requests only
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Completed requests (approved or rejected)
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }

    /**
     * Scope: Requests for a specific entity
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    /**
     * Scope: Requests where user can approve (is member of current step's group)
     */
    public function scopeCanBeApprovedBy($query, User $user)
    {
        return $query->pending()
            ->whereHas('currentStep.group.members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the request was returned for amendment
     */
    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    /**
     * Check if a user can approve this request (instance method)
     */
    public function userCanApprove(User $user): bool
    {
        if (!$this->isPending() || !$this->currentStep) {
            return false;
        }

        return $this->currentStep->canUserApprove($user);
    }

    /**
     * Get current step number (1-indexed for display)
     */
    public function getCurrentStepNumber(): int
    {
        if (!$this->currentStep) {
            return 0;
        }

        return $this->workflow->activeSteps()
            ->where('step_order', '<=', $this->currentStep->step_order)
            ->count();
    }

    /**
     * Get total steps in workflow
     */
    public function getTotalSteps(): int
    {
        return $this->workflow->activeSteps()->count();
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        $total = $this->getTotalSteps();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->histories()->where('action', ApprovalHistory::ACTION_APPROVED)->count();
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Check if this is the last step
     */
    public function isAtLastStep(): bool
    {
        return $this->currentStep && $this->currentStep->isLastStep();
    }

    /**
     * Get the last action taken
     */
    public function getLastAction(): ?ApprovalHistory
    {
        return $this->histories()->latest('actioned_at')->first();
    }
}
