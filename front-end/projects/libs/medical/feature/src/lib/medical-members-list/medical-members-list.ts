// libs/medical/feature/src/lib/members/medical-members-list.ts
import { Component, OnInit, ViewChild, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
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
  MemberStore,
  MemberFilters,
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
import { FeedbackService, PageHeaderComponent, PermissionDirective } from 'shared';
import { MEDICAL_PERMISSIONS } from 'core-auth';
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
    PageHeaderComponent,
    PermissionDirective,
  ],
  templateUrl: './medical-members-list.html',
})
export class MedicalMembersList implements OnInit {
  readonly store = inject(MemberStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Permissions
  readonly permissions = MEDICAL_PERMISSIONS;

  // Table
  readonly displayedColumns = [
    'status',
    'member_number',
    'name',
    'type',
    'policy',
    'age',
    'card',
    'actions',
  ];

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters (local UI state — sent to server)
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');

  // Debounce timer for search
  private searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

  // Selected item for drawer
  selectedMember = signal<Member | null>(null);

  // Page size options
  readonly pageSizeOptions = [10, 20, 50, 100];

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly MEMBER_STATUSES = MEMBER_STATUSES;
  readonly CARD_STATUSES = CARD_STATUSES;
  readonly GENDERS = GENDERS;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;
  readonly calculateAge = calculateAge;

  // Computed Properties
  readonly hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  ngOnInit(): void {
    this.store.loadAll();
  }

  // =========================================================================
  // FILTER HELPERS
  // =========================================================================

  private buildFilters(): MemberFilters {
    const filters: MemberFilters = {
      per_page: this.store.pageSize(),
      page: this.store.currentPage(),
    };
    if (this.searchTerm()) filters.search = this.searchTerm();
    if (this.statusFilter()) filters.status = this.statusFilter();
    if (this.typeFilter()) filters.member_type = this.typeFilter();
    return filters;
  }

  // =========================================================================
  // FILTER HANDLERS (server-side)
  // =========================================================================

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    if (this.searchDebounceTimer) clearTimeout(this.searchDebounceTimer);
    this.searchDebounceTimer = setTimeout(() => {
      this.store.loadAll({ ...this.buildFilters(), page: 1 });
    }, 300);
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.store.loadAll({ ...this.buildFilters(), page: 1 });
  }

  onTypeChange(value: string): void {
    this.typeFilter.set(value);
    this.store.loadAll({ ...this.buildFilters(), page: 1 });
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

  // =========================================================================
  // PAGINATION
  // =========================================================================

  onPageChange(event: PageEvent): void {
    const filters = this.buildFilters();
    filters.page = event.pageIndex + 1;
    filters.per_page = event.pageSize;
    this.store.loadAll(filters);
  }

  // =========================================================================
  // DRAWER & DIALOGS
  // =========================================================================

  viewDetails(member: Member): void {
    this.store.loadOne(member.id).subscribe({
      next: (res) => {
        if (res?.data) {
          this.selectedMember.set(res.data);
          this.detailDrawer.open();
        }
      },
      error: () => this.feedback.error('Failed to load member details'),
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedMember.set(null);
  }

  openDialog(member?: Member): void {
    const dialogRef = this.dialog.open(MedicalMemberDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { member },
      panelClass: 'bg-white',
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll(this.buildFilters());
      }
    });
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async activateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Activate Member',
      `Are you sure you want to activate "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.activate(member.id).subscribe({
        next: () => this.feedback.success('Member activated successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async suspendMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Suspend Member',
      `Are you sure you want to suspend "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.suspend(member.id).subscribe({
        next: () => this.feedback.success('Member suspended successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async terminateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Terminate Member',
      `Are you sure you want to terminate "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.terminate(member.id, 'voluntary').subscribe({
        next: () => {
          this.feedback.success('Member terminated successfully');
          if (this.selectedMember()?.id === member.id) this.closeDrawer();
        },
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  // =========================================================================
  // CARD ACTIONS
  // =========================================================================

  async issueCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Issue Card', 'Issue a new card?')) {
      this.store.issueCard(member.id).subscribe({
        next: () => this.feedback.success('Card issued successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async activateCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Activate Card', 'Activate this card?')) {
      this.store.activateCard(member.id).subscribe({
        next: () => this.feedback.success('Card activated successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async blockCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Block Card', 'Block this card?')) {
      this.store.blockCard(member.id).subscribe({
        next: () => this.feedback.success('Card blocked successfully'),
        error: (err) => this.feedback.error(err.error?.message),
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
    const match = config.bgColor.match(/bg-(\w+)-\d+/);
    return match ? `bg-${match[1]}-500` : 'bg-slate-400';
  }

  exportToCsv(): void {
    const data = this.store.members();
    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = [
      'Member #',
      'Name',
      'National ID',
      'Type',
      'Gender',
      'Age',
      'Status',
      'Policy ID',
      'Card Status',
    ];

    const csvContent = [
      headers.join(','),
      ...data.map((m) =>
        [
          `"${m.member_number}"`,
          `"${m.full_name || m.first_name + ' ' + m.last_name}"`,
          `"${m.national_id || ''}"`,
          `"${getLabelByValue(MEMBER_TYPES, m.member_type)}"`,
          `"${m.gender || ''}"`,
          m.age || calculateAge(m.date_of_birth ?? ''),
          `"${m.status}"`,
          `"${m.policy_id || ''}"`,
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
