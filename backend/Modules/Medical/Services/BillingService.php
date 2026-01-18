<?php

namespace Modules\Medical\Services;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Modules\Medical\Models\Invoice;
use Modules\Medical\Models\InvoiceItem;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Endorsement;
use Modules\Medical\Models\Group;
use Modules\Medical\Constants\MedicalConstants;

class BillingService
{
    // =========================================================================
    // INVOICE GENERATION
    // =========================================================================

    /**
     * Generate first invoice for a newly activated policy.
     */
    public function generateFirstInvoice(Policy $policy, ?string $createdBy = null): Invoice
    {
        return DB::transaction(function () use ($policy, $createdBy) {
            // Calculate billing period
            $billingPeriod = $this->calculateBillingPeriod(
                $policy->inception_date,
                $policy->billing_frequency,
                $policy->expiry_date
            );

            // Calculate due date based on group payment terms or default
            $dueDate = $this->calculateDueDate($policy);

            // Create invoice
            $invoice = Invoice::create([
                'policy_id' => $policy->id,
                'group_id' => $policy->group_id,
                'invoice_type' => MedicalConstants::INVOICE_TYPE_PREMIUM,
                'billing_period_start' => $billingPeriod['start'],
                'billing_period_end' => $billingPeriod['end'],
                'invoice_date' => now(),
                'due_date' => $dueDate,
                'currency' => $policy->currency,
                'bill_to_name' => $this->getBillToName($policy),
                'bill_to_email' => $this->getBillToEmail($policy),
                'bill_to_address' => $this->getBillToAddress($policy),
                'created_by' => $createdBy,
            ]);

            // Generate line items
            $this->generatePolicyInvoiceItems($invoice, $policy);

            // Recalculate totals
            $invoice->recalculateTotals();

            return $invoice->fresh(['items', 'policy']);
        });
    }

    /**
     * Generate recurring invoice for a policy.
     */
    public function generateRecurringInvoice(
        Policy $policy,
        Carbon $periodStart,
        ?string $createdBy = null
    ): Invoice {
        return DB::transaction(function () use ($policy, $periodStart, $createdBy) {
            // Check for existing invoice in this period
            $existing = Invoice::forPolicy($policy->id)
                ->ofType(MedicalConstants::INVOICE_TYPE_PREMIUM)
                ->where('billing_period_start', $periodStart->format('Y-m-d'))
                ->first();

            if ($existing) {
                throw new Exception('Invoice already exists for this billing period');
            }

            // Calculate billing period
            $billingPeriod = $this->calculateBillingPeriod(
                $periodStart,
                $policy->billing_frequency,
                $policy->expiry_date
            );

            $dueDate = $this->calculateDueDate($policy, $periodStart);

            $invoice = Invoice::create([
                'policy_id' => $policy->id,
                'group_id' => $policy->group_id,
                'invoice_type' => MedicalConstants::INVOICE_TYPE_PREMIUM,
                'billing_period_start' => $billingPeriod['start'],
                'billing_period_end' => $billingPeriod['end'],
                'invoice_date' => now(),
                'due_date' => $dueDate,
                'currency' => $policy->currency,
                'bill_to_name' => $this->getBillToName($policy),
                'bill_to_email' => $this->getBillToEmail($policy),
                'bill_to_address' => $this->getBillToAddress($policy),
                'created_by' => $createdBy,
            ]);

            $this->generatePolicyInvoiceItems($invoice, $policy);
            $invoice->recalculateTotals();

            return $invoice->fresh(['items', 'policy']);
        });
    }

