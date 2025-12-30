// libs/medical/feature/src/lib/groups/medical-groups-list.ts
// Corporate Groups List Component - Aligned with Schemes/Plans patterns

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
  GroupStore,
  CorporateGroup,
  GROUP_STATUSES,
  INDUSTRIES,
  COMPANY_SIZES,
  getLabelByValue,
  getStatusConfig,
  formatCurrency,
  PAYMENT_TERMS,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalGroupDialog } from '../dialogs/medical-group-dialog/medical-group-dialog';

@Component({
  selector: 'lib-medical-groups-list',
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
  templateUrl: './medical-groups-list.html',
})
export class MedicalGroupsList implements OnInit, AfterViewInit {
  readonly store = inject(GroupStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table
  displayedColumns = ['status', 'name', 'industry', 'size', 'policies', 'members', 'actions'];
  dataSource = new MatTableDataSource<CorporateGroup>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  searchTerm = signal('');
  statusFilter = signal('');
  industryFilter = signal('');
  sizeFilter = signal('');

  // Selected item for drawer
  selectedGroup = signal<CorporateGroup | null>(null);

  // Constants
  readonly GROUP_STATUSES = GROUP_STATUSES;
  readonly INDUSTRIES = INDUSTRIES;
  readonly COMPANY_SIZES = COMPANY_SIZES;
  readonly PAYMENT_TERMS = PAYMENT_TERMS;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;

  // Computed
  hasActiveFilters = computed(
    () =>
      this.searchTerm() !== '' ||
      this.statusFilter() !== '' ||
      this.industryFilter() !== '' ||
      this.sizeFilter() !== ''
  );

  constructor() {
    // Sync store data with table
    effect(() => {
      const groups = this.store.groups();
      this.dataSource.data = groups;
    });
  }

  ngOnInit(): void {
    this.store.loadAll();
    this.setupFilter();
  }

  ngAfterViewInit(): void {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  private setupFilter(): void {
    this.dataSource.filterPredicate = (data: CorporateGroup, filter: string) => {
      const searchData = JSON.parse(filter);

      // Text search
      const textMatch =
        !searchData.search ||
        data.name.toLowerCase().includes(searchData.search) ||
        data.code.toLowerCase().includes(searchData.search) ||
        (data.email?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.trading_name?.toLowerCase().includes(searchData.search) ?? false);

      // Status filter
      const statusMatch = !searchData.status || data.status === searchData.status;

      // Industry filter
      const industryMatch = !searchData.industry || data.industry === searchData.industry;

      // Size filter
      const sizeMatch = !searchData.size || data.company_size === searchData.size;

      return textMatch && statusMatch && industryMatch && sizeMatch;
    };
  }

  private applyFilter(): void {
    const filterValue = JSON.stringify({
      search: this.searchTerm().toLowerCase(),
      status: this.statusFilter(),
      industry: this.industryFilter(),
      size: this.sizeFilter(),
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

  onIndustryChange(value: string): void {
    this.industryFilter.set(value);
    this.applyFilter();
  }

  onSizeChange(value: string): void {
    this.sizeFilter.set(value);
    this.applyFilter();
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.applyFilter();
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.industryFilter.set('');
    this.sizeFilter.set('');
    this.applyFilter();
  }

  // =========================================================================
  // DRAWER
  // =========================================================================

  viewDetails(group: CorporateGroup): void {
    this.store.loadOne(group.id).subscribe((res) => {
      if (res?.data) {
        this.selectedGroup.set(res.data);
        this.detailDrawer.open();
      }
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedGroup.set(null);
  }

  // =========================================================================
  // DIALOGS
  // =========================================================================

  openDialog(group?: CorporateGroup): void {
    const dialogRef = this.dialog.open(MedicalGroupDialog, {
      width: '70vw',
      minWidth: '70vw',
      maxHeight: '90vh',
      data: { group },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.feedback.success(
          group ? 'Client updated successfully' : 'Client created successfully'
        );
      }
    });
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async toggleStatus(group: CorporateGroup, event?: Event): Promise<void> {
    event?.stopPropagation();

    const action = group.status === 'active' ? 'suspend' : 'activate';
    const confirmed = await this.feedback.confirm(
      `${action === 'suspend' ? 'Suspend' : 'Activate'} Client?`,
      `Are you sure you want to ${action} "${group.name}"?`
    );

    if (!confirmed) return;

    if (group.status === 'active') {
      this.store.suspend(group.id).subscribe({
        next: () => this.feedback.success('Client suspended successfully'),
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to suspend client'),
      });
    } else {
      this.store.activate(group.id).subscribe({
        next: () => this.feedback.success('Client activated successfully'),
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate client'),
      });
    }
  }

  async deleteGroup(group: CorporateGroup, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Delete Client?',
      `Are you sure you want to delete "${group.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(group.id).subscribe({
      next: () => {
        this.feedback.success('Client deleted successfully');
        if (this.selectedGroup()?.id === group.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete client'),
    });
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusClasses(status: string): string {
    const config = getStatusConfig(GROUP_STATUSES, status);
    return config ? `${config.bgColor} ${config.color}` : 'bg-gray-100 text-gray-600';
  }

  exportToCsv(): void {
    const dataToExport = this.dataSource.filteredData.length
      ? this.dataSource.filteredData
      : this.store.groups();

    if (dataToExport.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = ['Code', 'Name', 'Industry', 'Size', 'Status', 'Policies', 'Members', 'Email'];
    const rows = dataToExport.map((g) => [
      g.code,
      `"${g.name}"`,
      getLabelByValue(INDUSTRIES, g.industry),
      getLabelByValue(COMPANY_SIZES, g.company_size),
      g.status,
      g.policies_count || 0,
      g.active_members_count || 0,
      g.email || '',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute(
      'download',
      `corporate_clients_${new Date().toISOString().split('T')[0]}.csv`
    );
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
