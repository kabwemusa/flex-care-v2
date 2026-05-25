<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Invoice;
use Modules\Medical\Models\Payment;
use Modules\Medical\Models\PaymentAllocation;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Group;
use Modules\Medical\Models\Endorsement;
use Modules\Medical\Http\Resources\InvoiceResource;
use Modules\Medical\Http\Resources\InvoiceListResource;
use Modules\Medical\Http\Resources\PaymentResource;
use Modules\Medical\Http\Resources\PaymentListResource;
use Modules\Medical\Services\BillingService;
use Modules\Medical\Services\PaymentService;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BillingService $billingService,
        protected PaymentService $paymentService
    ) {}

    // =========================================================================
    // INVOICE OPERATIONS
    // =========================================================================

    /**
     * List all invoices with filtering.
     * GET /v1/medical/billing/invoices
     */
    public function invoices(): JsonResponse
    {
        try {
            $query = Invoice::query()
                ->with([
                    'policy:id,policy_number,holder_name,status',
                    'group:id,code,name',
                ]);

            // Search
            $query->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('bill_to_name', 'like', "%{$search}%")
                        ->orWhereHas('policy', function ($pq) use ($search) {
                            $pq->where('policy_number', 'like', "%{$search}%")
                                ->orWhere('holder_name', 'like', "%{$search}%");
                        });
                });
            });

            // Filters
            $query->when(request('status'), fn($q, $v) => $q->where('status', $v))
                ->when(request('invoice_type'), fn($q, $v) => $q->where('invoice_type', $v))
                ->when(request('policy_id'), fn($q, $v) => $q->where('policy_id', $v))
                ->when(request('group_id'), fn($q, $v) => $q->where('group_id', $v))
                ->when(request('outstanding'), fn($q) => $q->outstanding())
                ->when(request('overdue'), fn($q) => $q->overdue());

            // Date filters
            $query->when(request('invoice_date_from'), fn($q, $v) =>
                $q->whereDate('invoice_date', '>=', $v)
            );
            $query->when(request('invoice_date_to'), fn($q, $v) =>
                $q->whereDate('invoice_date', '<=', $v)
            );
            $query->when(request('due_date_from'), fn($q, $v) =>
                $q->whereDate('due_date', '>=', $v)
            );
            $query->when(request('due_date_to'), fn($q, $v) =>
                $q->whereDate('due_date', '<=', $v)
            );

            // Sorting
            $allowedSorts = ['invoice_date', 'due_date', 'invoice_number', 'total_amount', 'balance', 'status', 'days_overdue'];
            $sortBy = in_array(request('sort_by'), $allowedSorts) ? request('sort_by') : 'invoice_date';
            $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = min((int) request('per_page', 20), 100);
            $invoices = $query->paginate($perPage);

            return $this->paginated(
                InvoiceListResource::collection($invoices),
                'Invoices retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve invoices: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific invoice with details.
     * GET /v1/medical/billing/invoices/{id}
     */
    public function showInvoice(string $id): JsonResponse
    {
        try {
            $invoice = Invoice::with([
                'policy:id,policy_number,holder_name,holder_email,status,inception_date,expiry_date',
                'group:id,code,name,billing_email,billing_address',
                'endorsement:id,endorsement_number,endorsement_type,effective_date',
                'items.member:id,member_number,first_name,last_name,member_type',
                'allocations.payment:id,payment_number,payment_date,amount,status',
            ])->findOrFail($id);

            return $this->success(
                new InvoiceResource($invoice),
                'Invoice retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Invoice not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get invoices for a policy.
     * GET /v1/medical/policies/{policyId}/invoices
     */
    public function invoicesForPolicy(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);

            $query = $this->billingService->getInvoicesForPolicy($policy, request()->all())
                ->with(['endorsement:id,endorsement_number,endorsement_type']);

            $invoices = $query->paginate(request('per_page', 20));

            return $this->paginated(
                InvoiceListResource::collection($invoices),
                'Policy invoices retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve invoices: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get invoices for a group.
     * GET /v1/medical/groups/{groupId}/invoices
     */
    public function invoicesForGroup(string $groupId): JsonResponse
    {
        try {
            $group = Group::findOrFail($groupId);

            $query = $this->billingService->getInvoicesForGroup($group, request()->all())
                ->with(['policy:id,policy_number,holder_name']);

            $invoices = $query->paginate(request('per_page', 20));

            return $this->paginated(
                InvoiceListResource::collection($invoices),
                'Group invoices retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve invoices: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate first invoice for a policy.
     * POST /v1/medical/policies/{policyId}/generate-invoice
     */
    public function generateInvoice(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);
            $createdBy = auth()->id() ?? request('created_by');

            $invoice = DB::transaction(function () use ($policy, $createdBy) {
                return $this->billingService->generateFirstInvoice($policy, $createdBy);
            });

            return $this->success(
                new InvoiceResource($invoice),
                'Invoice generated',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to generate invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate recurring invoice for a policy.
     * POST /v1/medical/policies/{policyId}/generate-recurring-invoice
     */
    public function generateRecurringInvoice(string $policyId): JsonResponse
    {
        try {
            request()->validate([
                'period_start' => 'required|date',
            ]);

            $policy = Policy::findOrFail($policyId);
            $createdBy = auth()->id() ?? request('created_by');
            $periodStart = \Carbon\Carbon::parse(request('period_start'));

            $invoice = DB::transaction(function () use ($policy, $periodStart, $createdBy) {
                return $this->billingService->generateRecurringInvoice($policy, $periodStart, $createdBy);
            });

            return $this->success(
                new InvoiceResource($invoice),
                'Recurring invoice generated',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to generate invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate invoice for an endorsement.
     * POST /v1/medical/endorsements/{endorsementId}/generate-invoice
     */
    public function generateEndorsementInvoice(string $endorsementId): JsonResponse
    {
        try {
            $endorsement = Endorsement::findOrFail($endorsementId);
            $createdBy = auth()->id() ?? request('created_by');

            $invoice = DB::transaction(function () use ($endorsement, $createdBy) {
                return $this->billingService->generateEndorsementInvoice($endorsement, $createdBy);
            });

            return $this->success(
                new InvoiceResource($invoice),
                'Endorsement invoice generated',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Endorsement not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to generate invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send an invoice.
     * POST /v1/medical/billing/invoices/{id}/send
     */
    public function sendInvoice(string $id): JsonResponse
    {
        try {
            request()->validate([
                'via' => 'required|string|in:email,post,portal,sms',
            ]);

            $invoice = Invoice::findOrFail($id);
            $sentBy = auth()->id() ?? request('sent_by');

            $success = $this->billingService->sendInvoice(
                $invoice,
                request('via'),
                $sentBy
            );

            if (!$success) {
                return $this->error('Failed to send invoice', 422);
            }

            return $this->success(
                new InvoiceResource($invoice->fresh()),
                'Invoice sent'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Invoice not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to send invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel an invoice.
     * POST /v1/medical/billing/invoices/{id}/cancel
     */
    public function cancelInvoice(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string|max:500',
            ]);

            $invoice = Invoice::findOrFail($id);
            $cancelledBy = auth()->id() ?? request('cancelled_by');

            $success = DB::transaction(function () use ($invoice, $cancelledBy) {
                return $this->billingService->cancelInvoice(
                    $invoice,
                    request('reason'),
                    $cancelledBy
                );
            });

            if (!$success) {
                return $this->error('Failed to cancel invoice', 422);
            }

            return $this->success(
                new InvoiceResource($invoice->fresh()),
                'Invoice cancelled'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Invoice not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to cancel invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Write off an invoice.
     * POST /v1/medical/billing/invoices/{id}/write-off
     */
    public function writeOffInvoice(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string|max:500',
            ]);

            $invoice = Invoice::findOrFail($id);
            $approvedBy = auth()->id() ?? request('approved_by');

            $success = $this->billingService->writeOffInvoice(
                $invoice,
                request('reason'),
                $approvedBy
            );

            if (!$success) {
                return $this->error('Failed to write off invoice', 422);
            }

            return $this->success(
                new InvoiceResource($invoice->fresh()),
                'Invoice written off'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Invoice not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to write off invoice: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // PAYMENT OPERATIONS
    // =========================================================================

    /**
     * List all payments with filtering.
     * GET /v1/medical/billing/payments
     */
    public function payments(): JsonResponse
    {
        try {
            $query = Payment::query()
                ->with([
                    'policy:id,policy_number,holder_name',
                    'group:id,code,name',
                    'receivedByUser:id,username,email',
                ]);

            // Search
            $query->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('payer_name', 'like', "%{$search}%")
                        ->orWhere('payment_reference', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            });

            // Filters
            $query->when(request('status'), fn($q, $v) => $q->where('status', $v))
                ->when(request('payment_method'), fn($q, $v) => $q->where('payment_method', $v))
                ->when(request('policy_id'), fn($q, $v) => $q->where('policy_id', $v))
                ->when(request('group_id'), fn($q, $v) => $q->where('group_id', $v))
                ->when(request('unallocated'), fn($q) => $q->unallocated())
                ->when(request('unreconciled'), fn($q) => $q->unreconciled());

            // Date filters
            $query->when(request('payment_date_from'), fn($q, $v) =>
                $q->whereDate('payment_date', '>=', $v)
            );
            $query->when(request('payment_date_to'), fn($q, $v) =>
                $q->whereDate('payment_date', '<=', $v)
            );

            // Sorting
            $allowedSorts = ['payment_date', 'payment_number', 'amount', 'status'];
            $sortBy = in_array(request('sort_by'), $allowedSorts) ? request('sort_by') : 'payment_date';
            $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = min((int) request('per_page', 20), 100);
            $payments = $query->paginate($perPage);

            return $this->paginated(
                PaymentListResource::collection($payments),
                'Payments retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve payments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific payment with details.
     * GET /v1/medical/billing/payments/{id}
     */
    public function showPayment(string $id): JsonResponse
    {
        try {
            $payment = Payment::with([
                'policy:id,policy_number,holder_name,status',
                'group:id,code,name',
                'allocations.invoice:id,invoice_number,total_amount,balance,status',
                'receivedByUser:id,username,email',
                'reconciledByUser:id,username,email',
            ])->findOrFail($id);

            return $this->success(
                new PaymentResource($payment),
                'Payment retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payments for a policy.
     * GET /v1/medical/policies/{policyId}/payments
     */
    public function paymentsForPolicy(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);

            $query = $this->paymentService->getPaymentsForPolicy($policy, request()->all())
                ->with([
                    'allocations.invoice:id,invoice_number,total_amount',
                    'receivedByUser:id,username,email',
                ]);

            $payments = $query->paginate(request('per_page', 20));

            return $this->paginated(
                PaymentListResource::collection($payments),
                'Policy payments retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve payments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record a new payment.
     * POST /v1/medical/billing/payments
     */
    public function recordPayment(): JsonResponse
    {
        try {
            request()->validate([
                'policy_id' => 'nullable|uuid|exists:med_policies,id|required_without:group_id',
                'group_id' => 'nullable|uuid|exists:med_corporate_groups,id|required_without:policy_id',
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string',
                'payment_date' => 'nullable|date',
                'payment_reference' => 'nullable|string|max:100',
                'bank_name' => 'nullable|string|max:100',
                'cheque_number' => 'nullable|string|max:30',
                'transaction_id' => 'nullable|string|max:100',
                'payer_name' => 'nullable|string|max:255',
                'payer_reference' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:1000',
                'auto_allocate' => 'nullable|boolean',
                'invoice_id' => 'nullable|uuid|exists:med_invoices,id',
            ]);

            $receivedBy = auth()->id() ?? request('received_by');

            $payment = DB::transaction(function () use ($receivedBy) {
                return $this->paymentService->recordPayment(request()->all(), $receivedBy);
            });

            return $this->success(
                new PaymentResource($payment),
                'Payment recorded',
                201
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record payment directly for an invoice.
     * POST /v1/medical/billing/invoices/{invoiceId}/payments
     */
    public function recordPaymentForInvoice(string $invoiceId): JsonResponse
    {
        try {
            request()->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string',
                'payment_date' => 'nullable|date',
                'payment_reference' => 'nullable|string|max:100',
                'bank_name' => 'nullable|string|max:100',
                'cheque_number' => 'nullable|string|max:30',
                'transaction_id' => 'nullable|string|max:100',
                'payer_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);

            $invoice = Invoice::findOrFail($invoiceId);
            $receivedBy = auth()->id() ?? request('received_by');

            $payment = DB::transaction(function () use ($invoice, $receivedBy) {
                return $this->paymentService->recordPaymentForInvoice(
                    $invoice,
                    request()->all(),
                    $receivedBy
                );
            });

            return $this->success(
                new PaymentResource($payment),
                'Payment recorded and allocated',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Invoice not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Allocate payment to an invoice.
     * POST /v1/medical/billing/payments/{paymentId}/allocate
     */
    public function allocatePayment(string $paymentId): JsonResponse
    {
        try {
            request()->validate([
                'invoice_id' => 'required|uuid|exists:med_invoices,id',
                'amount' => 'required|numeric|min:0.01',
            ]);

            $payment = Payment::findOrFail($paymentId);
            $invoice = Invoice::findOrFail(request('invoice_id'));
            $allocatedBy = auth()->id() ?? request('allocated_by');

            $allocation = DB::transaction(function () use ($payment, $invoice, $allocatedBy) {
                return $this->paymentService->allocatePayment(
                    $payment,
                    $invoice,
                    request('amount'),
                    $allocatedBy
                );
            });

            return $this->success([
                'allocation' => $allocation,
                'payment' => new PaymentResource($payment->fresh(['allocations.invoice'])),
            ], 'Payment allocated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment or Invoice not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to allocate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Auto-allocate a payment to outstanding invoices.
     * POST /v1/medical/billing/payments/{paymentId}/auto-allocate
     */
    public function autoAllocatePayment(string $paymentId): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $allocatedBy = auth()->id() ?? request('allocated_by');

            $allocations = DB::transaction(function () use ($payment, $allocatedBy) {
                return $this->paymentService->autoAllocatePayment($payment, $allocatedBy);
            });

            return $this->success([
                'allocations_count' => count($allocations),
                'payment' => new PaymentResource($payment->fresh(['allocations.invoice'])),
            ], 'Payment auto-allocated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to auto-allocate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reverse an allocation.
     * DELETE /v1/medical/billing/allocations/{allocationId}
     */
    public function reverseAllocation(string $allocationId): JsonResponse
    {
        try {
            $allocation = PaymentAllocation::findOrFail($allocationId);

            $success = DB::transaction(function () use ($allocation) {
                return $this->paymentService->reverseAllocation($allocation);
            });

            if (!$success) {
                return $this->error('Failed to reverse allocation', 422);
            }

            return $this->success(null, 'Allocation reversed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Allocation not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to reverse allocation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm a payment.
     * POST /v1/medical/billing/payments/{id}/confirm
     */
    public function confirmPayment(string $id): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($id);
            $confirmedBy = auth()->id() ?? request('confirmed_by');

            $success = $this->paymentService->confirmPayment($payment, $confirmedBy);

            if (!$success) {
                return $this->error('Failed to confirm payment', 422);
            }

            return $this->success(
                new PaymentResource($payment->fresh()),
                'Payment confirmed'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to confirm payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark payment as bounced.
     * POST /v1/medical/billing/payments/{id}/bounce
     */
    public function bouncePayment(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string|max:500',
            ]);

            $payment = Payment::findOrFail($id);

            $success = DB::transaction(function () use ($payment) {
                return $this->paymentService->markPaymentBounced($payment, request('reason'));
            });

            if (!$success) {
                return $this->error('Failed to mark payment as bounced', 422);
            }

            return $this->success(
                new PaymentResource($payment->fresh()),
                'Payment marked as bounced'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to bounce payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reverse a payment.
     * POST /v1/medical/billing/payments/{id}/reverse
     */
    public function reversePayment(string $id): JsonResponse
    {
        try {
            request()->validate([
                'reason' => 'required|string|max:500',
            ]);

            $payment = Payment::findOrFail($id);
            $reversedBy = auth()->id() ?? request('reversed_by');

            $success = DB::transaction(function () use ($payment, $reversedBy) {
                return $this->paymentService->reversePayment(
                    $payment,
                    request('reason'),
                    $reversedBy
                );
            });

            if (!$success) {
                return $this->error('Failed to reverse payment', 422);
            }

            return $this->success(
                new PaymentResource($payment->fresh()),
                'Payment reversed'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to reverse payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reconcile a payment.
     * POST /v1/medical/billing/payments/{id}/reconcile
     */
    public function reconcilePayment(string $id): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($id);
            $reconciledBy = auth()->id() ?? request('reconciled_by');

            $success = $this->paymentService->reconcilePayment(
                $payment,
                $reconciledBy,
                request('reference')
            );

            if (!$success) {
                return $this->error('Failed to reconcile payment', 422);
            }

            return $this->success(
                new PaymentResource($payment->fresh()),
                'Payment reconciled'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Payment not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to reconcile payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate a credit note for a policy.
     * POST /v1/medical/policies/{policyId}/credit-note
     */
    public function generateCreditNote(string $policyId): JsonResponse
    {
        try {
            request()->validate([
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'required|string|max:500',
                'endorsement_id' => 'nullable|uuid|exists:med_endorsements,id',
            ]);

            $policy = Policy::findOrFail($policyId);
            $endorsement = null;

            if (request('endorsement_id')) {
                $endorsement = Endorsement::findOrFail(request('endorsement_id'));
            }

            $createdBy = auth()->id() ?? request('created_by');

            $invoice = DB::transaction(function () use ($policy, $endorsement, $createdBy) {
                return $this->billingService->generateCreditNote(
                    $policy,
                    request('amount'),
                    request('reason'),
                    $endorsement,
                    $createdBy
                );
            });

            return $this->success(
                new InvoiceResource($invoice),
                'Credit note generated',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->error('Failed to generate credit note: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // STATISTICS & REPORTS
    // =========================================================================

    /**
     * Get billing statistics.
     * GET /v1/medical/billing/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $invoiceStats = $this->billingService->getStatistics(request()->all());
            $paymentStats = $this->paymentService->getStatistics(request()->all());

            return $this->success([
                'invoices' => $invoiceStats,
                'payments' => $paymentStats,
            ], 'Billing statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get policy billing summary.
     * GET /v1/medical/policies/{policyId}/billing-summary
     */
    public function policyBillingSummary(string $policyId): JsonResponse
    {
        try {
            $policy = Policy::findOrFail($policyId);

            $standing = $this->billingService->isPolicyInGoodStanding($policy);

            return $this->success([
                'policy_id' => $policy->id,
                'policy_number' => $policy->policy_number,
                'outstanding_balance' => $policy->outstanding_balance,
                'overdue_amount' => $policy->overdue_amount,
                'total_invoiced' => $policy->total_invoiced,
                'total_paid' => $policy->total_paid,
                'standing' => $standing,
            ], 'Policy billing summary retrieved');
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve billing summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get invoices requiring action.
     * GET /v1/medical/billing/action-required
     */
    public function actionRequired(): JsonResponse
    {
        try {
            $data = $this->billingService->getInvoicesRequiringAction();

            return $this->success([
                'overdue' => InvoiceListResource::collection($data['overdue']),
                'due_soon' => InvoiceListResource::collection($data['due_soon']),
            ], 'Invoices requiring action retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get unallocated payments.
     * GET /v1/medical/billing/unallocated-payments
     */
    public function unallocatedPayments(): JsonResponse
    {
        try {
            $payments = $this->paymentService->getUnallocatedPayments(request()->all())
                ->with([
                    'policy:id,policy_number,holder_name',
                    'group:id,code,name',
                    'receivedByUser:id,username,email',
                ])
                ->paginate(request('per_page', 20));

            return $this->paginated(
                PaymentListResource::collection($payments),
                'Unallocated payments retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve payments: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // REFERENCE DATA
    // =========================================================================

    /**
     * Get invoice types.
     * GET /v1/medical/billing/invoice-types
     */
    public function invoiceTypes(): JsonResponse
    {
        return $this->success(MedicalConstants::INVOICE_TYPES, 'Invoice types retrieved');
    }

    /**
     * Get invoice statuses.
     * GET /v1/medical/billing/invoice-statuses
     */
    public function invoiceStatuses(): JsonResponse
    {
        return $this->success(MedicalConstants::INVOICE_STATUSES, 'Invoice statuses retrieved');
    }

    /**
     * Get payment methods.
     * GET /v1/medical/billing/payment-methods
     */
    public function paymentMethods(): JsonResponse
    {
        return $this->success(MedicalConstants::BILLING_PAYMENT_METHODS, 'Payment methods retrieved');
    }

    /**
     * Get payment statuses.
     * GET /v1/medical/billing/payment-statuses
     */
    public function paymentStatuses(): JsonResponse
    {
        return $this->success(MedicalConstants::PAYMENT_STATUSES, 'Payment statuses retrieved');
    }
}
