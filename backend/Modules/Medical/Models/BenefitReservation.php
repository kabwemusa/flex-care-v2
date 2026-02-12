<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitReservation extends Model
{
    use HasUuids;

    protected $table = 'med_benefit_reservations';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'member_benefit_utilization_id',
        'preauthorization_id',
        'preauthorization_line_id',
        'reserved_amount',
        'reserved_count',
        'reserved_days',
        'reserved_at',
        'expires_at',
        'released_at',
        'claimed_at',
        'status',
        'release_reason',
        'released_by',
        'notes',
        'claim_id',
        'claim_line_id',
    ];

    protected $casts = [
        'reserved_amount' => 'decimal:2',
        'reserved_count' => 'integer',
        'reserved_days' => 'integer',
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function utilization(): BelongsTo
    {
        return $this->belongsTo(MemberBenefitUtilization::class, 'member_benefit_utilization_id');
    }

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauthorization_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeForPreAuth($query, string $preauthId)
    {
        return $query->where('preauthorization_id', $preauthId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function release(string $reason, ?string $releasedBy = null): bool
    {
        $this->status = self::STATUS_RELEASED;
        $this->released_at = now();
        $this->release_reason = $reason;
        $this->released_by = $releasedBy;

        // Update utilization to reduce reserved amounts
        if ($this->utilization) {
            $this->utilization->decrement('reserved_amount', $this->reserved_amount);
            $this->utilization->decrement('reserved_count', $this->reserved_count);
            $this->utilization->decrement('reserved_days', $this->reserved_days);
            $this->utilization->decrement('active_reservations_count');
        }

        return $this->save();
    }

    public function markAsClaimed(Claim $claim, ?string $claimLineId = null): bool
    {
        $this->status = self::STATUS_CLAIMED;
        $this->claimed_at = now();
        $this->claim_id = $claim->id;
        $this->claim_line_id = $claimLineId;

        // Move reserved to used in utilization
        if ($this->utilization) {
            $this->utilization->decrement('reserved_amount', $this->reserved_amount);
            $this->utilization->decrement('reserved_count', $this->reserved_count);
            $this->utilization->decrement('reserved_days', $this->reserved_days);
            $this->utilization->decrement('active_reservations_count');

            $this->utilization->increment('used_amount', $this->reserved_amount);
            $this->utilization->increment('used_count', $this->reserved_count);
            $this->utilization->increment('used_days', $this->reserved_days);
        }

        return $this->save();
    }

    public function markAsExpired(): bool
    {
        $this->status = self::STATUS_EXPIRED;
        $this->release_reason = 'expired';

        // Release the reserved amounts
        if ($this->utilization) {
            $this->utilization->decrement('reserved_amount', $this->reserved_amount);
            $this->utilization->decrement('reserved_count', $this->reserved_count);
            $this->utilization->decrement('reserved_days', $this->reserved_days);
            $this->utilization->decrement('active_reservations_count');
        }

        return $this->save();
    }
}
