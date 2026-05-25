// libs/medical/feature/src/lib/medical-billing-payments/medical-billing-payments.ts

import { Component, OnInit, ViewChild, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatMenuModule } from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDividerModule } from '@angular/material/divider';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDrawer, MatSidenavModule } from '@angular/material/sidenav';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog } from '@angular/material/dialog';

// Domain Imports
import { BillingStore, Payment, PaymentFilters, formatCurrency } from 'medical-data';
import { FeedbackService, PageHeaderComponent, PermissionDirective } from 'shared';
import { MEDICAL_PERMISSIONS } from 'core-auth';
import {
  PAYMENT_STATUS_STYLES,
  PAYMENT_FILTER_OPTIONS,
  BILLING_UI_CONFIG,
  PAYMENT_CSV_CONFIG,
  BILLING_PAYMENT_METHODS,
} from 'medical-data';
import { MedicalRecordPaymentDialog } from '../dialogs/medical-record-payment-dialog/medical-record-payment-dialog';

@Component({
  selector: 'lib-medical-billing-payments',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    FormsModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatMenuModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
    MatDividerModule,
    MatSelectModule,
    MatFormFieldModule,
    MatInputModule,
    MatSidenavModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    PageHeaderComponent,
    PermissionDirective,
  ],
  templateUrl: './medical-billing-payments.html',
})
export class MedicalBillingPayments implements OnInit {
  readonly store = inject(BillingStore);
  private readonly feedback = inject(FeedbackService);
  private readonly dialog = inject(MatDialog);

  // Permissions
  readonly permissions = MEDICAL_PERMISSIONS;

  // Table columns
  displayedColumns = [
    'status',
    'payment_number',
    'policy',
    'method',
    'amount',
    'allocated',
    'unallocated',
    'date',
    'actions',
  ];

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // ── Filters (local UI state) ─────────────────────────────────────────────
  searchTerm      = signal('');
  statusFilter    = signal('');
  methodFilter    = signal('');
  unallocatedOnly = signal(false);
  dateFrom        = signal<string>('');
  dateTo          = signal<string>('');

  // Debounce timer for search
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

  // Selected item for drawer
  selectedPayment = signal<Payment | null>(null);

  // Constants
  readonly PAYMENT_STATUS_STYLES  = PAYMENT_STATUS_STYLES;
  readonly PAYMENT_FILTER_OPTIONS = PAYMENT_FILTER_OPTIONS;
  readonly BILLING_UI_CONFIG      = BILLING_UI_CONFIG;
  readonly BILLING_PAYMENT_METHODS = BILLING_PAYMENT_METHODS;
  readonly formatCurrency          = formatCurrency;

  // Page size options for paginator
  readonly pageSizeOptions = BILLING_UI_CONFIG.PAGE_SIZE_OPTIONS;

  // Computed Logic
  readonly hasActiveFilters = computed(
    () =>
      this.searchTerm()      !== '' ||
      this.statusFilter()    !== '' ||
      this.methodFilter()    !== '' ||
      this.unallocatedOnly() ||
      this.dateFrom()        !== '' ||
      this.dateTo()          !== ''
  );

  // KPIs from store stats
  readonly totalReceived     = computed(() => this.store.stats()?.payments?.summary?.total_received     ?? 0);
  readonly unallocatedAmount = computed(() => this.store.stats()?.payments?.summary?.total_unallocated  ?? 0);
  readonly paymentsThisMonth = computed(() => this.store.stats()?.payments?.summary?.total_payments     ?? 0);

  ngOnInit(): void {
    this.loadPayments();
    this.store.loadStats();
  }

  // Build filters object from current UI state
  private buildFilters(): PaymentFilters {
    const filters: PaymentFilters = {
      per_page: this.store.paymentPageSize(),
      page:     this.store.paymentCurrentPage(),
    };

    if (this.searchTerm())      filters.search          = this.searchTerm();
    if (this.statusFilter())    filters.status          = this.statusFilter();
    if (this.methodFilter())    filters.payment_method  = this.methodFilter();
    if (this.unallocatedOnly()) filters.unallocated     = true;
    if (this.dateFrom())        filters.payment_date_from = this.dateFrom();
    if (this.dateTo())          filters.payment_date_to   = this.dateTo();

    return filters;
  }

  private loadPayments(): void {
    this.store.loadPayments(this.buildFilters());
  }

