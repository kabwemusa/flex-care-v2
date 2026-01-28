// libs/medical/feature/src/lib/policies/medical-policies-list.ts

import {
  Component,
  OnInit,
  ViewChild,
  inject,
  signal,
  computed,
} from '@angular/core';
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
import {
  PolicyStore,
  PolicyFilters,
  Policy,
  POLICY_STATUSES,
  POLICY_TYPES,
  getLabelByValue,
  getStatusConfig,
  formatCurrency,
  POLICY_UI_CONFIG,
  POLICY_STATUS_STYLES,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent, PermissionDirective } from 'shared';
import { MedicalAddMemberToPolicyDialog } from '../dialogs/medical-add-member-to-policy-dialog/medical-add-member-to-policy-dialog';
import { MEDICAL_PERMISSIONS } from 'core-auth';

@Component({
  selector: 'lib-medical-policies-list',
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
  templateUrl: './medical-policies-list.html',
})
export class MedicalPoliciesList implements OnInit {
  readonly store = inject(PolicyStore);
  private readonly feedback = inject(FeedbackService);
  private readonly dialog = inject(MatDialog);

  // Permissions
  readonly permissions = MEDICAL_PERMISSIONS;

  // Table
  displayedColumns = ['status', 'policy_number', 'holder', 'plan', 'members', 'dates', 'premium', 'actions'];

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters (local UI state)
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');

  // Debounce timer for search
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

  // Selected item for drawer
  selectedPolicy = signal<Policy | null>(null);

  // Constants
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly POLICY_UI_CONFIG = POLICY_UI_CONFIG;
  readonly POLICY_STATUS_STYLES = POLICY_STATUS_STYLES;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;

  // Page size options for paginator
  readonly pageSizeOptions = [10, 25, 50, 100];

  // Computed Logic
  readonly hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  // Local KPIs (based on current page data)
  readonly totalPremiumVolume = computed(() =>
    this.store.activePolicies().reduce((sum, p) => sum + (p.gross_premium || 0), 0)
  );