    /**
     * Generate invoice for an endorsement.
     */
    public function generateEndorsementInvoice(
        Endorsement $endorsement,
        ?string $createdBy = null
    ): Invoice {
        if (!$endorsement->generates_invoice || $endorsement->premium_adjustment <= 0) {
            throw new Exception('Endorsement does not generate an invoice');
        }

        return DB::transaction(function () use ($endorsement, $createdBy) {
            $policy = $endorsement->policy;

            // Calculate pro-rata period
            $periodStart = $endorsement->effective_date;
            $periodEnd = $policy->expiry_date;

            $invoice = Invoice::create([
                'policy_id' => $policy->id,
                'group_id' => $policy->group_id,
                'endorsement_id' => $endorsement->id,
                'invoice_type' => MedicalConstants::INVOICE_TYPE_ENDORSEMENT,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'invoice_date' => now(),
                'due_date' => $this->calculateDueDate($policy),
                'currency' => $policy->currency,
                'bill_to_name' => $this->getBillToName($policy),
                'bill_to_email' => $this->getBillToEmail($policy),
                'created_by' => $createdBy,
            ]);

            // Create line item for endorsement
            $description = $this->getEndorsementDescription($endorsement);

            $invoice->items()->create([
                'line_number' => 1,
                'item_type' => MedicalConstants::INVOICE_ITEM_PRORATA,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $endorsement->prorated_amount > 0
                    ? $endorsement->prorated_amount
                    : $endorsement->premium_adjustment,
                'amount' => $endorsement->prorated_amount > 0
                    ? $endorsement->prorated_amount
                    : $endorsement->premium_adjustment,
                'reference_type' => 'endorsement',
                'reference_id' => $endorsement->id,
            ]);

            // Apply tax if configured
            $taxRate = config('medical.tax_rate', 0);
            if ($taxRate > 0) {
                $taxAmount = round($endorsement->premium_adjustment * $taxRate, 2);
                $invoice->items()->create([
                    'line_number' => 2,
                    'item_type' => MedicalConstants::INVOICE_ITEM_TAX,
                    'description' => 'Tax (' . ($taxRate * 100) . '%)',
                    'quantity' => 1,
                    'unit_price' => $taxAmount,
                    'amount' => $taxAmount,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $invoice->recalculateTotals();

            // Link invoice back to endorsement
            $endorsement->update(['invoice_id' => $invoice->id]);

            return $invoice->fresh(['items', 'endorsement']);
        });
    }

    /**
     * Generate credit note for refund.
     */
    public function generateCreditNote(
        Policy $policy,
        float $amount,
        string $reason,
        ?Endorsement $endorsement = null,
        ?string $createdBy = null
    ): Invoice {
        return DB::transaction(function () use ($policy, $amount, $reason, $endorsement, $createdBy) {
            $invoice = Invoice::create([
                'policy_id' => $policy->id,
                'group_id' => $policy->group_id,
                'endorsement_id' => $endorsement?->id,
                'invoice_type' => MedicalConstants::INVOICE_TYPE_CREDIT_NOTE,
                'invoice_date' => now(),
                'due_date' => now(), // Credit notes are immediately due
                'currency' => $policy->currency,
                'bill_to_name' => $this->getBillToName($policy),
                'bill_to_email' => $this->getBillToEmail($policy),
                'notes' => $reason,
                'created_by' => $createdBy,
            ]);

            $invoice->items()->create([
                'line_number' => 1,
                'item_type' => MedicalConstants::INVOICE_ITEM_ADJUSTMENT,
                'description' => 'Credit: ' . $reason,
                'quantity' => 1,
                'unit_price' => -abs($amount),
                'amount' => -abs($amount),
            ]);

            $invoice->recalculateTotals();

            return $invoice->fresh(['items']);
        });
    }

    // =========================================================================
    // LINE ITEM GENERATION
    // =========================================================================

    /**
     * Generate invoice items from policy premiums.
     */
    protected function generatePolicyInvoiceItems(Invoice $invoice, Policy $policy): void
    {
        $lineNumber = 0;
        $billingMultiplier = $this->getBillingMultiplier($policy->billing_frequency);

        // Option 1: Summarized billing (one line for all premiums)
        if (config('medical.billing.detailed_items', true) === false) {
            $lineNumber++;
            $periodPremium = round($policy->gross_premium * $billingMultiplier, 2);

            $invoice->items()->create([
                'line_number' => $lineNumber,
                'item_type' => MedicalConstants::INVOICE_ITEM_BASE_PREMIUM,
                'description' => $this->getPeriodDescription($policy->billing_frequency) . ' Premium',
                'quantity' => 1,
                'unit_price' => $periodPremium,
                'amount' => $periodPremium,
            ]);
            return;
        }

        // Option 2: Detailed billing (member-level breakdown)
        $activeMembers = $policy->activeMembers()->with('memberLoadings')->get();

        foreach ($activeMembers as $member) {
            $lineNumber++;
            $memberPremium = round($member->premium * $billingMultiplier, 2);

            $invoice->items()->create([
                'line_number' => $lineNumber,
                'member_id' => $member->id,
                'item_type' => MedicalConstants::INVOICE_ITEM_MEMBER_PREMIUM,
                'description' => $member->full_name . ' - ' . ucfirst($member->member_type) . ' Premium',
                'quantity' => 1,
                'unit_price' => $memberPremium,
                'amount' => $memberPremium,
                'reference_type' => 'member',
                'reference_id' => $member->id,
            ]);

            // Add member loadings
            $memberLoadings = round($member->loading_amount * $billingMultiplier, 2);
            if ($memberLoadings > 0) {
                $lineNumber++;
                $invoice->items()->create([
                    'line_number' => $lineNumber,
                    'member_id' => $member->id,
                    'item_type' => MedicalConstants::INVOICE_ITEM_LOADING,
                    'description' => $member->full_name . ' - Underwriting Loading',
                    'quantity' => 1,
                    'unit_price' => $memberLoadings,
                    'amount' => $memberLoadings,
                    'reference_type' => 'member',
                    'reference_id' => $member->id,
                ]);
            }
        }

        // Add addon premiums
        $activeAddons = $policy->activeAddons()->with('addon')->get();
        foreach ($activeAddons as $policyAddon) {
            $addonPremium = round($policyAddon->premium * $billingMultiplier, 2);
            if ($addonPremium > 0) {
                $lineNumber++;
                $invoice->items()->create([
                    'line_number' => $lineNumber,
                    'item_type' => MedicalConstants::INVOICE_ITEM_ADDON_PREMIUM,
                    'description' => ($policyAddon->addon?->name ?? 'Add-on') . ' Premium',
                    'quantity' => 1,
                    'unit_price' => $addonPremium,
                    'amount' => $addonPremium,
                    'reference_type' => 'policy_addon',
                    'reference_id' => $policyAddon->id,
                ]);
            }
        }

        // Apply discounts
        $discount = round($policy->discount_amount * $billingMultiplier, 2);
        if ($discount > 0) {
            $lineNumber++;
            $invoice->items()->create([
                'line_number' => $lineNumber,
                'item_type' => MedicalConstants::INVOICE_ITEM_DISCOUNT,
                'description' => 'Discount',
                'quantity' => 1,
                'unit_price' => $discount,
                'amount' => -$discount, // Negative for discount
            ]);
        }

        // Apply tax
        $tax = round($policy->tax_amount * $billingMultiplier, 2);
        if ($tax > 0) {
            $lineNumber++;
            $taxRate = config('medical.tax_rate', 0) * 100;
            $invoice->items()->create([
                'line_number' => $lineNumber,
                'item_type' => MedicalConstants::INVOICE_ITEM_TAX,
                'description' => "Tax ({$taxRate}%)",
                'quantity' => 1,
                'unit_price' => $tax,
                'amount' => $tax,
                'tax_amount' => $tax,
            ]);
        }
    }

    // =========================================================================
    // INVOICE MANAGEMENT
    // =========================================================================

    /**
     * Send an invoice.
     */
    public function sendInvoice(Invoice $invoice, string $via, ?string $sentBy = null): bool
    {
        if (!$invoice->can_be_sent) {
            throw new Exception('Invoice cannot be sent in current state');
        }

        // Here you would integrate with email/notification service
        // For now, we just update the status

        return $invoice->send($via, $sentBy);
    }

    /**
     * Cancel an invoice.
     */
    public function cancelInvoice(Invoice $invoice, string $reason, ?string $cancelledBy = null): bool
    {
        if (!$invoice->can_be_cancelled) {
            throw new Exception('Invoice cannot be cancelled');
        }

        // Reverse any allocations
        foreach ($invoice->allocations as $allocation) {
            $allocation->reverse();
        }

        return $invoice->cancel($reason);
    }

    /**
     * Write off an invoice.
     */
    public function writeOffInvoice(Invoice $invoice, string $reason, ?string $approvedBy = null): bool
    {
        return $invoice->writeOff($reason);
    }

    /**
     * Update overdue status for all unpaid invoices.
     */
    public function updateOverdueInvoices(): int
    {
        $count = 0;

        Invoice::unpaid()
            ->where('due_date', '<', now())
            ->whereIn('status', [
                MedicalConstants::INVOICE_STATUS_SENT,
                MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID,
            ])
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $invoice->updateOverdueDays();
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Get invoices requiring action (overdue, approaching due date).
     */
    public function getInvoicesRequiringAction(): array
    {
        $overdue = Invoice::overdue()
            ->with(['policy', 'group'])
            ->orderBy('days_overdue', 'desc')
            ->get();

        $dueSoon = Invoice::sent()
            ->where('due_date', '<=', now()->addDays(7))
            ->where('due_date', '>=', now())
            ->with(['policy', 'group'])
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
        ];
    }

    // =========================================================================
    // BILLING QUERIES
    // =========================================================================

    /**
     * Get invoices for a policy.
     */
    public function getInvoicesForPolicy(Policy $policy, array $filters = []): Builder
    {
        $query = Invoice::forPolicy($policy->id);

        $this->applyInvoiceFilters($query, $filters);

        return $query->orderBy('invoice_date', 'desc');
    }

    /**
     * Get invoices for a group.
     */
    public function getInvoicesForGroup(Group $group, array $filters = []): Builder
    {
        $query = Invoice::forGroup($group->id);

        $this->applyInvoiceFilters($query, $filters);

        return $query->orderBy('invoice_date', 'desc');
    }

    /**
     * Apply filters to invoice query.
     */
    protected function applyInvoiceFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }

        if (isset($filters['outstanding']) && $filters['outstanding']) {
            $query->outstanding();
        }
    }

    /**
     * Get outstanding balance for a policy.
     */
    public function getOutstandingBalance(Policy $policy): float
    {
        return Invoice::forPolicy($policy->id)
            ->outstanding()
            ->sum('balance');
    }

    /**
     * Get outstanding balance for a group.
     */
    public function getGroupOutstandingBalance(Group $group): float
    {
        return Invoice::forGroup($group->id)
            ->outstanding()
            ->sum('balance');
    }

    /**
     * Check if policy is in good standing (payment-wise).
     */
    public function isPolicyInGoodStanding(Policy $policy): array
    {
        $overdueInvoices = Invoice::forPolicy($policy->id)
            ->overdue()
            ->get();

        $maxOverdueDays = $overdueInvoices->max('days_overdue') ?? 0;
        $totalOverdue = $overdueInvoices->sum('balance');

        return [
            'in_good_standing' => $maxOverdueDays < MedicalConstants::OVERDUE_GRACE_PERIOD,
            'should_suspend' => $maxOverdueDays >= MedicalConstants::OVERDUE_SUSPENSION_THRESHOLD,
            'should_lapse' => $maxOverdueDays >= MedicalConstants::OVERDUE_LAPSE_THRESHOLD,
            'max_overdue_days' => $maxOverdueDays,
            'total_overdue_amount' => $totalOverdue,
            'overdue_invoices_count' => $overdueInvoices->count(),
        ];
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get billing statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Invoice::query();

        if (!empty($filters['policy_id'])) {
            $query->forPolicy($filters['policy_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->forGroup($filters['group_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }

        $stats = $query->selectRaw("
            COUNT(*) as total_invoices,
            SUM(total_amount) as total_invoiced,
            SUM(paid_amount) as total_collected,
            SUM(balance) as total_outstanding,
            COUNT(CASE WHEN status = ? THEN 1 END) as draft_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as sent_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as partially_paid_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as overdue_count,
            SUM(CASE WHEN status = ? THEN balance ELSE 0 END) as overdue_amount
        ", [
            MedicalConstants::INVOICE_STATUS_DRAFT,
            MedicalConstants::INVOICE_STATUS_SENT,
            MedicalConstants::INVOICE_STATUS_PARTIALLY_PAID,
            MedicalConstants::INVOICE_STATUS_PAID,
            MedicalConstants::INVOICE_STATUS_OVERDUE,
            MedicalConstants::INVOICE_STATUS_OVERDUE,
        ])->first();

        $collectionRate = $stats->total_invoiced > 0
            ? round(($stats->total_collected / $stats->total_invoiced) * 100, 2)
            : 0;

        return [
            'summary' => $stats->toArray(),
            'collection_rate' => $collectionRate,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Calculate billing period based on frequency.
     */
    protected function calculateBillingPeriod(Carbon $startDate, string $frequency, Carbon $maxEndDate): array
    {
        $endDate = match ($frequency) {
            MedicalConstants::BILLING_MONTHLY => $startDate->copy()->addMonth()->subDay(),
            MedicalConstants::BILLING_QUARTERLY => $startDate->copy()->addMonths(3)->subDay(),
            MedicalConstants::BILLING_SEMI_ANNUAL => $startDate->copy()->addMonths(6)->subDay(),
            MedicalConstants::BILLING_ANNUAL => $startDate->copy()->addYear()->subDay(),
            default => $startDate->copy()->addMonth()->subDay(),
        };

        // Don't exceed policy end date
        if ($endDate->gt($maxEndDate)) {
            $endDate = $maxEndDate->copy();
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Calculate due date for invoice.
     */
    protected function calculateDueDate(Policy $policy, ?Carbon $baseDate = null): Carbon
    {
        $baseDate = $baseDate ?? now();

        // Get payment terms from group or default to 30 days
        $paymentTerms = MedicalConstants::PAYMENT_TERMS_30_DAYS;

        if ($policy->group) {
            $paymentTerms = $policy->group->payment_terms ?? $paymentTerms;
        }

        $days = MedicalConstants::PAYMENT_TERMS_DAYS[$paymentTerms] ?? 30;

        return $baseDate->copy()->addDays($days);
    }

    /**
     * Get billing multiplier for frequency.
     */
    protected function getBillingMultiplier(string $frequency): float
    {
        return match ($frequency) {
            MedicalConstants::BILLING_MONTHLY => 1 / 12,
            MedicalConstants::BILLING_QUARTERLY => 1 / 4,
            MedicalConstants::BILLING_SEMI_ANNUAL => 1 / 2,
            MedicalConstants::BILLING_ANNUAL => 1,
            default => 1 / 12,
        };
    }

    /**
     * Get period description.
     */
    protected function getPeriodDescription(string $frequency): string
    {
        return match ($frequency) {
            MedicalConstants::BILLING_MONTHLY => 'Monthly',
            MedicalConstants::BILLING_QUARTERLY => 'Quarterly',
            MedicalConstants::BILLING_SEMI_ANNUAL => 'Semi-Annual',
            MedicalConstants::BILLING_ANNUAL => 'Annual',
            default => 'Monthly',
        };
    }

    /**
     * Get bill to name.
     */
    protected function getBillToName(Policy $policy): string
    {
        if ($policy->is_corporate && $policy->group) {
            return $policy->group->name;
        }

        return $policy->holder_name ?? 'Policy Holder';
    }

    /**
     * Get bill to email.
     */
    protected function getBillToEmail(Policy $policy): ?string
    {
        if ($policy->is_corporate && $policy->group) {
            return $policy->group->billing_email ?? $policy->group->contact_email;
        }

        return $policy->holder_email;
    }

    /**
     * Get bill to address.
     */
    protected function getBillToAddress(Policy $policy): ?string
    {
        if ($policy->is_corporate && $policy->group) {
            return $policy->group->address;
        }

        return null;
    }

    /**
     * Get endorsement description for invoice.
     */
    protected function getEndorsementDescription(Endorsement $endorsement): string
    {
        $typeLabel = MedicalConstants::ENDORSEMENT_TYPES[$endorsement->endorsement_type]
            ?? $endorsement->endorsement_type;

        $period = '';
        if ($endorsement->effective_date && $endorsement->policy?->expiry_date) {
            $period = ' (' . $endorsement->effective_date->format('d M Y')
                . ' - ' . $endorsement->policy->expiry_date->format('d M Y') . ')';
        }

        return $typeLabel . ' - Pro-rata Premium' . $period;
    }
}
