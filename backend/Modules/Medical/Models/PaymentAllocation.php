<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends BaseModel
{
    protected $table = 'med_payment_allocations';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount',
        'allocation_date',
        'allocated_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
    ];

    protected $attributes = [
        'amount' => 0,
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->allocation_date)) {
                $model->allocation_date = now();
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForPayment($query, string $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    public function scopeForInvoice($query, string $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Reverse this allocation.
     */
    public function reverse(): bool
    {
        // Reverse payment on invoice
        if ($this->invoice) {
            $this->invoice->reversePayment($this->amount);
        }

        // Update payment allocated amount
        if ($this->payment) {
            $this->payment->allocated_amount = max(0, $this->payment->allocated_amount - $this->amount);
            $this->payment->unallocated_amount = max(0, $this->payment->amount - $this->payment->allocated_amount);
            $this->payment->save();
        }

        // Delete the allocation
        return $this->delete();
    }

    /**
     * Get the display summary.
     */
    public function getDisplaySummaryAttribute(): string
    {
        $paymentNum = $this->payment?->payment_number ?? 'Unknown';
        $invoiceNum = $this->invoice?->invoice_number ?? 'Unknown';

        return "{$paymentNum} → {$invoiceNum}: " . number_format($this->amount, 2);
    }
}
