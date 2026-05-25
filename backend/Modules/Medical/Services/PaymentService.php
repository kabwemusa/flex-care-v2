<?php

namespace Modules\Medical\Services;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Modules\Medical\Models\Payment;
use Modules\Medical\Models\PaymentAllocation;
use Modules\Medical\Models\Invoice;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Group;
use Modules\Medical\Constants\MedicalConstants;

class PaymentService
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    // =========================================================================
    // PAYMENT RECORDING
    // =========================================================================

    /**
     * Record a new payment.
     */
    public function recordPayment(array $data, ?string $receivedBy = null): Payment
    {
        return DB::transaction(function () use ($data, $receivedBy) {
            // Validate policy or group exists
            $policy = null;
            $group = null;

            if (!empty($data['policy_id'])) {
                $policy = Policy::findOrFail($data['policy_id']);
            }

            if (!empty($data['group_id'])) {
                $group = Group::findOrFail($data['group_id']);
            }

            if (!$policy && !$group) {
                throw new Exception('Payment must be linked to a policy or group');
            }

            $payment = Payment::create([
                'policy_id' => $policy?->id,
                'group_id' => $group?->id ?? $policy?->group_id,
                'payment_date' => $data['payment_date'] ?? now(),
                'currency' => $data['currency'] ?? MedicalConstants::DEFAULT_CURRENCY,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'payer_name' => $data['payer_name'] ?? $this->getPayerName($policy, $group),
                'payer_reference' => $data['payer_reference'] ?? null,
                'status' => MedicalConstants::PAYMENT_STATUS_RECEIVED,
                'notes' => $data['notes'] ?? null,
                'received_by' => $receivedBy,
                'created_by' => $receivedBy,
            ]);

            // Auto-allocate if requested
            if (!empty($data['auto_allocate']) && $data['auto_allocate']) {
                $payment->autoAllocate($receivedBy);
            }

            // Manual allocation to specific invoice
            if (!empty($data['invoice_id'])) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                $allocateAmount = $data['allocate_amount'] ?? min($payment->amount, $invoice->balance);
                $payment->allocateToInvoice($invoice, $allocateAmount, $receivedBy);
            }

            return $payment->fresh(['allocations.invoice', 'policy', 'group']);
        });
    }

    /**
     * Record payment and allocate to a specific invoice.
     */
    public function recordPaymentForInvoice(
        Invoice $invoice,
        array $data,
        ?string $receivedBy = null
    ): Payment {
        $data['policy_id'] = $invoice->policy_id;
        $data['group_id'] = $invoice->group_id;
        $data['invoice_id'] = $invoice->id;
        $data['allocate_amount'] = min($data['amount'], $invoice->balance);

        return $this->recordPayment($data, $receivedBy);
    }

    // =========================================================================
    // PAYMENT ALLOCATION
    // =========================================================================

    /**
     * Manually allocate payment to an invoice.
     */
    public function allocatePayment(
        Payment $payment,
        Invoice $invoice,
        float $amount,
        ?string $allocatedBy = null
    ): PaymentAllocation {
        if (!$payment->can_be_allocated) {
            throw new Exception('Payment cannot be allocated');
        }

        if (!$invoice->can_receive_payment) {
            throw new Exception('Invoice cannot receive payment');
        }

        if ($amount > $payment->unallocated_amount) {
            throw new Exception('Allocation amount exceeds unallocated payment amount');
        }

        if ($amount > $invoice->balance) {
            throw new Exception('Allocation amount exceeds invoice balance');
        }

        $allocation = $payment->allocateToInvoice($invoice, $amount, $allocatedBy);

        if (!$allocation) {
            throw new Exception('Failed to create allocation');
        }

        return $allocation;
    }

    /**
     * Auto-allocate a payment to outstanding invoices (FIFO).
     */
    public function autoAllocatePayment(Payment $payment, ?string $allocatedBy = null): array
    {
        return $payment->autoAllocate($allocatedBy);
    }

    /**
     * Reverse a specific allocation.
     */
    public function reverseAllocation(PaymentAllocation $allocation): bool
    {
        return $allocation->reverse();
    }

    /**
     * Reallocate payment from one invoice to another.
     */
    public function reallocatePayment(
        PaymentAllocation $allocation,
        Invoice $newInvoice,
        ?float $amount = null,
        ?string $allocatedBy = null
    ): PaymentAllocation {
        $amount = $amount ?? $allocation->amount;
        $payment = $allocation->payment;

        return DB::transaction(function () use ($allocation, $newInvoice, $amount, $payment, $allocatedBy) {
            // Reverse original allocation
            $allocation->reverse();

            // Create new allocation
            $newAllocation = $payment->allocateToInvoice($newInvoice, $amount, $allocatedBy);

            if (!$newAllocation) {
                throw new Exception('Failed to create new allocation');
            }

            return $newAllocation;
        });
    }

    // =========================================================================
    // PAYMENT STATUS MANAGEMENT
    // =========================================================================

    /**
     * Confirm a payment.
     */
    public function confirmPayment(Payment $payment, ?string $confirmedBy = null): bool
    {
        return $payment->confirm($confirmedBy);
    }

    /**
     * Mark payment as bounced.
     */
    public function markPaymentBounced(Payment $payment, ?string $reason = null): bool
    {
        return $payment->markBounced($reason);
    }

    /**
     * Reverse a payment.
     */
    public function reversePayment(Payment $payment, string $reason, ?string $reversedBy = null): bool
    {
        return $payment->reverse($reason, $reversedBy);
    }

    /**
     * Reconcile a payment.
     */
    public function reconcilePayment(
        Payment $payment,
        string $reconciledBy,
        ?string $reference = null
    ): bool {
        return $payment->reconcile($reconciledBy, $reference);
    }

    /**
     * Bulk reconcile payments.
     */
    public function bulkReconcile(array $paymentIds, string $reconciledBy, ?string $reference = null): int
    {
        $count = 0;

        foreach ($paymentIds as $paymentId) {
            $payment = Payment::find($paymentId);
            if ($payment && $payment->can_be_reconciled) {
                $payment->reconcile($reconciledBy, $reference);
                $count++;
            }
        }

        return $count;
    }

    // =========================================================================
    // PAYMENT QUERIES
    // =========================================================================

    /**
     * Get payments for a policy.
     */
    public function getPaymentsForPolicy(Policy $policy, array $filters = []): Builder
    {
        $query = Payment::forPolicy($policy->id);

        $this->applyPaymentFilters($query, $filters);

        return $query->orderBy('payment_date', 'desc');
    }

    /**
     * Get payments for a group.
     */
    public function getPaymentsForGroup(Group $group, array $filters = []): Builder
    {
        $query = Payment::forGroup($group->id);

        $this->applyPaymentFilters($query, $filters);

        return $query->orderBy('payment_date', 'desc');
    }

    /**
     * Get unallocated payments.
     */
    public function getUnallocatedPayments(array $filters = []): Builder
    {
        $query = Payment::unallocated();

        if (!empty($filters['policy_id'])) {
            $query->forPolicy($filters['policy_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->forGroup($filters['group_id']);
        }

        return $query->orderBy('payment_date', 'asc');
    }

    /**
     * Get payments pending reconciliation.
     */
    public function getPaymentsPendingReconciliation(array $filters = []): Builder
    {
        $query = Payment::unreconciled()->valid();

        if (!empty($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('payment_date', 'asc');
    }

    /**
     * Apply filters to payment query.
     */
    protected function applyPaymentFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->byMethod($filters['payment_method']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        if (isset($filters['reconciled'])) {
            if ($filters['reconciled']) {
                $query->reconciled();
            } else {
                $query->unreconciled();
            }
        }

        if (isset($filters['allocated'])) {
            if ($filters['allocated']) {
                $query->fullyAllocated();
            } else {
                $query->unallocated();
            }
        }
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get payment statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Payment::query();

        if (!empty($filters['policy_id'])) {
            $query->forPolicy($filters['policy_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->forGroup($filters['group_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        $stats = $query->selectRaw("
            COUNT(*) as total_payments,
            SUM(amount) as total_received,
            SUM(allocated_amount) as total_allocated,
            SUM(unallocated_amount) as total_unallocated,
            COUNT(CASE WHEN status = ? THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as received_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as confirmed_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as bounced_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as reversed_count,
            COUNT(CASE WHEN is_reconciled = true THEN 1 END) as reconciled_count,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as bounced_amount
        ", [
            MedicalConstants::PAYMENT_STATUS_PENDING,
            MedicalConstants::PAYMENT_STATUS_RECEIVED,
            MedicalConstants::PAYMENT_STATUS_CONFIRMED,
            MedicalConstants::PAYMENT_STATUS_BOUNCED,
            MedicalConstants::PAYMENT_STATUS_REVERSED,
            MedicalConstants::PAYMENT_STATUS_BOUNCED,
        ])->first();

        // Payments by method
        $byMethod = Payment::valid()
            ->when(!empty($filters['policy_id']), fn($q) => $q->forPolicy($filters['policy_id']))
            ->when(!empty($filters['group_id']), fn($q) => $q->forGroup($filters['group_id']))
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        return [
            'summary' => $stats->toArray(),
            'by_method' => $byMethod,
            'allocation_rate' => $stats->total_received > 0
                ? round(($stats->total_allocated / $stats->total_received) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get collection summary by period.
     */
    public function getCollectionSummary(
        Carbon $startDate,
        Carbon $endDate,
        string $groupBy = 'month'
    ): array {
        // PostgreSQL TO_CHAR format equivalents
        $format = match ($groupBy) {
            'day'   => 'YYYY-MM-DD',
            'week'  => 'IYYY-IW',
            'month' => 'YYYY-MM',
            'year'  => 'YYYY',
            default => 'YYYY-MM',
        };

        return Payment::valid()
            ->dateRange($startDate, $endDate)
            ->selectRaw("
                TO_CHAR(payment_date, '{$format}') as period,
                COUNT(*) as payment_count,
                SUM(amount) as total_amount,
                SUM(allocated_amount) as allocated_amount
            ")
            ->groupByRaw("TO_CHAR(payment_date, '{$format}')")
            ->orderBy('period', 'asc')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get payer name from policy or group.
     */
    protected function getPayerName(?Policy $policy, ?Group $group): string
    {
        if ($group) {
            return $group->name;
        }

        if ($policy) {
            return $policy->holder_name ?? 'Policy Holder';
        }

        return 'Unknown';
    }

    /**
     * Find duplicate payments.
     */
    public function findPotentialDuplicates(Payment $payment): Builder
    {
        return Payment::where('id', '!=', $payment->id)
            ->where('amount', $payment->amount)
            ->where('payment_date', $payment->payment_date)
            ->where(function ($query) use ($payment) {
                $query->where('policy_id', $payment->policy_id)
                    ->orWhere('group_id', $payment->group_id);
            })
            ->valid();
    }

    /**
     * Match bank statement entry to existing payment.
     */
    public function matchPayment(
        string $reference,
        float $amount,
        Carbon $date,
        ?string $policyId = null,
        ?string $groupId = null
    ): ?Payment {
        $query = Payment::valid()
            ->where('amount', $amount)
            ->whereBetween('payment_date', [$date->copy()->subDays(3), $date->copy()->addDays(3)]);

        if ($policyId) {
            $query->forPolicy($policyId);
        }

        if ($groupId) {
            $query->forGroup($groupId);
        }

        // Try to match by reference
        $byRef = (clone $query)->where(function ($q) use ($reference) {
            $q->where('payment_reference', $reference)
                ->orWhere('transaction_id', $reference)
                ->orWhere('cheque_number', $reference);
        })->first();

        if ($byRef) {
            return $byRef;
        }

        // Return first unreconciled match
        return $query->unreconciled()->first();
    }
}