  readonly upcomingRenewals = computed(
    () =>
      this.store.policies().filter((p) => {
        if (!p.expiry_date || p.status !== 'active') return false;
        const expiry = new Date(p.expiry_date);
        const today = new Date();
        const diffDays = Math.ceil((expiry.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
        return diffDays <= 30 && diffDays >= 0;
      }).length
  );

  ngOnInit(): void {
    this.loadPolicies();
  }

  // Build filters object from current UI state
  private buildFilters(): PolicyFilters {
    const filters: PolicyFilters = {
      per_page: this.store.pageSize(),
      page: this.store.currentPage(),
    };

    if (this.searchTerm()) filters.search = this.searchTerm();
    if (this.statusFilter()) filters.status = this.statusFilter();
    if (this.typeFilter()) filters.policy_type = this.typeFilter();

    return filters;
  }

  // Load policies with current filters
  private loadPolicies(): void {
    this.store.loadAll(this.buildFilters());
  }

  // Filter Handlers with server-side filtering
  onSearchChange(value: string): void {
    this.searchTerm.set(value);

    // Debounce search to avoid too many API calls
    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
    }

    this.searchDebounceTimer = setTimeout(() => {
      this.store.loadAll({ ...this.buildFilters(), page: 1 }); // Reset to page 1 on search
    }, 300);
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.store.loadAll({ ...this.buildFilters(), page: 1 }); // Reset to page 1 on filter change
  }

  onTypeChange(value: string): void {
    this.typeFilter.set(value);
    this.store.loadAll({ ...this.buildFilters(), page: 1 }); // Reset to page 1 on filter change
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.store.loadAll({ ...this.buildFilters(), search: undefined, page: 1 });
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.typeFilter.set('');
    this.store.loadAll({ per_page: this.store.pageSize(), page: 1 });
  }

  // Pagination handlers
  onPageChange(event: { pageIndex: number; pageSize: number }): void {
    const newPage = event.pageIndex + 1; // MatPaginator is 0-indexed, API is 1-indexed
    const filters = this.buildFilters();
    filters.page = newPage;
    filters.per_page = event.pageSize;
    this.store.loadAll(filters);
  }

  // =========================================================================
  // DRAWER & DIALOGS
  // =========================================================================

  viewDetails(policy: Policy): void {
    this.store.loadOne(policy.id).subscribe({
      next: () => {
        this.selectedPolicy.set(this.store.selectedPolicy());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load policy details'),
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedPolicy.set(null);
  }

  openAddMemberDialog(policy: Policy): void {
    const dialogRef = this.dialog.open(MedicalAddMemberToPolicyDialog, {
      width: '60vw',
      minWidth: '60vw',
      maxHeight: '90vh',
      data: { policy },
      disableClose: true,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.addMember(policy.id, result).subscribe({
        next: () => {
          this.feedback.success('Member added successfully with pro-rated premium');
          // Reload the policy details in drawer
          this.store.loadOne(policy.id).subscribe({
            next: () => {
              this.selectedPolicy.set(this.store.selectedPolicy());
            },
          });
        },
        error: (err) => {
          this.feedback.error(err?.error?.message ?? 'Failed to add member');
        },
      });
    });
  }

  // Note: Policies are created via application conversion, not directly
  // This method kept for reference but should not be used
  // openDialog(policy?: Policy): void {
  //   const dialogRef = this.dialog.open(MedicalPolicyDialog, {
  //     width: '70vw',
  //     minWidth: '70vw',
  //     maxHeight: '90vh',
  //     data: { policy },
  //     disableClose: true,
  //   });

  //   dialogRef.afterClosed().subscribe((result) => {
  //     if (result) {
  //       this.store.loadAll();
  //       this.feedback.success(
  //         policy ? 'Policy updated successfully' : 'Policy created successfully'
  //       );
  //     }
  //   });
  // }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async activatePolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm('Activate Policy', `Activate policy ${policy.policy_number}?`)
    ) {
      this.store.activate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy activated');
          if (this.selectedPolicy()?.id === policy.id) {
            this.selectedPolicy.set({ ...this.selectedPolicy()!, status: 'active' });
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate policy'),
      });
    }
  }

  async suspendPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    const reason = 'Administrative suspension';
    if (await this.feedback.confirm('Suspend Policy', `Suspend policy ${policy.policy_number}?`)) {
      this.store.suspend(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy suspended');
          // Update drawer if this policy is currently being viewed
          if (this.selectedPolicy()?.id === policy.id) {
            this.selectedPolicy.set({ ...this.selectedPolicy()!, status: 'suspended' });
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to suspend policy'),
      });
    }
  }

  async reinstatePolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm('Reinstate Policy', `Reinstate policy ${policy.policy_number}?`)
    ) {
      this.store.reinstate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy reinstated');
          if (this.selectedPolicy()?.id === policy.id) {
            this.selectedPolicy.set({ ...this.selectedPolicy()!, status: 'active' });
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reinstate policy'),
      });
    }
  }

  async cancelPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    const reason = 'Client request';
    if (
      await this.feedback.confirm(
        'Cancel Policy',
        `Are you sure you want to cancel policy ${policy.policy_number}? This action is permanent.`
      )
    ) {
      this.store.cancel(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy cancelled');
          if (this.selectedPolicy()?.id === policy.id) {
            this.closeDrawer();
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to cancel policy'),
      });
    }
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusClasses(status: string): string {
    const colorMap: Record<string, string> = {
      pending: 'bg-amber-50 text-amber-700 border-amber-200',
      active: 'bg-green-50 text-green-700 border-green-200',
      suspended: 'bg-orange-50 text-orange-700 border-orange-200',
      cancelled: 'bg-red-50 text-red-700 border-red-200',
      expired: 'bg-slate-100 text-slate-700 border-slate-200',
      lapsed: 'bg-rose-50 text-rose-700 border-rose-200',
    };
    return colorMap[status] || 'bg-slate-100 text-slate-700 border-slate-200';
  }

  exportToCsv(): void {
    const data = this.store.policies();
    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = [
      'Policy #',
      'Holder',
      'Scheme',
      'Plan',
      'Status',
      'Inception',
      'Premium',
      'Type',
    ];
    const rows = data.map((p: Policy) => [
      p.policy_number,
      `"${p.holder_name}"`,
      `"${p.scheme?.name || ''}"`,
      `"${p.plan?.name || ''}"`,
      p.status,
      p.inception_date,
      p.gross_premium,
      p.policy_type,
    ]);

    const csvContent = [headers.join(','), ...rows.map((r: (string | number | undefined)[]) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `policies_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
