// libs/medical/data/src/lib/stores/billing.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  Invoice,
  InvoiceFilters,
  Payment,
  PaymentFilters,
  PaymentAllocation,
  BillingStats,
  PolicyBillingSummary,
  ActionRequiredInvoices,
  RecordPaymentPayload,
  AllocatePaymentPayload,
} from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse, PaginationMeta } from '../models/api-reponse';

interface BillingState {
  // Invoices
  invoices: Invoice[];
  selectedInvoice: Invoice | null;
  invoicePagination: PaginationMeta | null;
  invoiceFilters: InvoiceFilters;

  // Payments
  payments: Payment[];
  selectedPayment: Payment | null;
  paymentPagination: PaginationMeta | null;
  paymentFilters: PaymentFilters;

  // Stats & Dashboard
  stats: BillingStats | null;
  actionRequired: ActionRequiredInvoices | null;
  unallocatedPayments: Payment[];

  // Loading states
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class BillingStore {
  private readonly http = inject(HttpClient);
  private readonly billingUrl = '/api/v1/medical/billing';

  private readonly state = signal<BillingState>({
    invoices: [],
    selectedInvoice: null,
    invoicePagination: null,
    invoiceFilters: {},
    payments: [],
    selectedPayment: null,
    paymentPagination: null,
    paymentFilters: {},
    stats: null,
    actionRequired: null,
    unallocatedPayments: [],
    loading: false,
    saving: false,
  });

  // =========================================================================
  // SELECTORS - INVOICES
  // =========================================================================

  readonly invoices = computed(() => this.state().invoices);
  readonly selectedInvoice = computed(() => this.state().selectedInvoice);
  readonly invoicePagination = computed(() => this.state().invoicePagination);
  readonly invoiceFilters = computed(() => this.state().invoiceFilters);

  readonly invoiceCurrentPage = computed(() => this.invoicePagination()?.current_page ?? 1);
  readonly invoiceTotalPages = computed(() => this.invoicePagination()?.last_page ?? 1);
  readonly invoiceTotalItems = computed(() => this.invoicePagination()?.total ?? 0);
  readonly pageSize = computed(() => this.invoicePagination()?.per_page ?? 25);
  readonly currentPage = computed(() => this.invoiceCurrentPage());
  readonly totalItems = computed(() => this.invoiceTotalItems());

  // Invoice status computed
  readonly overdueInvoices = computed(() =>
    this.invoices().filter((i) => i.status === 'overdue')
  );
  readonly unpaidInvoices = computed(() =>
    this.invoices().filter((i) => ['sent', 'partially_paid', 'overdue'].includes(i.status))
  );
  readonly paidInvoices = computed(() =>
    this.invoices().filter((i) => i.status === 'paid')
  );

  // =========================================================================
  // SELECTORS - PAYMENTS
  // =========================================================================

  readonly payments = computed(() => this.state().payments);
  readonly selectedPayment = computed(() => this.state().selectedPayment);
  readonly paymentPagination = computed(() => this.state().paymentPagination);
  readonly paymentFilters = computed(() => this.state().paymentFilters);

  readonly paymentCurrentPage = computed(() => this.paymentPagination()?.current_page ?? 1);
  readonly paymentTotalPages = computed(() => this.paymentPagination()?.last_page ?? 1);
  readonly paymentTotalItems = computed(() => this.paymentPagination()?.total ?? 0);
  readonly paymentPageSize = computed(() => this.paymentPagination()?.per_page ?? 25);
  readonly isPaymentLoading = computed(() => this.state().loading);

  // Payment status computed
  readonly unallocatedPaymentsList = computed(() =>
    this.payments().filter((p) => p.has_unallocated && p.is_valid)
  );
  readonly confirmedPayments = computed(() =>
    this.payments().filter((p) => p.status === 'confirmed')
  );

  // =========================================================================
  // SELECTORS - GENERAL
  // =========================================================================

  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly stats = computed(() => this.state().stats);
  readonly actionRequired = computed(() => this.state().actionRequired);
  readonly unallocatedPayments = computed(() => this.state().unallocatedPayments);

  // Stats shortcuts
  readonly totalOutstanding = computed(
    () => this.stats()?.invoices?.summary?.total_outstanding ?? 0
  );
  readonly totalOverdue = computed(
    () => this.stats()?.invoices?.summary?.overdue_amount ?? 0
  );
  readonly collectionRate = computed(
    () => this.stats()?.invoices?.collection_rate ?? 0
  );
  readonly overdueCount = computed(
    () => this.stats()?.invoices?.summary?.overdue_count ?? 0
  );

  // =========================================================================
  // INVOICE OPERATIONS
  // =========================================================================

  loadInvoices(filters?: InvoiceFilters) {
    this.state.update((s) => ({ ...s, loading: true, invoiceFilters: filters ?? {} }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.invoice_type) params = params.set('invoice_type', filters.invoice_type);
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);
    if (filters?.outstanding) params = params.set('outstanding', 'true');
    if (filters?.overdue) params = params.set('overdue', 'true');
    if (filters?.invoice_date_from) params = params.set('invoice_date_from', filters.invoice_date_from);
    if (filters?.invoice_date_to) params = params.set('invoice_date_to', filters.invoice_date_to);
    if (filters?.due_date_from) params = params.set('due_date_from', filters.due_date_from);
    if (filters?.due_date_to) params = params.set('due_date_to', filters.due_date_to);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters?.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters?.sort_order) params = params.set('sort_order', filters.sort_order);

    this.http.get<PaginatedResponse<Invoice>>(`${this.billingUrl}/invoices`, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          invoices: res.data,
          invoicePagination: res.meta ?? null,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadInvoicePage(page: number) {
    const currentFilters = this.invoiceFilters();
    this.loadInvoices({ ...currentFilters, page });
  }

  loadInvoice(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Invoice>>(`${this.billingUrl}/invoices/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selectedInvoice: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  loadInvoicesForPolicy(policyId: string, filters?: Partial<InvoiceFilters>) {
    this.state.update((s) => ({
      ...s,
      loading: true,
      invoiceFilters: { ...filters, policy_id: policyId },
    }));

    let params = new HttpParams();
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.invoice_type) params = params.set('type', filters.invoice_type);
    if (filters?.outstanding) params = params.set('outstanding', 'true');
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http
      .get<PaginatedResponse<Invoice>>(`/api/v1/medical/policies/${policyId}/invoices`, { params })
      .subscribe({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            invoices: res.data,
            invoicePagination: res.meta ?? null,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      });
  }

  generateInvoice(policyId: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Invoice>>(`/api/v1/medical/policies/${policyId}/generate-invoice`, {})
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              invoices: [res.data, ...s.invoices],
              selectedInvoice: res.data,
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  sendInvoice(id: string, via: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Invoice>>(`${this.billingUrl}/invoices/${id}/send`, { via })
      .pipe(
        tap({
          next: (res) => this.updateInvoiceInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  cancelInvoice(id: string, reason: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Invoice>>(`${this.billingUrl}/invoices/${id}/cancel`, { reason })
      .pipe(
        tap({
          next: (res) => this.updateInvoiceInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  writeOffInvoice(id: string, reason: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Invoice>>(`${this.billingUrl}/invoices/${id}/write-off`, { reason })
      .pipe(
        tap({
          next: (res) => this.updateInvoiceInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // PAYMENT OPERATIONS
  // =========================================================================

  loadPayments(filters?: PaymentFilters) {
    this.state.update((s) => ({ ...s, loading: true, paymentFilters: filters ?? {} }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.payment_method) params = params.set('payment_method', filters.payment_method);
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);
    if (filters?.unallocated) params = params.set('unallocated', 'true');
    if (filters?.unreconciled) params = params.set('unreconciled', 'true');
    if (filters?.payment_date_from) params = params.set('payment_date_from', filters.payment_date_from);
    if (filters?.payment_date_to) params = params.set('payment_date_to', filters.payment_date_to);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters?.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters?.sort_order) params = params.set('sort_order', filters.sort_order);

    this.http.get<PaginatedResponse<Payment>>(`${this.billingUrl}/payments`, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          payments: res.data,
          paymentPagination: res.meta ?? null,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadPaymentPage(page: number) {
    const currentFilters = this.paymentFilters();
    this.loadPayments({ ...currentFilters, page });
  }

  loadPayment(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Payment>>(`${this.billingUrl}/payments/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selectedPayment: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  loadPaymentsForPolicy(policyId: string, filters?: Partial<PaymentFilters>) {
    this.state.update((s) => ({
      ...s,
      loading: true,
      paymentFilters: { ...filters, policy_id: policyId },
    }));

    let params = new HttpParams();
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.payment_method) params = params.set('payment_method', filters.payment_method);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http
      .get<PaginatedResponse<Payment>>(`/api/v1/medical/policies/${policyId}/payments`, { params })
      .subscribe({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            payments: res.data,
            paymentPagination: res.meta ?? null,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      });
  }

  recordPayment(data: RecordPaymentPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Payment>>(`${this.billingUrl}/payments`, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            payments: [res.data, ...s.payments],
            selectedPayment: res.data,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  recordPaymentForInvoice(invoiceId: string, data: RecordPaymentPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Payment>>(`${this.billingUrl}/invoices/${invoiceId}/payments`, data)
      .pipe(
        tap({
          next: (res) => {
            this.state.update((s) => ({
              ...s,
              payments: [res.data, ...s.payments],
              saving: false,
            }));
            // Reload invoice to get updated balance
            if (this.selectedInvoice()?.id === invoiceId) {
              this.loadInvoice(invoiceId).subscribe();
            }
          },
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  allocatePayment(paymentId: string, data: AllocatePaymentPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<{ allocation: PaymentAllocation; payment: Payment }>>(
        `${this.billingUrl}/payments/${paymentId}/allocate`,
        data
      )
      .pipe(
        tap({
          next: (res) => {
            this.updatePaymentInState(paymentId, res.data.payment);
            // Reload invoice if viewing it
            if (this.selectedInvoice()?.id === data.invoice_id) {
              this.loadInvoice(data.invoice_id).subscribe();
            }
          },
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  autoAllocatePayment(paymentId: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<{ allocations_count: number; payment: Payment }>>(
        `${this.billingUrl}/payments/${paymentId}/auto-allocate`,
        {}
      )
      .pipe(
        tap({
          next: (res) => this.updatePaymentInState(paymentId, res.data.payment),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  confirmPayment(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Payment>>(`${this.billingUrl}/payments/${id}/confirm`, {})
      .pipe(
        tap({
          next: (res) => this.updatePaymentInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  bouncePayment(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Payment>>(`${this.billingUrl}/payments/${id}/bounce`, { reason })
      .pipe(
        tap({
          next: (res) => this.updatePaymentInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  reversePayment(id: string, reason: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Payment>>(`${this.billingUrl}/payments/${id}/reverse`, { reason })
      .pipe(
        tap({
          next: (res) => this.updatePaymentInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  reconcilePayment(id: string, reference?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Payment>>(`${this.billingUrl}/payments/${id}/reconcile`, { reference })
      .pipe(
        tap({
          next: (res) => this.updatePaymentInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  reverseAllocation(allocationId: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<void>>(`${this.billingUrl}/allocations/${allocationId}`).pipe(
      tap({
        next: () => {
          this.state.update((s) => ({ ...s, saving: false }));
          // Reload current data
          if (this.selectedPayment()) {
            this.loadPayment(this.selectedPayment()!.id).subscribe();
          }
          if (this.selectedInvoice()) {
            this.loadInvoice(this.selectedInvoice()!.id).subscribe();
          }
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // STATS & DASHBOARD
  // =========================================================================

  loadStats(filters?: { policy_id?: string; group_id?: string; from_date?: string; to_date?: string }) {
    let params = new HttpParams();
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);
    if (filters?.from_date) params = params.set('from_date', filters.from_date);
    if (filters?.to_date) params = params.set('to_date', filters.to_date);

    this.http.get<ApiResponse<BillingStats>>(`${this.billingUrl}/stats`, { params }).subscribe({
      next: (res) => this.state.update((s) => ({ ...s, stats: res.data })),
    });
  }

  loadActionRequired() {
    this.http
      .get<ApiResponse<ActionRequiredInvoices>>(`${this.billingUrl}/action-required`)
      .subscribe({
        next: (res) => this.state.update((s) => ({ ...s, actionRequired: res.data })),
      });
  }

  loadUnallocatedPayments(filters?: { policy_id?: string; group_id?: string }) {
    let params = new HttpParams();
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);

    this.http
      .get<PaginatedResponse<Payment>>(`${this.billingUrl}/unallocated-payments`, { params })
      .subscribe({
        next: (res) => this.state.update((s) => ({ ...s, unallocatedPayments: res.data })),
      });
  }

  loadPolicyBillingSummary(policyId: string) {
    return this.http.get<ApiResponse<PolicyBillingSummary>>(
      `/api/v1/medical/policies/${policyId}/billing-summary`
    );
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearSelectedInvoice() {
    this.state.update((s) => ({ ...s, selectedInvoice: null }));
  }

  clearSelectedPayment() {
    this.state.update((s) => ({ ...s, selectedPayment: null }));
  }

  clearInvoices() {
    this.state.update((s) => ({ ...s, invoices: [], invoicePagination: null }));
  }

  clearPayments() {
    this.state.update((s) => ({ ...s, payments: [], paymentPagination: null }));
  }

  private updateInvoiceInState(id: string, data: Invoice) {
    this.state.update((s) => ({
      ...s,
      invoices: s.invoices.map((item) => (item.id === id ? data : item)),
      selectedInvoice: s.selectedInvoice?.id === id ? data : s.selectedInvoice,
      saving: false,
    }));
  }

  private updatePaymentInState(id: string, data: Payment) {
    this.state.update((s) => ({
      ...s,
      payments: s.payments.map((item) => (item.id === id ? data : item)),
      selectedPayment: s.selectedPayment?.id === id ? data : s.selectedPayment,
      saving: false,
    }));
  }
}
