<?php

namespace Modules\Medical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Medical\Constants\MedicalConstants;

class Payment extends BaseModel
{
    use SoftDeletes;

    protected $table = 'med_payments';

    protected $fillable = [
        'payment_number',
        'policy_id',
        'group_id',
        'payment_date',
        'currency',
        'amount',
        'allocated_amount',
        'unallocated_amount',
        'payment_method',
        'payment_reference',
        'bank_name',
        'cheque_number',
        'transaction_id',
        'payer_name',
        'payer_reference',
        'status',
        'confirmed_date',
        'is_reconciled',
        'reconciled_by',
        'reconciled_at',
        'reconciliation_reference',
        'notes',
        'received_by',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'confirmed_date' => 'date',
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    protected $attributes = [
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
        'status' => MedicalConstants::PAYMENT_STATUS_RECEIVED,
        'allocated_amount' => 0,
        'unallocated_amount' => 0,
        'is_reconciled' => false,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payment_number)) {
                $model->payment_number = $model->generatePaymentNumber();
            }
            if (empty($model->payment_date)) {
                $model->payment_date = now();
            }
            // Initialize unallocated to full amount
            if ($model->unallocated_amount == 0 && $model->amount > 0) {
                $model->unallocated_amount = $model->amount;
            }
        });

        static::saving(function ($model) {
            // Auto-calculate unallocated amount
            $model->unallocated_amount = max(0, $model->amount - $model->allocated_amount);
        });
    }

    protected function generatePaymentNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_PAYMENT;
        $year = date('Y');

        $lastNumber = static::where('payment_number', 'like', "{$prefix}{$year}-%")
            ->orderByRaw('CAST(RIGHT(payment_number, 6) AS INTEGER) DESC')
            ->value('payment_number');

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

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', MedicalConstants::PAYMENT_STATUS_PENDING);
    }

    public function scopeReceived($query)
    {
        return $query->where('status', MedicalConstants::PAYMENT_STATUS_RECEIVED);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', MedicalConstants::PAYMENT_STATUS_CONFIRMED);
    }

    public function scopeBounced($query)
    {
        return $query->where('status', MedicalConstants::PAYMENT_STATUS_BOUNCED);
    }

    public function scopeReversed($query)
    {
        return $query->where('status', MedicalConstants::PAYMENT_STATUS_REVERSED);
    }

    public function scopeValid($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::PAYMENT_STATUS_RECEIVED,
            MedicalConstants::PAYMENT_STATUS_CONFIRMED,
        ]);
    }

    public function scopeUnallocated($query)
    {
        return $query->where('unallocated_amount', '>', 0)
            ->valid();
    }

    public function scopeFullyAllocated($query)
    {
        return $query->where('unallocated_amount', '<=', 0);
    }

    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeForPolicy($query, string $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeForGroup($query, string $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::PAYMENT_STATUSES[$this->status] ?? $this->status;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return MedicalConstants::BILLING_PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_PENDING;
    }

    public function getIsReceivedAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_RECEIVED;
    }

    public function getIsConfirmedAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_CONFIRMED;
    }

    public function getIsBouncedAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_BOUNCED;
    }

    public function getIsReversedAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_REVERSED;
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->status === MedicalConstants::PAYMENT_STATUS_REFUNDED;
    }

    public function getIsValidAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::PAYMENT_STATUS_RECEIVED,
            MedicalConstants::PAYMENT_STATUS_CONFIRMED,
        ]);
    }

    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->unallocated_amount <= 0;
    }

    public function getHasUnallocatedAttribute(): bool
    {
        return $this->unallocated_amount > 0;
    }

    public function getCanBeAllocatedAttribute(): bool
    {
        return $this->is_valid && $this->has_unallocated;
    }

    public function getCanBeReversedAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::PAYMENT_STATUS_RECEIVED,
            MedicalConstants::PAYMENT_STATUS_CONFIRMED,
        ]);
    }

    public function getCanBeReconciledAttribute(): bool
    {
        return $this->is_valid && !$this->is_reconciled;
    }

    public function getAllocationProgressAttribute(): float
    {
        if ($this->amount <= 0) {
            return 100;
        }
        return round(($this->allocated_amount / $this->amount) * 100, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->payment_number . ' - ' . $this->currency . ' ' . number_format($this->amount, 2);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Confirm the payment.
     */
    public function confirm(?string $confirmedBy = null): bool
    {
        if ($this->status !== MedicalConstants::PAYMENT_STATUS_RECEIVED) {
            return false;
        }

        $this->status = MedicalConstants::PAYMENT_STATUS_CONFIRMED;
        $this->confirmed_date = now();

        return $this->save();
    }

    /**
     * Mark payment as bounced.
     */
    public function markBounced(?string $reason = null): bool
    {
        if (!in_array($this->status, [
            MedicalConstants::PAYMENT_STATUS_PENDING,
            MedicalConstants::PAYMENT_STATUS_RECEIVED,
        ])) {
            return false;
        }

        // Reverse all allocations first
        foreach ($this->allocations as $allocation) {
            $allocation->reverse();
        }

        $this->status = MedicalConstants::PAYMENT_STATUS_BOUNCED;
        $this->allocated_amount = 0;
        $this->unallocated_amount = $this->amount;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Bounced: " . $reason;
        }

        return $this->save();
    }

    /**
     * Reverse the payment.
     */
    public function reverse(?string $reason = null, ?string $reversedBy = null): bool
    {
        if (!$this->can_be_reversed) {
            return false;
        }

        // Reverse all allocations first
        foreach ($this->allocations as $allocation) {
            $allocation->reverse();
        }

        $this->status = MedicalConstants::PAYMENT_STATUS_REVERSED;
        $this->allocated_amount = 0;
        $this->unallocated_amount = $this->amount;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Reversed: " . $reason;
        }

        return $this->save();
    }

    /**
     * Reconcile the payment.
     */
    public function reconcile(string $reconciledBy, ?string $reference = null): bool
    {
        if (!$this->can_be_reconciled) {
            return false;
        }

        $this->is_reconciled = true;
        $this->reconciled_by = $reconciledBy;
        $this->reconciled_at = now();
        $this->reconciliation_reference = $reference;

        return $this->save();
    }

    /**
     * Allocate amount to an invoice.
     */
    public function allocateToInvoice(Invoice $invoice, float $amount, ?string $allocatedBy = null): ?PaymentAllocation
    {
        if (!$this->can_be_allocated) {
            return null;
        }

        if (!$invoice->can_receive_payment) {
            return null;
        }

        // Cap amount to available
        $amount = min($amount, $this->unallocated_amount, $invoice->balance);

        if ($amount <= 0) {
            return null;
        }

        $allocation = PaymentAllocation::create([
            'payment_id' => $this->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'allocation_date' => now(),
            'allocated_by' => $allocatedBy,
        ]);

        // Update payment allocated amounts
        $this->allocated_amount += $amount;
        $this->unallocated_amount = max(0, $this->amount - $this->allocated_amount);
        $this->save();

        // Apply payment to invoice
        $invoice->applyPayment($amount);

        return $allocation;
    }

    /**
     * Auto-allocate to oldest unpaid invoices.
     */
    public function autoAllocate(?string $allocatedBy = null): array
    {
        if (!$this->can_be_allocated) {
            return [];
        }

        $allocations = [];

        // Get outstanding invoices for the policy/group, oldest first
        $query = Invoice::outstanding()->orderBy('due_date', 'asc');

        if ($this->policy_id) {
            $query->forPolicy($this->policy_id);
        } elseif ($this->group_id) {
            $query->forGroup($this->group_id);
        } else {
            return []; // Cannot auto-allocate without policy or group
        }

        $invoices = $query->get();

        foreach ($invoices as $invoice) {
            if ($this->unallocated_amount <= 0) {
                break;
            }

            $allocation = $this->allocateToInvoice(
                $invoice,
                min($this->unallocated_amount, $invoice->balance),
                $allocatedBy
            );

            if ($allocation) {
                $allocations[] = $allocation;
            }
        }

        return $allocations;
    }

    /**
     * Get invoices that can receive this payment.
     */
    public function getEligibleInvoices()
    {
        $query = Invoice::outstanding();

        if ($this->policy_id) {
            $query->forPolicy($this->policy_id);
        } elseif ($this->group_id) {
            $query->forGroup($this->group_id);
        }

        return $query->orderBy('due_date', 'asc')->get();
    }
}
