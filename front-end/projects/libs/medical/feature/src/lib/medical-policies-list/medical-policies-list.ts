// libs/medical/feature/src/lib/policies/medical-policies-list.ts
// Medical Policies List Component - Aligned with Plans/Schemes patterns

import {
  Component,
  OnInit,
  AfterViewInit,
  ViewChild,
  inject,
  signal,
  computed,
  effect,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatDialog } from '@angular/material/dialog';
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
import { MatTabsModule } from '@angular/material/tabs';

// Domain Imports
import {
  PolicyStore,
  Policy,
  POLICY_TYPES,
  POLICY_STATUSES,
  BILLING_FREQUENCIES,
  getLabelByValue,
  getStatusConfig,
  formatCurrency,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalPolicyDialog } from '../dialogs/medical-policy-dialog/medical-policy-dialog';

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
    MatTabsModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-policies-list.html',
})
export class MedicalPoliciesList implements OnInit, AfterViewInit {
  readonly store = inject(PolicyStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table
  displayedColumns = [
    'status',
    'policy_number',
    'type',
    'plan',
    'holder',
    'members',
    'premium',
    'expiry',
    'actions',
  ];
  dataSource = new MatTableDataSource<Policy>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');

  // Selected item for drawer
  selectedPolicy = signal<Policy | null>(null);

  // Constants
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly BILLING_FREQUENCIES = BILLING_FREQUENCIES;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;

  // Computed
  hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  constructor() {
    effect(() => {
      const policies = this.store.policies();
      this.dataSource.data = policies;
    });
  }

  ngOnInit(): void {
    this.store.loadAll();
    this.store.loadStats();
    this.setupFilter();
  }

  ngAfterViewInit(): void {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  private setupFilter(): void {
    this.dataSource.filterPredicate = (data: Policy, filter: string) => {
      const searchData = JSON.parse(filter);

      const textMatch =
        !searchData.search ||
        data.policy_number.toLowerCase().includes(searchData.search) ||
        (data.policy_holder_name?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.plan?.name.toLowerCase().includes(searchData.search) ?? false);

      const statusMatch = !searchData.status || data.status === searchData.status;
      const typeMatch = !searchData.type || data.policy_type === searchData.type;

      return textMatch && statusMatch && typeMatch;
    };
  }

  private applyFilter(): void {
    const filterValue = JSON.stringify({
      search: this.searchTerm().toLowerCase(),
      status: this.statusFilter(),
      type: this.typeFilter(),
    });
    this.dataSource.filter = filterValue;

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.applyFilter();
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.applyFilter();
  }

  onTypeChange(value: string): void {
    this.typeFilter.set(value);
    this.applyFilter();
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.applyFilter();
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.typeFilter.set('');
    this.applyFilter();
  }

  // =========================================================================
  // DRAWER
  // =========================================================================

  viewDetails(policy: Policy): void {
    this.store.loadOne(policy.id).subscribe((res) => {
      if (res?.data) {
        this.selectedPolicy.set(res.data);
        this.detailDrawer.open();
      }
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedPolicy.set(null);
  }

  // =========================================================================
  // DIALOGS
  // =========================================================================

  openDialog(policy?: Policy): void {
    const dialogRef = this.dialog.open(MedicalPolicyDialog, {
      width: '70vw',
      minWidth: '70vw',
      maxHeight: '90vh',
      data: { policy },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.store.loadStats();
        this.feedback.success(
          policy ? 'Policy updated successfully' : 'Policy created successfully'
        );
      }
    });
  }

  // =========================================================================
  // STATUS ACTIONS (with confirmations)
  // =========================================================================

  async activatePolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Activate Policy',
      `Are you sure you want to activate policy "${policy.policy_number}"? Coverage will become effective.`
    );

    if (confirmed) {
      this.store.activate(policy.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Policy activated successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to activate policy');
        },
      });
    }
  }

  async suspendPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Suspend Policy',
      `Are you sure you want to suspend policy "${policy.policy_number}"? Coverage will be temporarily paused.`
    );

    if (confirmed) {
      this.store.suspend(policy.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Policy suspended successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to suspend policy');
        },
      });
    }
  }

  async cancelPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Cancel Policy',
      `Are you sure you want to cancel policy "${policy.policy_number}"? This action cannot be undone and all coverage will be terminated.`
    );

    if (confirmed) {
      this.store.cancel(policy.id, 'customer_request').subscribe({
        next: (res) => {
          if (res) {
            this.feedback.success('Policy cancelled successfully');
            if (this.selectedPolicy()?.id === policy.id) {
              this.closeDrawer();
            }
          }
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to cancel policy');
        },
      });
    }
  }

  async renewPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Renew Policy',
      `This will create a new policy for the next term. Proceed with renewal for "${policy.policy_number}"?`
    );

    if (confirmed) {
      this.store.renew(policy.id).subscribe({
        next: (res) => {
          if (res) {
            this.feedback.success('Policy renewed successfully');
            this.store.loadAll();
          }
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to renew policy');
        },
      });
    }
  }

  // =========================================================================
  // CERTIFICATE
  // =========================================================================

  downloadCertificate(policy: Policy, event?: Event): void {
    event?.stopPropagation();

    if (policy.status !== 'active') {
      this.feedback.error('Certificate is only available for active policies');
      return;
    }

    this.store.downloadCertificate(policy.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `certificate-${policy.policy_number}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
        this.feedback.success('Certificate downloaded');
      },
      error: (err) => {
        this.feedback.error(err.error?.message || 'Failed to download certificate');
      },
    });
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusClasses(status: string): string {
    const config = getStatusConfig(POLICY_STATUSES, status);
    return config ? `${config.bgColor} ${config.color}` : 'bg-slate-100 text-slate-600';
  }

  getStatusDotColor(status: string): string {
    const config = getStatusConfig(POLICY_STATUSES, status);
    if (!config) return 'bg-slate-400';
    const match = config.bgColor.match(/bg-(\w+)-\d+/);
    if (match) {
      return `bg-${match[1]}-500`;
    }
    return 'bg-slate-400';
  }

  getTypeIcon(type: string): string {
    return POLICY_TYPES.find((t) => t.value === type)?.icon || 'description';
  }

  getDaysToExpiry(policy: Policy): { days: number; class: string } {
    if (!policy.expiry_date) return { days: 0, class: 'text-slate-500' };

    const today = new Date();
    const expiry = new Date(policy.expiry_date);
    const days = Math.ceil((expiry.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    let cssClass = 'text-green-600';
    if (days <= 0) cssClass = 'text-red-600';
    else if (days <= 30) cssClass = 'text-amber-600';
    else if (days <= 60) cssClass = 'text-orange-500';

    return { days, class: cssClass };
  }

  exportToCsv(): void {
    const data = this.dataSource.filteredData;

    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = [
      'Policy #',
      'Type',
      'Plan',
      'Holder',
      'Status',
      'Members',
      'Premium',
      'Expiry',
    ];

    const csvContent = [
      headers.join(','),
      ...data.map((p) =>
        [
          `"${p.policy_number}"`,
          `"${getLabelByValue(POLICY_TYPES, p.policy_type)}"`,
          `"${p.plan?.name || ''}"`,
          `"${p.policy_holder_name || ''}"`,
          `"${p.status}"`,
          p.members_count || 0,
          p.net_premium || 0,
          `"${p.expiry_date || ''}"`,
        ].join(',')
      ),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `policies_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
