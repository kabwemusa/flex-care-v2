// libs/medical/feature/src/lib/medical-billing-invoices/medical-billing-invoices.ts

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
import { MatDatepickerModule } from '@angular/material/datepicker';

// Domain Imports
import {
  BillingStore,
  Invoice,
  InvoiceFilters,
  formatCurrency,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import {
  INVOICE_STATUS_STYLES,
  INVOICE_FILTER_OPTIONS,
  BILLING_UI_CONFIG,
  INVOICE_CSV_CONFIG,
} from 'medical-data';

@Component({
  selector: 'lib-medical-billing-invoices',
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
    MatDatepickerModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-billing-invoices.html',
})
export class MedicalBillingInvoices implements OnInit {
  readonly store = inject(BillingStore);
  private readonly feedback = inject(FeedbackService);
  private readonly dialog = inject(MatDialog);

  // Table
  displayedColumns = [
    'status',
    'invoice_number',
    'policy',
    'type',
    'period',
    'total',
    'balance',
    'due_date',
    'actions',
  ];

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters (local UI state)
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');
  overdueOnly = signal(false);

  // Debounce timer for search
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

  // Selected item for drawer
  selectedInvoice = signal<Invoice | null>(null);

  // Constants
  readonly INVOICE_STATUS_STYLES = INVOICE_STATUS_STYLES;
  readonly INVOICE_FILTER_OPTIONS = INVOICE_FILTER_OPTIONS;
  readonly BILLING_UI_CONFIG = BILLING_UI_CONFIG;
  readonly formatCurrency = formatCurrency;

  // Page size options for paginator
  readonly pageSizeOptions = BILLING_UI_CONFIG.PAGE_SIZE_OPTIONS;

  // Computed Logic
  readonly hasActiveFilters = computed(
    () =>
      this.searchTerm() !== '' ||
      this.statusFilter() !== '' ||
      this.typeFilter() !== '' ||
      this.overdueOnly()
  );

  // KPIs from store stats
  readonly totalOutstanding = computed(() => this.store.stats()?.invoices?.summary?.total_outstanding ?? 0);
  readonly overdueCount = computed(() => this.store.stats()?.invoices?.summary?.overdue_count ?? 0);
  readonly overdueAmount = computed(() => this.store.stats()?.invoices?.summary?.overdue_amount ?? 0);
  readonly collectionRate = computed(() => {
    const stats = this.store.stats();
    if (!stats || stats.invoices?.summary?.total_invoiced === 0) return 0;
    return ((stats.invoices.summary.total_collected / stats.invoices.summary.total_invoiced) * 100).toFixed(1);
  });

  ngOnInit(): void {
    this.loadInvoices();
    this.store.loadStats();
  }

  // Build filters object from current UI state
  private buildFilters(): InvoiceFilters {
    const filters: InvoiceFilters = {
      per_page: this.store.pageSize(),
      page: this.store.currentPage(),
    };

    if (this.searchTerm()) filters.search = this.searchTerm();
    if (this.statusFilter()) filters.status = this.statusFilter();
    if (this.typeFilter()) filters.invoice_type = this.typeFilter();
    if (this.overdueOnly()) filters.overdue = true;

    return filters;
  }

  // Load invoices with current filters
  private loadInvoices(): void {
    this.store.loadInvoices(this.buildFilters());
  }

  // Filter Handlers with server-side filtering
  onSearchChange(value: string): void {
    this.searchTerm.set(value);

    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
    }

    this.searchDebounceTimer = setTimeout(() => {
      this.store.loadInvoices({ ...this.buildFilters(), page: 1 });
    }, 300);
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.store.loadInvoices({ ...this.buildFilters(), page: 1 });
  }

  onTypeChange(value: string): void {
    this.typeFilter.set(value);
    this.store.loadInvoices({ ...this.buildFilters(), page: 1 });
  }

  toggleOverdueOnly(): void {
    this.overdueOnly.set(!this.overdueOnly());
    this.store.loadInvoices({ ...this.buildFilters(), page: 1 });
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.store.loadInvoices({ ...this.buildFilters(), search: undefined, page: 1 });
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.typeFilter.set('');
    this.overdueOnly.set(false);
    this.store.loadInvoices({ per_page: this.store.pageSize(), page: 1 });
  }

  // Pagination handlers
  onPageChange(event: { pageIndex: number; pageSize: number }): void {
    const newPage = event.pageIndex + 1;
    const filters = this.buildFilters();
    filters.page = newPage;
    filters.per_page = event.pageSize;
    this.store.loadInvoices(filters);
  }

  // =========================================================================
  // DRAWER & DETAILS
  // =========================================================================

  viewDetails(invoice: Invoice): void {
    this.store.loadInvoice(invoice.id).subscribe({
      next: () => {
        this.selectedInvoice.set(this.store.selectedInvoice());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load invoice details'),
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedInvoice.set(null);
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async sendInvoice(invoice: Invoice, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Send Invoice',
        `Send invoice ${invoice.invoice_number} to the client?`
      )
    ) {
      this.store.sendInvoice(invoice.id, 'email').subscribe({
        next: () => {
          this.feedback.success('Invoice sent successfully');
          this.refreshAfterAction(invoice.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to send invoice'),
      });
    }
  }

  async cancelInvoice(invoice: Invoice, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm(
        'Cancel Invoice',
        `Cancel invoice ${invoice.invoice_number}? This action cannot be undone.`
      )
    ) {
      this.store.cancelInvoice(invoice.id, 'Cancelled by user').subscribe({
        next: () => {
          this.feedback.success('Invoice cancelled');
          if (this.selectedInvoice()?.id === invoice.id) {
            this.closeDrawer();
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to cancel invoice'),
      });
    }
  }

  recordPayment(invoice: Invoice, event?: Event): void {
    event?.stopPropagation();
    // This will open the record payment dialog
    // For now, navigate to payments tab or open dialog
    this.feedback.info('Navigate to payments to record a payment for this invoice');
  }

  private refreshAfterAction(invoiceId: string): void {
    if (this.selectedInvoice()?.id === invoiceId) {
      this.store.loadInvoice(invoiceId).subscribe({
        next: () => this.selectedInvoice.set(this.store.selectedInvoice()),
      });
    }
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusStyles(status: string): { bg: string; text: string; dot: string } {
    return (
      INVOICE_STATUS_STYLES[status] ?? {
        bg: 'bg-slate-100',
        text: 'text-slate-600',
        dot: 'bg-slate-400',
      }
    );
  }

  getStatusLabel(status: string): string {
    return INVOICE_STATUS_STYLES[status]?.label ?? status;
  }

  getTypeLabel(type: string): string {
    const found = INVOICE_FILTER_OPTIONS.types.find((t) => t.value === type);
    return found?.label ?? type;
  }

  isOverdue(invoice: Invoice): boolean {
    if (!invoice.due_date || invoice.status === 'paid' || invoice.status === 'cancelled') {
      return false;
    }
    return new Date(invoice.due_date) < new Date();
  }

  getDaysOverdue(invoice: Invoice): number {
    if (!invoice.due_date) return 0;
    const dueDate = new Date(invoice.due_date);
    const today = new Date();
    const diffTime = today.getTime() - dueDate.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  }

  formatDate(date: string | null | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-ZM', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  formatPeriod(invoice: Invoice): string {
    if (!invoice.billing_period_start || !invoice.billing_period_end) return '-';
    const start = new Date(invoice.billing_period_start).toLocaleDateString('en-ZM', {
      day: '2-digit',
      month: 'short',
    });
    const end = new Date(invoice.billing_period_end).toLocaleDateString('en-ZM', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
    return `${start} - ${end}`;
  }

  exportToCsv(): void {
    const data = this.store.invoices();
    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = INVOICE_CSV_CONFIG.headers;
    const rows = data.map((inv: Invoice) => [
      inv.invoice_number,
      inv.policy?.policy_number ?? '',
      `"${inv.policy?.holder_name ?? ''}"`,
      inv.invoice_type,
      inv.status,
      inv.invoice_date,
      inv.due_date,
      inv.billing_period_start ?? '',
      inv.billing_period_end ?? '',
      inv.subtotal,
      inv.tax_amount,
      inv.total_amount,
      inv.paid_amount,
      inv.balance,
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = INVOICE_CSV_CONFIG.filename(new Date().toISOString().split('T')[0]);
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
