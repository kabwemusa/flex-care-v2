<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class Claim extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_claims';

    protected $fillable = [
        'claim_number',
        'policy_id',
        'member_id',
        'claim_type',
        'submission_type',
        'submission_channel',
        'service_date',
        'service_end_date',
        'admission_date',
        'discharge_date',
        'days_admitted',
        'provider_id',
        'provider_name',
        'provider_type',
        'provider_invoice_number',
        'primary_diagnosis',
        'primary_icd_code',
        'secondary_diagnoses',
        'diagnosis_notes',
        'currency',
        'claimed_amount',
        'approved_amount',
        'copay_amount',
        'deductible_amount',
        'excess_amount',
        'excluded_amount',
        'payable_amount',
        'paid_amount',
        'payment_method',
        'payment_reference',
        'payment_date',
        'paid_to',
        'bank_name',
        'account_number',
        'requires_preauth',
        'preauth_number',
        'preauth_status',
        'preauth_amount',
        'preauth_at',
        'preauth_by',
        'status',
        'substatus',
        'priority',
        'assigned_to',
        'assigned_at',
        'processed_by',
        'processed_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejection_notes',
        'rejected_by',
        'rejected_at',
        'received_at',
        'first_response_at',
        'completed_at',
        'tat_days',
        'fraud_score',
        'fraud_flags',
        'is_flagged',
        'flag_reason',
        'requires_audit',
        'audited_by',
        'audited_at',
        'metadata',
        'internal_notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'service_end_date' => 'date',
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'payment_date' => 'date',
        'days_admitted' => 'integer',
        'claimed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'copay_amount' => 'decimal:2',
        'deductible_amount' => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'excluded_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'preauth_amount' => 'decimal:2',
        'requires_preauth' => 'boolean',
        'is_flagged' => 'boolean',
        'requires_audit' => 'boolean',
        'priority' => 'integer',
        'tat_days' => 'integer',
        'fraud_score' => 'integer',
        'secondary_diagnoses' => 'array',
        'fraud_flags' => 'array',
        'metadata' => 'array',
        'preauth_at' => 'datetime',
        'assigned_at' => 'datetime',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'first_response_at' => 'datetime',
        'completed_at' => 'datetime',
        'audited_at' => 'datetime',
    ];

    protected $attributes = [
        'currency' => 'ZMW',
        'status' => MedicalConstants::CLAIM_STATUS_SUBMITTED,
        'approved_amount' => 0,
        'copay_amount' => 0,
        'deductible_amount' => 0,
        'excess_amount' => 0,
        'excluded_amount' => 0,
        'payable_amount' => 0,
        'paid_amount' => 0,
        'priority' => 5,
        'requires_preauth' => false,
        'is_flagged' => false,
        'requires_audit' => false,
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (empty($claim->claim_number)) {
                $claim->claim_number = self::generateClaimNumber();
            }
            $claim->received_at = $claim->received_at ?? now();
        });
    }

    public static function generateClaimNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_CLAIM;
        $year = date('Y');
        $random = strtoupper(Str::random(6));
        return "{$prefix}-{$year}-{$random}";
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ClaimLine::class)->orderBy('line_number');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClaimDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClaimNote::class)->orderBy('created_at', 'desc');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeSubmitted($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_SUBMITTED);
    }

    public function scopeInReview($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_IN_REVIEW);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::CLAIM_STATUS_APPROVED,
            MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED,
        ]);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_REJECTED);
    }

    public function scopePaid($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_PAID);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_STATUS_CLOSED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS,
            MedicalConstants::CLAIM_STATUS_IN_REVIEW,
            MedicalConstants::CLAIM_STATUS_PENDING_APPROVAL,
        ]);
    }

    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeForPolicy($query, string $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('claim_type', $type);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', '<=', 3);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::CLAIM_STATUSES[$this->status] ?? $this->status;
    }

    public function getClaimTypeLabelAttribute(): string
    {
        return MedicalConstants::CLAIM_TYPES[$this->claim_type] ?? $this->claim_type;
    }

    public function getSubmissionTypeLabelAttribute(): string
    {
        return MedicalConstants::SUBMISSION_TYPES[$this->submission_type] ?? $this->submission_type;
    }

    public function getProviderTypeLabelAttribute(): string
    {
        return MedicalConstants::PROVIDER_TYPES[$this->provider_type] ?? $this->provider_type;
    }

    public function getRejectionReasonLabelAttribute(): ?string
    {
        if (!$this->rejection_reason) return null;
        return MedicalConstants::CLAIM_REJECTION_REASONS[$this->rejection_reason] ?? $this->rejection_reason;
    }

    public function getNetPayableAttribute(): float
    {
        return max(0, $this->approved_amount - $this->copay_amount - $this->deductible_amount - $this->excess_amount - $this->excluded_amount);
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, $this->payable_amount - $this->paid_amount);
    }

    public function getIsInPatientAttribute(): bool
    {
        return $this->claim_type === MedicalConstants::CLAIM_TYPE_IN_PATIENT;
    }

    public function getCanBeEditedAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS,
        ]);
    }

    public function getCanBeProcessedAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_IN_REVIEW,
        ]);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::CLAIM_STATUS_IN_REVIEW,
            MedicalConstants::CLAIM_STATUS_PENDING_APPROVAL,
        ]);
    }

    public function getCanBePaidAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::CLAIM_STATUS_APPROVED,
            MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED,
        ]) && $this->outstanding_amount > 0;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculateTat(): int
    {
        if (!$this->completed_at) return 0;

        return $this->received_at->diffInDays($this->completed_at);
    }

    public function recalculateTotals(): void
    {
        // Use a fresh query to avoid the orderBy from the lines() relationship
        // which causes MariaDB error when combined with aggregate functions
        $totals = ClaimLine::where('claim_id', $this->id)
            ->selectRaw('
                SUM(claimed_amount) as total_claimed,
                SUM(approved_amount) as total_approved,
                SUM(copay_amount) as total_copay,
                SUM(deductible_amount) as total_deductible,
                SUM(excess_amount) as total_excess,
                SUM(excluded_amount) as total_excluded,
                SUM(payable_amount) as total_payable
            ')
            ->first();

        $this->update([
            'claimed_amount' => $totals->total_claimed ?? $this->claimed_amount,
            'approved_amount' => $totals->total_approved ?? 0,
            'copay_amount' => $totals->total_copay ?? 0,
            'deductible_amount' => $totals->total_deductible ?? 0,
            'excess_amount' => $totals->total_excess ?? 0,
            'excluded_amount' => $totals->total_excluded ?? 0,
            'payable_amount' => $totals->total_payable ?? 0,
        ]);
    }

    public function addNote(string $type, string $content, ?string $userId = null, array $extra = []): ClaimNote
    {
        return $this->notes()->create([
            'note_type' => $type,
            'content' => $content,
            'created_by' => $userId,
            'old_status' => $extra['old_status'] ?? null,
            'new_status' => $extra['new_status'] ?? null,
            'is_internal' => $extra['is_internal'] ?? true,
            'is_system' => $extra['is_system'] ?? false,
        ]);
    }

    public function assign(string $userId): void
    {
        $oldAssignee = $this->assigned_to;

        $this->update([
            'assigned_to' => $userId,
            'assigned_at' => now(),
        ]);

        $this->addNote(
            MedicalConstants::CLAIM_NOTE_ASSIGNMENT,
            'Claim assigned',
            $userId,
            ['old_assignee' => $oldAssignee, 'new_assignee' => $userId, 'is_system' => true]
        );
    }
}
