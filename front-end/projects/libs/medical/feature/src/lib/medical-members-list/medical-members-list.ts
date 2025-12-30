// libs/medical/feature/src/lib/members/medical-members-list.ts
// Medical Members Registry Component - Aligned with Plans/Schemes patterns

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
  MemberStore,
  Member,
  MEMBER_TYPES,
  MEMBER_STATUSES,
  CARD_STATUSES,
  GENDERS,
  getLabelByValue,
  getStatusConfig,
  formatCurrency,
  calculateAge,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalMemberDialog } from '../dialogs/medical-member-dialog/medical-member-dialog';

@Component({
  selector: 'lib-medical-members-list',
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
  templateUrl: './medical-members-list.html',
})
export class MedicalMembersList implements OnInit, AfterViewInit {
  readonly store = inject(MemberStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table
  displayedColumns = [
    'status',
    'member_number',
    'name',
    'type',
    'policy',
    'age',
    'card',
    'actions',
  ];
  dataSource = new MatTableDataSource<Member>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');

  // Selected item for drawer
  selectedMember = signal<Member | null>(null);

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly MEMBER_STATUSES = MEMBER_STATUSES;
  readonly CARD_STATUSES = CARD_STATUSES;
  readonly GENDERS = GENDERS;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;
  readonly calculateAge = calculateAge;

  // Computed
  hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  constructor() {
    effect(() => {
      const members = this.store.members();
      this.dataSource.data = members;
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
    this.dataSource.filterPredicate = (data: Member, filter: string) => {
      const searchData = JSON.parse(filter);

      const textMatch =
        !searchData.search ||
        data.member_number.toLowerCase().includes(searchData.search) ||
        data.first_name.toLowerCase().includes(searchData.search) ||
        data.last_name.toLowerCase().includes(searchData.search) ||
        (data.full_name?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.id_number?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.policy_number?.toLowerCase().includes(searchData.search) ?? false);

      const statusMatch = !searchData.status || data.status === searchData.status;
      const typeMatch = !searchData.type || data.member_type === searchData.type;

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

  viewDetails(member: Member): void {
    this.store.loadOne(member.id).subscribe((res) => {
      if (res?.data) {
        this.selectedMember.set(res.data);
        this.detailDrawer.open();
      }
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedMember.set(null);
  }

  // =========================================================================
  // DIALOGS
  // =========================================================================

  openDialog(member?: Member): void {
    const dialogRef = this.dialog.open(MedicalMemberDialog, {
      width: '70vw',
      minWidth: '70vw',
      maxHeight: '90vh',
      data: { member },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.store.loadStats();
        this.feedback.success(
          member ? 'Member updated successfully' : 'Member created successfully'
        );
      }
    });
  }

  // =========================================================================
  // STATUS ACTIONS (with confirmations)
  // =========================================================================

  async activateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Activate Member',
      `Are you sure you want to activate "${
        member.full_name || member.first_name
      }"? This will enable their coverage.`
    );

    if (confirmed) {
      this.store.activate(member.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Member activated successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to activate member');
        },
      });
    }
  }

  async suspendMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Suspend Member',
      `Are you sure you want to suspend "${
        member.full_name || member.first_name
      }"? Their coverage will be temporarily paused.`
    );

    if (confirmed) {
      this.store.suspend(member.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Member suspended successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to suspend member');
        },
      });
    }
  }

  async terminateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Terminate Member',
      `Are you sure you want to terminate "${
        member.full_name || member.first_name
      }"? This will permanently end their coverage and cannot be undone.`
    );

    if (confirmed) {
      this.store.terminate(member.id, 'voluntary').subscribe({
        next: (res) => {
          if (res) {
            this.feedback.success('Member terminated successfully');
            if (this.selectedMember()?.id === member.id) {
              this.closeDrawer();
            }
          }
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to terminate member');
        },
      });
    }
  }

  // =========================================================================
  // CARD ACTIONS (with confirmations)
  // =========================================================================

  async issueCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Issue Card',
      `Issue a medical card for "${member.full_name || member.first_name}"?`
    );

    if (confirmed) {
      this.store.issueCard(member.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Card issued successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to issue card');
        },
      });
    }
  }

  async activateCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Activate Card',
      `Activate the medical card for "${
        member.full_name || member.first_name
      }"? They will be able to use it at service providers.`
    );

    if (confirmed) {
      this.store.activateCard(member.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Card activated successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to activate card');
        },
      });
    }
  }

  async blockCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Block Card',
      `Block the medical card for "${
        member.full_name || member.first_name
      }"? They won't be able to use it until it's unblocked.`
    );

    if (confirmed) {
      this.store.blockCard(member.id).subscribe({
        next: (res) => {
          if (res) this.feedback.success('Card blocked successfully');
        },
        error: (err) => {
          this.feedback.error(err.error?.message || 'Failed to block card');
        },
      });
    }
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusClasses(status: string): string {
    const config = getStatusConfig(MEMBER_STATUSES, status);
    return config ? `${config.bgColor} ${config.color}` : 'bg-slate-100 text-slate-600';
  }

  getCardStatusClasses(status: string | undefined): string {
    if (!status) return 'bg-slate-100 text-slate-600';
    const config = CARD_STATUSES.find((s) => s.value === status);
    return config?.color || 'text-slate-600';
  }

  getCardStatusDotColor(status: string | undefined): string {
    if (!status || status === 'pending') return 'bg-slate-400';
    if (status === 'issued') return 'bg-blue-500';
    if (status === 'active') return 'bg-green-500';
    if (status === 'blocked') return 'bg-red-500';
    if (status === 'expired') return 'bg-amber-500';
    return 'bg-slate-400';
  }

  getMemberTypeIcon(type: string): string {
    return MEMBER_TYPES.find((t) => t.value === type)?.icon || 'person';
  }

  getStatusDotColor(status: string): string {
    const config = getStatusConfig(MEMBER_STATUSES, status);
    if (!config) return 'bg-slate-400';
    // Extract dot color from bgColor (e.g., 'bg-green-100' -> 'bg-green-500')
    const match = config.bgColor.match(/bg-(\w+)-\d+/);
    if (match) {
      return `bg-${match[1]}-500`;
    }
    return 'bg-slate-400';
  }

  getWaitingPeriodStatus(member: Member): { active: boolean; type?: string; daysLeft?: number } {
    const today = new Date();

    const waitingPeriods = [
      { type: 'General', end: member.general_waiting_end },
      { type: 'Maternity', end: member.maternity_waiting_end },
      { type: 'Pre-existing', end: member.pre_existing_waiting_end },
      { type: 'Chronic', end: member.chronic_waiting_end },
    ];

    for (const wp of waitingPeriods) {
      if (wp.end) {
        const endDate = new Date(wp.end);
        if (endDate > today) {
          const daysLeft = Math.ceil((endDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
          return { active: true, type: wp.type, daysLeft };
        }
      }
    }

    return { active: false };
  }

  exportToCsv(): void {
    const data = this.dataSource.filteredData;

    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = [
      'Member #',
      'Name',
      'Type',
      'Gender',
      'Age',
      'Status',
      'Policy #',
      'Card Status',
    ];

    const csvContent = [
      headers.join(','),
      ...data.map((m) =>
        [
          `"${m.member_number}"`,
          `"${m.full_name || m.first_name + ' ' + m.last_name}"`,
          `"${getLabelByValue(MEMBER_TYPES, m.member_type)}"`,
          `"${m.gender || ''}"`,
          m.age || calculateAge(m.date_of_birth),
          `"${m.status}"`,
          `"${m.policy_number || ''}"`,
          `"${m.card_status || 'pending'}"`,
        ].join(',')
      ),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `members_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
