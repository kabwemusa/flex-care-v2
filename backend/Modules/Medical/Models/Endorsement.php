<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Medical\Constants\MedicalConstants;

class Endorsement extends BaseModel
{
    use SoftDeletes;

    protected $table = 'med_endorsements';

    protected $fillable = [
        'endorsement_number',
        'policy_id',
        'endorsement_type',
        'description',
        'effective_date',
        'request_date',
        'premium_adjustment',
        'prorated_amount',
        'generates_invoice',
        'generates_refund',
        'invoice_id',
        'changes',
        'before_snapshot',
        'after_snapshot',
        'member_id',
        'addon_id',
        'new_plan_id',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'rejection_reason',
        'notes',
        'supporting_documents',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'request_date' => 'date',
        'premium_adjustment' => 'decimal:2',
        'prorated_amount' => 'decimal:2',
        'generates_invoice' => 'boolean',
        'generates_refund' => 'boolean',
        'changes' => 'array',
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'supporting_documents' => 'array',
    ];

    protected $attributes = [
        'status' => MedicalConstants::ENDORSEMENT_STATUS_PENDING,
        'premium_adjustment' => 0,
        'prorated_amount' => 0,
        'generates_invoice' => false,
        'generates_refund' => false,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->endorsement_number)) {
                $model->endorsement_number = $model->generateEndorsementNumber();
            }
            if (empty($model->request_date)) {
                $model->request_date = now();
            }
        });
    }

    protected function generateEndorsementNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_ENDORSEMENT;
        $year = date('Y');

        $lastNumber = static::where('endorsement_number', 'like', "{$prefix}{$year}-%")
            ->orderByRaw('CAST(SUBSTRING(endorsement_number, -6) AS UNSIGNED) DESC')
            ->value('endorsement_number');

        if ($lastNumber) {
            $sequence = (int) substr($lastNumber, -6) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s%s-%06d', $prefix, $year, $sequence);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'new_plan_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', MedicalConstants::ENDORSEMENT_STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', MedicalConstants::ENDORSEMENT_STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', MedicalConstants::ENDORSEMENT_STATUS_REJECTED);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', MedicalConstants::ENDORSEMENT_STATUS_PROCESSED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', MedicalConstants::ENDORSEMENT_STATUS_CANCELLED);
    }

    public function scopeForPolicy($query, string $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('endorsement_type', $type);
    }

    public function scopeEffectiveAfter($query, $date)
    {
        return $query->where('effective_date', '>=', $date);
    }

    public function scopeEffectiveBefore($query, $date)
    {
        return $query->where('effective_date', '<=', $date);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::ENDORSEMENT_STATUSES[$this->status] ?? $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return MedicalConstants::ENDORSEMENT_TYPES[$this->endorsement_type] ?? $this->endorsement_type;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_PENDING;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_APPROVED;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_REJECTED;
    }

    public function getIsProcessedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_PROCESSED;
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_CANCELLED;
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_PENDING;
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_PENDING;
    }

    public function getCanBeProcessedAttribute(): bool
    {
        return $this->status === MedicalConstants::ENDORSEMENT_STATUS_APPROVED;
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::ENDORSEMENT_STATUS_PENDING,
            MedicalConstants::ENDORSEMENT_STATUS_APPROVED,
        ]);
    }

    public function getHasFinancialImpactAttribute(): bool
    {
        return abs($this->premium_adjustment) > 0 || abs($this->prorated_amount) > 0;
    }

    public function getIsAdditionAttribute(): bool
    {
        return in_array($this->endorsement_type, [
            MedicalConstants::ENDORSEMENT_TYPE_ADD_MEMBER,
            MedicalConstants::ENDORSEMENT_TYPE_ADD_ADDON,
        ]);
    }

    public function getIsRemovalAttribute(): bool
    {
        return in_array($this->endorsement_type, [
            MedicalConstants::ENDORSEMENT_TYPE_REMOVE_MEMBER,
            MedicalConstants::ENDORSEMENT_TYPE_REMOVE_ADDON,
        ]);
    }

    public function getIsPlanChangeAttribute(): bool
    {
        return in_array($this->endorsement_type, [
            MedicalConstants::ENDORSEMENT_TYPE_UPGRADE_PLAN,
            MedicalConstants::ENDORSEMENT_TYPE_DOWNGRADE_PLAN,
        ]);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->endorsement_number . ' - ' . $this->type_label;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Approve the endorsement.
     */
    public function approve(string $approvedBy): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->status = MedicalConstants::ENDORSEMENT_STATUS_APPROVED;
        $this->approved_by = $approvedBy;
        $this->approved_at = now();

        return $this->save();
    }

    /**
     * Reject the endorsement.
     */
    public function reject(string $rejectedBy, string $reason): bool
    {
        if (!$this->can_be_rejected) {
            return false;
        }

        $this->status = MedicalConstants::ENDORSEMENT_STATUS_REJECTED;
        $this->approved_by = $rejectedBy; // Store who rejected
        $this->approved_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Mark as processed.
     */
    public function markAsProcessed(string $processedBy): bool
    {
        if (!$this->can_be_processed) {
            return false;
        }

        $this->status = MedicalConstants::ENDORSEMENT_STATUS_PROCESSED;
        $this->processed_by = $processedBy;
        $this->processed_at = now();

        return $this->save();
    }

    /**
     * Cancel the endorsement.
     */
    public function cancel(string $cancelledBy, ?string $reason = null): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->status = MedicalConstants::ENDORSEMENT_STATUS_CANCELLED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Cancelled: " . $reason;
        }

        return $this->save();
    }

    /**
     * Store the before snapshot.
     */
    public function captureBeforeSnapshot(array $data): void
    {
        $this->before_snapshot = $data;
        $this->save();
    }

    /**
     * Store the after snapshot.
     */
    public function captureAfterSnapshot(array $data): void
    {
        $this->after_snapshot = $data;
        $this->save();
    }

    /**
     * Record what changed.
     */
    public function recordChanges(array $changes): void
    {
        $this->changes = $changes;
        $this->save();
    }

    /**
     * Set financial impact.
     */
    public function setFinancialImpact(float $premiumAdjustment, float $proratedAmount): void
    {
        $this->premium_adjustment = $premiumAdjustment;
        $this->prorated_amount = $proratedAmount;
        $this->generates_invoice = $premiumAdjustment > 0;
        $this->generates_refund = $premiumAdjustment < 0;
        $this->save();
    }
}
