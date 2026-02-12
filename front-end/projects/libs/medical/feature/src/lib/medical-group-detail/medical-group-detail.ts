// libs/medical/feature/src/lib/medical-group-detail/medical-group-detail.ts

import { Component, OnInit, inject, signal, computed, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableModule } from '@angular/material/table';
import { MatTabsModule } from '@angular/material/tabs';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatMenuModule } from '@angular/material/menu';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';

// Domain Imports
import {
  GroupStore,
  ApplicationStore,
  Application,
  ApplicationMember,
  getLabelByValue,
  MEMBER_TYPES,
  GENDERS,
  APPLICATION_STATUSES,
  MEMBER_TYPE_STYLES,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalCensusUploadDialog } from '../dialogs/medical-census-upload-dialog/medical-census-upload-dialog';

@Component({
  selector: 'lib-medical-group-detail',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatTabsModule,
    MatIconModule,
    MatButtonModule,
    MatChipsModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatCardModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatMenuModule,
    MatTooltipModule,
    MatPaginatorModule,
  ],
  templateUrl: './medical-group-detail.html',
  styleUrls: ['./medical-group-detail.scss'],
})
export class MedicalGroupDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  readonly store = inject(GroupStore);
  private readonly applicationStore = inject(ApplicationStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Data
  readonly group = this.store.selectedGroup;
  readonly loading = this.store.isLoading;
  readonly members = this.store.members;
  readonly membersPagination = this.store.membersPagination;

  // Filters for members table
  readonly memberSearchTerm = signal('');
  readonly memberTypeFilter = signal('');
  readonly applicationFilter = signal('');

  // Members from current page (with display info)
  readonly allMembers = computed(() => {
    const grp = this.group();
    const members = this.members();
    const appMap = new Map((grp?.applications ?? []).map((app) => [app.id, app]));

    return members.map((member) => {
      const app = appMap.get(member.application_id);
      return {
        ...member,
        application_code: app?.application_number || app?.id,
      };
    });
  });

  // Client-side filtered members (search + member type on current page)
  readonly filteredMembers = computed(() => {
    let members = this.allMembers();

    const search = this.memberSearchTerm().toLowerCase();
    if (search) {
      members = members.filter(
        (m) =>
          m.first_name?.toLowerCase().includes(search) ||
          m.last_name?.toLowerCase().includes(search) ||
          m.email?.toLowerCase().includes(search) ||
          m.employee_number?.toLowerCase().includes(search)
      );
    }

    const memberType = this.memberTypeFilter();
    if (memberType) {
      members = members.filter((m) => m.member_type === memberType);
    }

    return members;
  });

  // Pagination
  readonly memberPageSize = signal(10);
  readonly memberPageSizeOptions = [10, 25, 50];

  // Total members from backend stats (across ALL applications)
  readonly totalGroupMembers = computed(() => {
    return this.group()?.stats?.total_application_members ?? 0;
  });

  // Effect: load members when group or filter changes
  readonly loadMembersEffect = effect(() => {
    const grp = this.group();
    const selectedAppId = this.applicationFilter();
    const perPage = this.memberPageSize();

    if (!grp) return;

    // Default to 'all' (no application filter)
    if (!selectedAppId) {
      this.applicationFilter.set('all');
      return;
    }

    const appFilter = selectedAppId === 'all' ? undefined : selectedAppId;
    this.store.loadGroupMembers(grp.id, 1, perPage, { application_id: appFilter });
  });

  // Member summary stats (from current page)
  readonly memberStats = computed(() => {
    const members = this.allMembers();
    return {
      total: members.length,
      principals: members.filter((m) => m.member_type === 'principal').length,
      spouses: members.filter((m) => m.member_type === 'spouse').length,
      children: members.filter((m) => m.member_type === 'child').length,
      parents: members.filter((m) => m.member_type === 'parent').length,
      other: members.filter((m) => m.member_type === 'other').length,
    };
  });

  // Applications list for dropdown
  readonly applications = computed(() => this.group()?.applications || []);

  // Application cards — totals across all plans
  readonly calculatingPremium = signal(false);

  readonly totalApplicationMembers = computed(() =>
    this.applications().reduce((sum, app) => sum + (app.member_count || 0), 0)
  );

  readonly totalApplicationPremium = computed(() =>
    this.applications().reduce((sum, app) => sum + (app.gross_premium || 0), 0)
  );

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly GENDERS = GENDERS;
  readonly APPLICATION_STATUSES = APPLICATION_STATUSES;
  readonly MEMBER_TYPE_STYLES = MEMBER_TYPE_STYLES;

  // Table columns
  readonly memberColumns = ['name', 'member_type', 'dob', 'gender', 'email', 'status'];

  ngOnInit() {
    const groupId = this.route.snapshot.paramMap.get('id');
    if (groupId) {
      this.loadGroup(groupId);
    }
  }

  loadGroup(id: string) {
    this.store.clearMembers();
    this.applicationFilter.set('all');
    this.store.loadOne(id).subscribe({
      error: (err) => {
        this.feedback.error('Failed to load group details');
        this.router.navigate([`/groups`]);
      },
    });
  }

  getMemberTypeLabel(value: string): string {
    return getLabelByValue(MEMBER_TYPES, value);
  }

  getGenderLabel(value: string): string {
    return getLabelByValue(GENDERS, value);
  }

  private readonly statusColorMap: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-700',
    blue: 'bg-blue-100 text-blue-700',
    indigo: 'bg-indigo-100 text-indigo-700',
    amber: 'bg-amber-100 text-amber-700',
    green: 'bg-green-100 text-green-700',
    red: 'bg-red-100 text-red-700',
    orange: 'bg-orange-100 text-orange-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    teal: 'bg-teal-100 text-teal-700',
    gray: 'bg-gray-100 text-gray-700',
    yellow: 'bg-yellow-100 text-yellow-700',
  };

  getStatusConfig(status: string): { label: string; classes: string; icon: string } {
    const config = APPLICATION_STATUSES.find((s) => s.value === status);
    if (!config) return { label: status, classes: 'bg-slate-100 text-slate-700', icon: 'help' };
    return {
      label: config.label,
      classes: this.statusColorMap[config.color] || 'bg-slate-100 text-slate-700',
      icon: config.icon,
    };
  }

  getMemberTypeBadgeClass(type: string): string {
    const style = MEMBER_TYPE_STYLES[type];
    return style ? `${style.bg} ${style.text}` : 'bg-slate-100 text-slate-700';
  }

  getInitials(member: ApplicationMember): string {
    return (member.first_name?.[0] || '') + (member.last_name?.[0] || '');
  }

  formatDate(date: string | Date | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  calculateAge(dob: string | Date | null): string {
    if (!dob) return '-';
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age.toString();
  }

  openCensusUploadDialog() {
    const grp = this.group();
    if (!grp) return;

    const dialogRef = this.dialog.open(MedicalCensusUploadDialog, {
      maxWidth: '70vw',
      data: {
        group_id: grp.id,
        group_name: grp.name,
      },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.feedback.success('Census uploaded and application created successfully!');
        this.loadGroup(grp.id); // Reload to show new application
      }
    });
  }

  clearFilters() {
    this.memberSearchTerm.set('');
    this.memberTypeFilter.set('');
  }

  onMemberPage(event: PageEvent) {
    const grp = this.group();
    if (!grp) return;
    this.memberPageSize.set(event.pageSize);
    const appFilter = this.applicationFilter();
    const applicationId = appFilter === 'all' ? undefined : appFilter;
    this.store.loadGroupMembers(grp.id, event.pageIndex + 1, event.pageSize, { application_id: applicationId });
  }

  exportMembers() {
    const members = this.filteredMembers();
    if (members.length === 0) {
      this.feedback.error('No members to export');
      return;
    }

    // Create CSV content
    const headers = [
      'Application',
      'Member Type',
      'First Name',
      'Last Name',
      'Date of Birth',
      'Age',
      'Gender',
      'Email',
      'Phone',
      'Employee Number',
      'Department',
      'Job Title',
    ];

    const csvContent = [
      headers.join(','),
      ...members.map((m) =>
        [
          m.application_code || '',
          this.getMemberTypeLabel(m.member_type || ''),
          m.first_name || '',
          m.last_name || '',
          m.date_of_birth || '',
          this.calculateAge(m.date_of_birth || null),
          m.gender || '',
          m.email || '',
          m.phone || '',
          m.employee_number || '',
          m.department || '',
          m.job_title || '',
        ].join(',')
      ),
    ].join('\n');

    // Download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    const grp = this.group();
    const filename = `${grp?.name.replace(/\s+/g, '_')}_members_${
      new Date().toISOString().split('T')[0]
    }.csv`;

    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    this.feedback.success(`Exported ${members.length} members to CSV`);
  }

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency: 'ZMW',
      minimumFractionDigits: 2,
    }).format(amount);
  }

  calculateAppPremium(app: Application) {
    this.calculatingPremium.set(true);
    this.applicationStore.calculatePremium(app.id).subscribe({
      next: () => {
        this.calculatingPremium.set(false);
        this.feedback.success('Premium calculated');
        const grp = this.group();
        if (grp) this.loadGroup(grp.id);
      },
      error: (err: any) => {
        this.calculatingPremium.set(false);
        this.feedback.error(err?.error?.message || 'Failed to calculate premium');
      },
    });
  }

  viewApplication(app: Application) {
    this.router.navigate(['/applications', app.id]);
  }

  backToGroups() {
    this.router.navigate(['/groups']);
  }
}
