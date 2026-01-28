<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalHistory extends Model implements Auditable
{
    use HasFactory, HasUuids;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'approval_histories';

    protected $fillable = [
        'request_id',
        'step_id',
        'action',
        'actioned_by',
        'comments',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'actioned_at' => 'datetime',
        ];
    }

    // =========================================================================
    // ACTION CONSTANTS
    // =========================================================================

    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_RETURNED = 'returned';

    public static function getActions(): array
    {
        return [
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_RETURNED => 'Returned for Amendment',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the approval request this history belongs to
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    /**
     * Get the step this action was taken on
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }

    /**
     * Get the user who took this action
     */
    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if this was an approval
     */
    public function isApproval(): bool
    {
        return $this->action === self::ACTION_APPROVED;
    }

    /**
     * Check if this was a rejection
     */
    public function isRejection(): bool
    {
        return $this->action === self::ACTION_REJECTED;
    }

    /**
     * Check if this was a return for amendment
     */
    public function isReturn(): bool
    {
        return $this->action === self::ACTION_RETURNED;
    }

    /**
     * Get action label
     */
    public function getActionLabel(): string
    {
        return self::getActions()[$this->action] ?? $this->action;
    }
}
