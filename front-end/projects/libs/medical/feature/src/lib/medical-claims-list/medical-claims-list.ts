// libs/medical/feature/src/lib/medical-claims-list/medical-claims-list.ts

import { Component, OnInit, ViewChild, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSort, MatSortModule, Sort } from '@angular/material/sort';
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
import { ClaimStore, Claim, ClaimFilters } from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalClaimDialog } from '../dialogs/medical-claim-dialog/medical-claim-dialog';

@Component({
  selector: 'lib-medical-claims-list',
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
  templateUrl: './medical-claims-list.html',
  styleUrl: './medical-claims-list.css',
})
export class MedicalClaimsList implements OnInit {
  readonly store = inject(ClaimStore);
  private readonly feedback = inject(FeedbackService);
  private readonly dialog = inject(MatDialog);

  // Table columns
  displayedColumns = [
    'status',
    'claim_number',
    'member',
    'claim_type',
    'service_date',
    'amounts',
    'provider',
    'actions',
  ];

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filter signals
  searchTerm = signal('');
  statusFilter = signal('');
  claimTypeFilter = signal('');
  dateFrom = signal<Date | null>(null);
  dateTo = signal<Date | null>(null);

  // Selected claim for drawer
  selectedClaim = signal<Claim | null>(null);

  // Debounce timer
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

  // Constants
  readonly CLAIM_STATUSES = [
    { value: 'submitted', label: 'Submitted', color: 'bg-blue-100 text-blue-800' },
    {
      value: 'pending_documents',
      label: 'Pending Documents',
      color: 'bg-yellow-100 text-yellow-800',
    },
    { value: 'in_review', label: 'In Review', color: 'bg-purple-100 text-purple-800' },
    {
      value: 'pending_approval',
      label: 'Pending Approval',
      color: 'bg-orange-100 text-orange-800',
    },
    { value: 'approved', label: 'Approved', color: 'bg-green-100 text-green-800' },
    {
      value: 'partially_approved',
      label: 'Partially Approved',
      color: 'bg-teal-100 text-teal-800',
    },
    { value: 'rejected', label: 'Rejected', color: 'bg-red-100 text-red-800' },
    { value: 'paid', label: 'Paid', color: 'bg-emerald-100 text-emerald-800' },
    { value: 'closed', label: 'Closed', color: 'bg-slate-100 text-slate-800' },
  ];

  readonly CLAIM_TYPES = [
    { value: 'in_patient', label: 'In-Patient', icon: 'local_hospital' },
    { value: 'out_patient', label: 'Out-Patient', icon: 'medical_services' },
    { value: 'dental', label: 'Dental', icon: 'dentistry' },
    { value: 'optical', label: 'Optical', icon: 'visibility' },
    { value: 'maternity', label: 'Maternity', icon: 'pregnant_woman' },
    { value: 'chronic', label: 'Chronic', icon: 'medication' },
    { value: 'wellness', label: 'Wellness', icon: 'spa' },
    { value: 'emergency', label: 'Emergency', icon: 'emergency' },
  ];

  readonly pageSizeOptions = [10, 25, 50, 100];

  // Computed
  readonly hasActiveFilters = computed(
    () =>
      this.searchTerm() !== '' ||
      this.statusFilter() !== '' ||
      this.claimTypeFilter() !== '' ||
      this.dateFrom() !== null ||
      this.dateTo() !== null
  );

  readonly totalClaimedAmount = computed(() =>
    this.store.claims().reduce((sum, c) => sum + (c.claimed_amount || 0), 0)
  );

  readonly totalApprovedAmount = computed(() =>
    this.store.claims().reduce((sum, c) => sum + (c.approved_amount || 0), 0)
  );

  ngOnInit() {
    this.loadClaims();
    this.store.loadStats();
  }

  loadClaims() {
    const filters: ClaimFilters = {
      search: this.searchTerm() || undefined,
      status: this.statusFilter() || undefined,
      claim_type: this.claimTypeFilter() || undefined,
      service_date_from: this.dateFrom()?.toISOString().split('T')[0],
      service_date_to: this.dateTo()?.toISOString().split('T')[0],
      per_page: this.store.pageSize(),
      page: this.store.currentPage(),
    };

    this.store.loadAll(filters);
  }

  onSearchChange(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    this.searchTerm.set(value);

    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
    }

    this.searchDebounceTimer = setTimeout(() => {
      this.loadClaims();
    }, 400);
  }

  onFilterChange() {
    this.loadClaims();
  }

  clearFilters() {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.claimTypeFilter.set('');
    this.dateFrom.set(null);
    this.dateTo.set(null);
    this.loadClaims();
  }

  onPageChange(event: PageEvent) {
    this.store.loadAll({
      ...this.store.filters(),
      page: event.pageIndex + 1,
      per_page: event.pageSize,
    });
  }

  onSortChange(sort: Sort) {
    this.store.loadAll({
      ...this.store.filters(),
      sort_by: sort.active,
      sort_order: sort.direction || 'desc',
      page: 1,
    });
  }

  openNewClaimDialog() {
    const dialogRef = this.dialog.open(MedicalClaimDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadClaims();
        this.store.loadStats();
        this.feedback.success('Claim submitted successfully');
      }
    });
  }

  viewClaimDetails(claim: Claim) {
    this.selectedClaim.set(claim);
    this.detailDrawer?.open();
  }

  closeDrawer() {
    this.selectedClaim.set(null);
    this.detailDrawer?.close();
  }

  // Helper methods
  getStatusConfig(status: string) {
    return (
      this.CLAIM_STATUSES.find((s) => s.value === status) || {
        label: status,
        color: 'bg-slate-100 text-slate-600',
      }
    );
  }

  getClaimTypeConfig(type: string) {
    return this.CLAIM_TYPES.find((t) => t.value === type) || { label: type, icon: 'receipt' };
  }

  formatCurrency(amount: number | undefined, currency = 'ZMW'): string {
    if (amount === undefined || amount === null) return '-';
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
    }).format(amount);
  }

  // Quick actions
  startReview(claim: Claim) {
    this.store.startReview(claim.id).subscribe({
      next: () => {
        this.feedback.success('Review started');
        this.store.loadStats();
      },
      error: (err) => this.feedback.error(err.error?.message || 'Failed to start review'),
    });
  }

  approveClaim(claim: Claim) {
    this.store.approve(claim.id).subscribe({
      next: () => {
        this.feedback.success('Claim approved');
        this.store.loadStats();
      },
      error: (err) => this.feedback.error(err.error?.message || 'Failed to approve claim'),
    });
  }
}
