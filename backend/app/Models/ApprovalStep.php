<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalStep extends Model implements Auditable
{
    use HasFactory, HasUuids;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'workflow_id',
        'group_id',
        'step_order',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the workflow this step belongs to
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * Get the group assigned to this step
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ApprovalGroup::class, 'group_id');
    }

    /**
     * Get the approval histories for this step
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'step_id');
    }

    /**
     * Get the approval requests currently at this step
     */
    public function pendingRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'current_step_id')
            ->where('status', ApprovalRequest::STATUS_PENDING);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: Active steps only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get members who can approve at this step
     */
    public function getApprovers()
    {
        return $this->group->members()->where('is_active', true)->get();
    }

    /**
     * Check if a user can approve at this step
     */
    public function canUserApprove(User $user): bool
    {
        return $this->group->hasMember($user);
    }

    /**
     * Check if this is the first step in the workflow
     */
    public function isFirstStep(): bool
    {
        return $this->workflow->activeSteps()
            ->where('step_order', '<', $this->step_order)
            ->doesntExist();
    }

    /**
     * Check if this is the last step in the workflow
     */
    public function isLastStep(): bool
    {
        return $this->workflow->isLastStep($this);
    }

    /**
     * Get the next step in the workflow
     */
    public function getNextStep(): ?ApprovalStep
    {
        return $this->workflow->getNextStep($this);
    }

    /**
     * Get the previous step in the workflow
     */
    public function getPreviousStep(): ?ApprovalStep
    {
        return $this->workflow->activeSteps()
            ->where('step_order', '<', $this->step_order)
            ->orderBy('step_order', 'desc')
            ->first();
    }
}
