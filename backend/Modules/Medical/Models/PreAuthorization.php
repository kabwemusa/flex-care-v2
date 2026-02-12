<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;

class PreAuthorization extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_preauthorizations';

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTO_APPROVED = 'auto_approved';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CLAIMED = 'claimed';

    public const PRIORITY_STANDARD = 'standard';
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_EMERGENCY = 'emergency';

    // =========================================================================
    // FILLABLE
    // =========================================================================

    protected $fillable = [
        'preauth_number',
        'provider_id',
        'policy_id',
        'member_id',
        'requested_service_date',
        'service_end_date',
        'admission_date',
        'discharge_date',
        'estimated_days',
        'primary_diagnosis',
        'primary_icd_code',
        'secondary_diagnoses',
        'treatment_plan',
        'clinical_notes',
        'facility_name',
        'facility_type',
        'attending_doctor',
        'doctor_license',
        'currency',
        'requested_amount',
        'approved_amount',
        'reserved_amount',
        'primary_benefit_id',
        'benefit_balance_before',
        'status',
        'priority',
        'requires_manual_review',
        'review_reason',
        'decision_at',
        'decision_by',
        'decision_notes',
        'rejection_reason',
        'rejection_details',
        'validity_hours',
        'expires_at',
        'is_expired',
        'extension_count',
        'last_extended_at',
        'last_extended_by',
        'fraud_score',
        'fraud_flags',
        'fraud_checked_at',
        'fraud_check_version',
        'claim_id',
        'claimed_at',
        'requested_at',
        'requested_by_type',
        'requested_by_id',
        'submission_channel',
        'has_supporting_documents',
        'metadata',
    ];

    protected $casts = [
        'requested_service_date' => 'date',
        'service_end_date' => 'date',
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'estimated_days' => 'integer',
        'secondary_diagnoses' => 'array',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'reserved_amount' => 'decimal:2',
        'benefit_balance_before' => 'decimal:2',
        'requires_manual_review' => 'boolean',
        'decision_at' => 'datetime',
        'validity_hours' => 'integer',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
        'extension_count' => 'integer',
        'last_extended_at' => 'datetime',
        'fraud_score' => 'integer',
        'fraud_flags' => 'array',
        'fraud_checked_at' => 'datetime',
        'claimed_at' => 'datetime',
        'requested_at' => 'datetime',
        'has_supporting_documents' => 'boolean',
        'metadata' => 'array',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->preauth_number)) {
                $model->preauth_number = static::generatePreauthNumber();
            }

            $model->requested_at = $model->requested_at ?? now();

            if (empty($model->expires_at) && $model->validity_hours) {
                $model->expires_at = now()->addHours($model->validity_hours);
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function primaryBenefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'primary_benefit_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PreAuthorizationLine::class, 'preauthorization_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PreAuthorizationDocument::class, 'preauthorization_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PreAuthorizationNote::class, 'preauthorization_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BenefitReservation::class, 'preauthorization_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(MedicalUser::class, 'decision_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_AUTO_APPROVED]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_AUTO_APPROVED])
            ->where('is_expired', false)
            ->whereNull('claim_id');
    }

    public function scopeExpired($query)
    {
        return $query->where('is_expired', true);
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_URGENT, self::PRIORITY_EMERGENCY]);
    }

    public function scopeExpiringSoon($query, int $hours = 24)
    {
        return $query->active()
            ->where('expires_at', '<=', now()->addHours($hours));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_AUTO_APPROVED => 'Auto Approved',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_CLAIMED => 'Claimed',
            default => ucfirst($this->status),
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_STANDARD => 'Standard',
            self::PRIORITY_URGENT => 'Urgent',
            self::PRIORITY_EMERGENCY => 'Emergency',
            default => ucfirst($this->priority),
        };
    }

    public function getIsApprovedAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_AUTO_APPROVED, self::STATUS_PARTIALLY_APPROVED]);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->is_approved && !$this->is_expired && !$this->claim_id;
    }

    public function getCanBeClaimedAttribute(): bool
    {
        return $this->is_active;
    }

    public function getRemainingHoursAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        $hours = now()->diffInHours($this->expires_at, false);
        return max(0, $hours);
    }

    public function getApprovalPercentageAttribute(): float
    {
        if ($this->requested_amount <= 0) {
            return 0;
        }

        return round(($this->approved_amount / $this->requested_amount) * 100, 2);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public static function generatePreauthNumber(): string
    {
        $prefix = 'PA';
        $year = now()->format('Y');
        $sequence = static::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    public function approve(float $approvedAmount, ?string $notes = null, ?string $approvedBy = null): bool
    {
        $this->status = $approvedAmount >= $this->requested_amount
            ? self::STATUS_APPROVED
            : self::STATUS_PARTIALLY_APPROVED;
        $this->approved_amount = $approvedAmount;
        $this->decision_at = now();
        $this->decision_by = $approvedBy;
        $this->decision_notes = $notes;

        return $this->save();
    }

    public function reject(string $reason, ?string $details = null, ?string $rejectedBy = null): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason = $reason;
        $this->rejection_details = $details;
        $this->decision_at = now();
        $this->decision_by = $rejectedBy;

        return $this->save();
    }

    public function markAsExpired(): bool
    {
        if ($this->claim_id) {
            return false; // Already claimed
        }

        $this->status = self::STATUS_EXPIRED;
        $this->is_expired = true;

        return $this->save();
    }

    public function linkToClaim(Claim $claim): bool
    {
        $this->claim_id = $claim->id;
        $this->claimed_at = now();
        $this->status = self::STATUS_CLAIMED;

        return $this->save();
    }

    public function extend(int $additionalHours, ?string $extendedBy = null): bool
    {
        if ($this->extension_count >= 3) {
            return false;
        }

        $this->expires_at = $this->expires_at->addHours($additionalHours);
        $this->validity_hours += $additionalHours;
        $this->extension_count++;
        $this->last_extended_at = now();
        $this->last_extended_by = $extendedBy;
        $this->is_expired = false;

        return $this->save();
    }

    public function getTotalLinesAmount(): float
    {
        return $this->lines()->sum('requested_amount');
    }

    public function recalculateTotals(): void
    {
        $this->requested_amount = $this->getTotalLinesAmount();
        $this->approved_amount = $this->lines()->sum('approved_amount');
        $this->save();
    }
}
