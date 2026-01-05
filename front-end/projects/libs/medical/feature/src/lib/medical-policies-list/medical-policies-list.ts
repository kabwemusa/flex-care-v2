// libs/medical/feature/src/lib/policies/medical-policies-list.ts

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

// Domain Imports
import {
  PolicyStore,
  Policy,
  POLICY_STATUSES,
  POLICY_TYPES,
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
    PageHeaderComponent,
  ],
  templateUrl: './medical-policies-list.html',
})
export class MedicalPoliciesList implements OnInit, AfterViewInit {
  readonly store = inject(PolicyStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table
  displayedColumns = ['status', 'policy_number', 'holder', 'plan', 'dates', 'premium', 'actions'];
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
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;

  // Computed Logic
  readonly hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  // Local KPIs
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

  constructor() {
    effect(() => {
      const policies = this.store.policies();
      this.dataSource.data = policies;
    });
  }

  ngOnInit(): void {
    this.store.loadAll();
    // this.store.loadStats();
    this.setupFilter();
  }

  ngAfterViewInit(): void {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  private setupFilter(): void {
    this.dataSource.filterPredicate = (data: Policy, filter: string) => {
      const searchData = JSON.parse(filter);

      // Text search
      const textMatch =
        !searchData.search ||
        data.policy_number.toLowerCase().includes(searchData.search) ||
        data.holder_name?.toLowerCase().includes(searchData.search) ||
        data.holder_email?.toLowerCase().includes(searchData.search) ||
        data.scheme?.name?.toLowerCase().includes(searchData.search) ||
        (data.group?.name?.toLowerCase().includes(searchData.search) ?? false);

      // Status filter
      const statusMatch = !searchData.status || data.status === searchData.status;

      // Type filter
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

  // Filter Handlers
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
  // DRAWER & DIALOGS
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
        this.feedback.success(
          policy ? 'Policy updated successfully' : 'Policy created successfully'
        );
      }
    });
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async activatePolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm('Activate Policy', `Activate policy ${policy.policy_number}?`)
    ) {
      this.store.activate(policy.id).subscribe({
        next: () => this.feedback.success('Policy activated'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async suspendPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    // In a real app, you might pop a dialog to ask for a reason
    const reason = 'Administrative suspension';
    if (await this.feedback.confirm('Suspend Policy', `Suspend policy ${policy.policy_number}?`)) {
      this.store.suspend(policy.id, reason).subscribe({
        next: () => this.feedback.success('Policy suspended'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async reinstatePolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (
      await this.feedback.confirm('Reinstate Policy', `Reinstate policy ${policy.policy_number}?`)
    ) {
      this.store.reinstate(policy.id).subscribe({
        next: () => this.feedback.success('Policy reinstated'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async cancelPolicy(policy: Policy, event?: Event): Promise<void> {
    event?.stopPropagation();
    // In real app, prompt for reason and effective date
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
          if (this.selectedPolicy()?.id === policy.id) this.closeDrawer();
        },
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
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
    return match ? `bg-${match[1]}-500` : 'bg-slate-400';
  }

  exportToCsv(): void {
    const data = this.dataSource.filteredData;
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
    const rows = data.map((p) => [
      p.policy_number,
      `"${p.holder_name}"`,
      `"${p.scheme?.name || ''}"`,
      `"${p.plan?.name || ''}"`,
      p.status,
      p.inception_date,
      p.gross_premium,
      p.policy_type,
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `policies_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
