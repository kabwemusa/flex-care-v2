<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail for benefit utilization changes.
 */
class BenefitUtilizationHistory extends Model
{
    use HasUuids;

    protected $table = 'med_benefit_utilization_history';

    protected $fillable = [
        'utilization_id',
        'claim_id',
        'claim_line_id',
        'transaction_type',
        'amount_change',
        'count_change',
        'days_change',
        'balance_amount',
        'balance_count',
        'balance_days',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_change' => 'decimal:2',
        'count_change' => 'integer',
        'days_change' => 'integer',
        'balance_amount' => 'decimal:2',
        'balance_count' => 'integer',
        'balance_days' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function utilization(): BelongsTo
    {
        return $this->belongsTo(MemberBenefitUtilization::class, 'utilization_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function claimLine(): BelongsTo
    {
        return $this->belongsTo(ClaimLine::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForClaim($query, string $claimId)
    {
        return $query->where('claim_id', $claimId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getTransactionTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'claim_approved' => 'Claim Approved',
            'claim_reversed' => 'Claim Reversed',
            'manual_adjustment' => 'Manual Adjustment',
            'period_reset' => 'Period Reset',
            default => $this->transaction_type,
        };
    }
}
