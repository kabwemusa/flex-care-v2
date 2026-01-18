<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class ClaimLine extends Model
{
    use HasUuids;

    protected $table = 'med_claim_lines';

    protected $fillable = [
        'claim_id',
        'benefit_id',
        'line_number',
        'service_code',
        'service_description',
        'service_date',
        'quantity',
        'unit',
        'unit_price',
        'claimed_amount',
        'approved_amount',
        'copay_amount',
        'deductible_amount',
        'excess_amount',
        'excluded_amount',
        'payable_amount',
        'status',
        'rejection_reason',
        'adjudication_notes',
        'benefit_limit',
        'benefit_used_before',
        'benefit_remaining',
        'tariff_amount',
        'tariff_code',
    ];

    protected $casts = [
        'service_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'claimed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'copay_amount' => 'decimal:2',
        'deductible_amount' => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'excluded_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'benefit_limit' => 'decimal:2',
        'benefit_used_before' => 'decimal:2',
        'benefit_remaining' => 'decimal:2',
        'tariff_amount' => 'decimal:2',
        'line_number' => 'integer',
    ];

    protected $attributes = [
        'line_number' => 1,
        'quantity' => 1,
        'unit_price' => 0,
        'approved_amount' => 0,
        'copay_amount' => 0,
        'deductible_amount' => 0,
        'excess_amount' => 0,
        'excluded_amount' => 0,
        'payable_amount' => 0,
        'status' => MedicalConstants::CLAIM_LINE_STATUS_PENDING,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::CLAIM_LINE_STATUS_APPROVED,
            MedicalConstants::CLAIM_LINE_STATUS_PARTIALLY_APPROVED,
        ]);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_LINE_STATUS_REJECTED);
    }

    public function scopePending($query)
    {
        return $query->where('status', MedicalConstants::CLAIM_LINE_STATUS_PENDING);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::CLAIM_LINE_STATUSES[$this->status] ?? $this->status;
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

    // =========================================================================
    // METHODS
    // =========================================================================

    public function approve(float $amount, ?string $notes = null): void
    {
        $this->update([
            'approved_amount' => $amount,
            'payable_amount' => $this->net_payable,
            'status' => $amount >= $this->claimed_amount
                ? MedicalConstants::CLAIM_LINE_STATUS_APPROVED
                : MedicalConstants::CLAIM_LINE_STATUS_PARTIALLY_APPROVED,
            'adjudication_notes' => $notes,
        ]);

        $this->claim->recalculateTotals();
    }

    public function reject(string $reason, ?string $notes = null): void
    {
        $this->update([
            'approved_amount' => 0,
            'payable_amount' => 0,
            'status' => MedicalConstants::CLAIM_LINE_STATUS_REJECTED,
            'rejection_reason' => $reason,
            'adjudication_notes' => $notes,
        ]);

        $this->claim->recalculateTotals();
    }
}