  // ── Filter Handlers ──────────────────────────────────────────────────────

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    if (this.searchDebounceTimer) clearTimeout(this.searchDebounceTimer);
    this.searchDebounceTimer = setTimeout(() => {
      this.store.loadPayments({ ...this.buildFilters(), page: 1 });
    }, 300);
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.store.loadPayments({ ...this.buildFilters(), page: 1 });
  }

  onMethodChange(value: string): void {
    this.methodFilter.set(value);
    this.store.loadPayments({ ...this.buildFilters(), page: 1 });
  }

  onDateFromChange(value: string): void {
    this.dateFrom.set(value);
    this.store.loadPayments({ ...this.buildFilters(), page: 1 });
  }

  onDateToChange(value: string): void {
    this.dateTo.set(value);
    this.store.loadPayments({ ...this.buildFilters(), page: 1 });
  }

  toggleUnallocatedOnly(): void {
    this.unallocatedOnly.set(!this.unallocatedOnly());
    this.store.loadPayments({ ...this.buildFilters(), page: 1 });
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.store.loadPayments({ ...this.buildFilters(), search: undefined, page: 1 });
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.methodFilter.set('');
    this.unallocatedOnly.set(false);
    this.dateFrom.set('');
    this.dateTo.set('');
    this.store.loadPayments({ per_page: this.store.paymentPageSize(), page: 1 });
  }

  // ── Pagination ────────────────────────────────────────────────────────────

  onPageChange(event: { pageIndex: number; pageSize: number }): void {
    const filters  = this.buildFilters();
    filters.page     = event.pageIndex + 1;
    filters.per_page = event.pageSize;
    this.store.loadPayments(filters);
  }

  // =========================================================================
  // DRAWER & DETAILS
  // =========================================================================

  viewDetails(payment: Payment): void {
    this.store.loadPayment(payment.id).subscribe({
      next: () => {
        this.selectedPayment.set(this.store.selectedPayment());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load payment details'),
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedPayment.set(null);
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  openRecordPaymentDialog(): void {
    const dialogRef = this.dialog.open(MedicalRecordPaymentDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.recordPayment(result).subscribe({
          next: () => {
            this.feedback.success('Payment recorded successfully');
            this.loadPayments();
            this.store.loadStats();
          },
          error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to record payment'),
        });
      }
    });
  }

  async confirmPayment(payment: Payment, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Confirm Payment',
        `Confirm payment ${payment.payment_number}? This marks it as verified and confirmed.`
      )
    ) {
      this.store.confirmPayment(payment.id).subscribe({
        next: () => {
          this.feedback.success('Payment confirmed');
          this.refreshAfterAction(payment.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to confirm payment'),
      });
    }
  }

  async bouncePayment(payment: Payment, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Mark as Bounced',
        `Mark payment ${payment.payment_number} as bounced? All allocations will be reversed.`
      )
    ) {
      this.store.bouncePayment(payment.id, 'Returned / bounced by bank').subscribe({
        next: () => {
          this.feedback.success('Payment marked as bounced');
          this.loadPayments();
          this.store.loadStats();
          if (this.selectedPayment()?.id === payment.id) this.closeDrawer();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to mark payment as bounced'),
      });
    }
  }

  async reconcilePayment(payment: Payment, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Reconcile Payment',
        `Reconcile payment ${payment.payment_number}? This marks it as matched against bank records.`
      )
    ) {
      this.store.reconcilePayment(payment.id).subscribe({
        next: () => {
          this.feedback.success('Payment reconciled');
          this.refreshAfterAction(payment.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reconcile payment'),
      });
    }
  }

  async autoAllocate(payment: Payment, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (payment.unallocated_amount <= 0) {
      this.feedback.info('No unallocated amount to allocate');
      return;
    }

    if (
      await this.feedback.confirm(
        'Auto-Allocate Payment',
        `Automatically allocate ${formatCurrency(payment.unallocated_amount)} to outstanding invoices?`
      )
    ) {
      this.store.autoAllocatePayment(payment.id).subscribe({
        next: () => {
          this.feedback.success('Payment allocated successfully');
          this.refreshAfterAction(payment.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to allocate payment'),
      });
    }
  }

  async reversePayment(payment: Payment, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Reverse Payment',
        `Reverse payment ${payment.payment_number}? This will also reverse all allocations.`
      )
    ) {
      this.store.reversePayment(payment.id, 'Reversed by user').subscribe({
        next: () => {
          this.feedback.success('Payment reversed');
          this.loadPayments();   // reload list after reversal
          this.store.loadStats();
          if (this.selectedPayment()?.id === payment.id) this.closeDrawer();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reverse payment'),
      });
    }
  }

  private refreshAfterAction(paymentId: string): void {
    if (this.selectedPayment()?.id === paymentId) {
      this.store.loadPayment(paymentId).subscribe({
        next: () => this.selectedPayment.set(this.store.selectedPayment()),
      });
    }
    this.loadPayments();
    this.store.loadStats();
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusStyles(status: string): { bg: string; text: string; dot: string } {
    return (
      PAYMENT_STATUS_STYLES[status] ?? {
        bg:   'bg-slate-100',
        text: 'text-slate-600',
        dot:  'bg-slate-400',
      }
    );
  }

  getStatusLabel(status: string): string {
    return PAYMENT_STATUS_STYLES[status]?.label ?? status;
  }

  getMethodLabel(method: string): string {
    const found = BILLING_PAYMENT_METHODS.find((m) => m.value === method);
    return found?.label ?? method;
  }

  formatDate(date: string | null | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-ZM', {
      day:   '2-digit',
      month: 'short',
      year:  'numeric',
    });
  }

  hasUnallocated(payment: Payment): boolean {
    return payment.unallocated_amount > 0;
  }

  exportToCsv(): void {
    const data = this.store.payments();
    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = PAYMENT_CSV_CONFIG.headers;
    const rows = data.map((pmt: Payment) => [
      pmt.payment_number,
      pmt.policy?.policy_number ?? '',
      `"${pmt.policy?.holder_name ?? ''}"`,
      pmt.payment_method,
      pmt.status,
      pmt.amount,
      pmt.allocated_amount,
      pmt.unallocated_amount,
      pmt.payment_date,
      `"${pmt.payment_reference ?? ''}"`,
      `"${pmt.received_by ?? ''}"`,
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url  = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href     = url;
    link.download = PAYMENT_CSV_CONFIG.filename(new Date().toISOString().split('T')[0]);
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
