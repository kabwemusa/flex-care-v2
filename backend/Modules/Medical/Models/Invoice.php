<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Medical\Constants\MedicalConstants;

class Invoice extends BaseModel
{
    use SoftDeletes;

    protected $table = 'med_invoices';

    protected $fillable = [
        'invoice_number',
        'policy_id',
        'group_id',
        'endorsement_id',
        'invoice_type',
        'billing_period_start',
        'billing_period_end',
        'invoice_date',
        'due_date',
        'paid_date',
        'currency',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'days_overdue',
        'bill_to_name',
        'bill_to_email',
        'bill_to_address',
        'sent_at',
        'sent_via',
        'reminder_count',
        'last_reminder_at',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'days_overdue' => 'integer',
        'reminder_count' => 'integer',
        'sent_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
        'status' => MedicalConstants::INVOICE_STATUS_DRAFT,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'balance' => 0,
        'days_overdue' => 0,
        'reminder_count' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = $model->generateInvoiceNumber();
            }
            if (empty($model->invoice_date)) {
                $model->invoice_date = now();
            }
        });

        static::saving(function ($model) {
            // Auto-calculate balance
            $model->balance = max(0, $model->total_amount - $model->paid_amount);

            // Auto-update status based on payment
            if ($model->isDirty('paid_amount') && !$model->isDirty('status')) {
                $model->updatePaymentStatus();
            }
        });
    }

    protected function generateInvoiceNumber(): string
    {
        $prefix = $this->invoice_type === MedicalConstants::INVOICE_TYPE_CREDIT_NOTE
            ? MedicalConstants::PREFIX_CREDIT_NOTE
            : MedicalConstants::PREFIX_INVOICE;
        $year = date('Y');

        $lastNumber = static::where('invoice_number', 'like', "{$prefix}{$year}-%")
            ->orderByRaw('CAST(RIGHT(invoice_number, 6) AS INTEGER) DESC')
            ->value('invoice_number');

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function endorsement(): BelongsTo
    {
        return $this->belongsTo(Endorsement::class, 'endorsement_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')->orderBy('line_number');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDraft($query)
    {
        return $query->where('status', MedicalConstants::INVOICE_STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', MedicalConstants::INVOICE_STATUS_SENT);
    }

    public function scopePaid($query)
    {
        return $query->where('status', MedicalConstants::INVOICE_STATUS_PAID);
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', MedicalConstants::INVOICE_STATUS_OVERDUE);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::INVOICE_STATUS_DRAFT,
            MedicalConstants::INVOICE_STATUS_SENT,
            MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID,
            MedicalConstants::INVOICE_STATUS_OVERDUE,
        ]);
    }

    public function scopeOutstanding($query)
    {
        return $query->where('balance', '>', 0)
            ->whereNotIn('status', [
                MedicalConstants::INVOICE_STATUS_CANCELLED,
                MedicalConstants::INVOICE_STATUS_WRITTEN_OFF,
            ]);
    }

    public function scopeForPolicy($query, string $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeForGroup($query, string $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('invoice_type', $type);
    }

    public function scopeDueBefore($query, $date)
    {
        return $query->where('due_date', '<=', $date);
    }

    public function scopeDueAfter($query, $date)
    {
        return $query->where('due_date', '>=', $date);
    }

    public function scopeBillingPeriod($query, $startDate, $endDate)
    {
        return $query->where('billing_period_start', '>=', $startDate)
            ->where('billing_period_end', '<=', $endDate);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::INVOICE_STATUSES[$this->status] ?? $this->status;
    }

    public function getInvoiceTypeLabelAttribute(): string
    {
        return MedicalConstants::INVOICE_TYPES[$this->invoice_type] ?? $this->invoice_type;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_DRAFT;
    }

    public function getIsSentAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_SENT;
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_PAID;
    }

    public function getIsPartiallyPaidAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_OVERDUE;
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_CANCELLED;
    }

    public function getIsWrittenOffAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_WRITTEN_OFF;
    }

    public function getIsCreditNoteAttribute(): bool
    {
        return $this->invoice_type === MedicalConstants::INVOICE_TYPE_CREDIT_NOTE;
    }

    public function getCanBeSentAttribute(): bool
    {
        return $this->status === MedicalConstants::INVOICE_STATUS_DRAFT
            && $this->total_amount > 0;
    }

    public function getCanReceivePaymentAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::INVOICE_STATUS_SENT,
            MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID,
            MedicalConstants::INVOICE_STATUS_OVERDUE,
        ]) && $this->balance > 0;
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::INVOICE_STATUS_DRAFT,
            MedicalConstants::INVOICE_STATUS_SENT,
        ]) && $this->paid_amount == 0;
    }

    public function getPaymentProgressAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 100;
        }
        return round(($this->paid_amount / $this->total_amount) * 100, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->invoice_number . ' - ' . $this->invoice_type_label;
    }

    public function getBillingPeriodAttribute(): ?string
    {
        if ($this->billing_period_start && $this->billing_period_end) {
            return $this->billing_period_start->format('d M Y') . ' - ' . $this->billing_period_end->format('d M Y');
        }
        return null;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update payment status based on paid amount.
     */
    protected function updatePaymentStatus(): void
    {
        if ($this->paid_amount <= 0) {
            return; // Don't change status if no payment
        }

        if ($this->paid_amount >= $this->total_amount) {
            $this->status = MedicalConstants::INVOICE_STATUS_PAID;
            $this->paid_date = now();
            $this->days_overdue = 0;
        } elseif ($this->paid_amount > 0) {
            $this->status = MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID;
        }
    }

    /**
     * Calculate and update overdue days.
     */
    public function updateOverdueDays(): void
    {
        if ($this->is_paid || $this->is_cancelled || $this->is_written_off) {
            $this->days_overdue = 0;
            $this->save();
            return;
        }

        if ($this->due_date && $this->due_date->isPast()) {
            $this->days_overdue = $this->due_date->diffInDays(now());

            // Update status to overdue if sent and past due
            if ($this->status === MedicalConstants::INVOICE_STATUS_SENT
                || $this->status === MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID) {
                $this->status = MedicalConstants::INVOICE_STATUS_OVERDUE;
            }

            $this->save();
        }
    }

    /**
     * Send the invoice.
     */
    public function send(string $via, ?string $sentBy = null): bool
    {
        if (!$this->can_be_sent) {
            return false;
        }

        $this->status = MedicalConstants::INVOICE_STATUS_SENT;
        $this->sent_at = now();
        $this->sent_via = $via;

        return $this->save();
    }

    /**
     * Record a reminder was sent.
     */
    public function recordReminder(): void
    {
        $this->increment('reminder_count');
        $this->update(['last_reminder_at' => now()]);
    }

    /**
     * Cancel the invoice.
     */
    public function cancel(?string $reason = null): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->status = MedicalConstants::INVOICE_STATUS_CANCELLED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Cancelled: " . $reason;
        }

        return $this->save();
    }

    /**
     * Write off the invoice.
     */
    public function writeOff(?string $reason = null): bool
    {
        if ($this->is_paid || $this->is_cancelled || $this->is_written_off) {
            return false;
        }

        $this->status = MedicalConstants::INVOICE_STATUS_WRITTEN_OFF;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Written off: " . $reason;
        }

        return $this->save();
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = $items->where('item_type', '!=', MedicalConstants::INVOICE_ITEM_TAX)
            ->where('item_type', '!=', MedicalConstants::INVOICE_ITEM_DISCOUNT)
            ->sum('total');

        $discount = $items->where('item_type', MedicalConstants::INVOICE_ITEM_DISCOUNT)
            ->sum(fn($item) => abs($item->total));

        $tax = $items->where('item_type', MedicalConstants::INVOICE_ITEM_TAX)
            ->sum('total');

        $this->subtotal = $subtotal;
        $this->discount_amount = $discount;
        $this->tax_amount = $tax;
        $this->total_amount = $subtotal - $discount + $tax;
        $this->balance = max(0, $this->total_amount - $this->paid_amount);

        $this->save();
    }

    /**
     * Apply payment to this invoice.
     */
    public function applyPayment(float $amount): float
    {
        $amountToApply = min($amount, $this->balance);

        $this->paid_amount += $amountToApply;
        $this->balance = max(0, $this->total_amount - $this->paid_amount);

        $this->updatePaymentStatus();
        $this->save();

        return $amountToApply;
    }

    /**
     * Reverse a payment amount.
     */
    public function reversePayment(float $amount): void
    {
        $this->paid_amount = max(0, $this->paid_amount - $amount);
        $this->balance = max(0, $this->total_amount - $this->paid_amount);

        // Reset status if needed
        if ($this->paid_amount == 0) {
            $this->status = $this->due_date && $this->due_date->isPast()
                ? MedicalConstants::INVOICE_STATUS_OVERDUE
                : MedicalConstants::INVOICE_STATUS_SENT;
            $this->paid_date = null;
        } elseif ($this->paid_amount < $this->total_amount) {
            $this->status = $this->due_date && $this->due_date->isPast()
                ? MedicalConstants::INVOICE_STATUS_OVERDUE
                : MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID;
        }

        $this->save();
    }

    /**
     * Get total allocated from payments.
     */
    public function getTotalAllocatedAttribute(): float
    {
        return $this->allocations()->sum('amount');
    }
}
