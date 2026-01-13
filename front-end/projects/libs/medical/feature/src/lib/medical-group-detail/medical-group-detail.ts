// libs/medical/feature/src/lib/medical-group-detail/medical-group-detail.ts

import { Component, OnInit, inject, signal, computed } from '@angular/core';
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

// Domain Imports
import {
  GroupStore,
  CorporateGroup,
  Application,
  ApplicationMember,
  getStatusConfig,
  getLabelByValue,
  MEMBER_TYPES,
  GENDERS,
  APPLICATION_STATUSES,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalCensusUploadDialog } from '../dialogs/medical-census-upload-dialog/medical-census-upload-dialog';
import { MedicalGroupQuoteDialog } from '../dialogs/medical-group-quote-dialog/medical-group-quote-dialog';

@Component({
  selector: 'lib-medical-group-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
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
    PageHeaderComponent,
  ],
  templateUrl: './medical-group-detail.html',
  styleUrls: ['./medical-group-detail.scss'],
})
export class MedicalGroupDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  readonly store = inject(GroupStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Data
  readonly group = this.store.selectedGroup;
  readonly loading = this.store.isLoading;

  // Filters for members table
  readonly memberSearchTerm = signal('');
  readonly memberTypeFilter = signal('');
  readonly applicationFilter = signal('');
  readonly planFilter = signal('');

  // Computed: All members from all applications
  readonly allMembers = computed(() => {
    const grp = this.group();
    if (!grp?.applications) return [];

    const members: (ApplicationMember & { application_id: string; application_code?: string })[] =
      [];

    grp.applications.forEach((app: Application) => {
      if (app.members) {
        app.members.forEach((member: ApplicationMember) => {
          members.push({
            ...member,
            application_id: app.id,
            application_code: app?.application_number || app.id,
          });
        });
      }
    });

    return members;
  });

  // Computed: Filtered members
  readonly filteredMembers = computed(() => {
    let members = this.allMembers();

    // Filter by search term
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

    // Filter by member type
    const memberType = this.memberTypeFilter();
    if (memberType) {
      members = members.filter((m) => m.member_type === memberType);
    }

    // Filter by application
    const appId = this.applicationFilter();
    if (appId) {
      members = members.filter((m) => m.application_id === appId);
    }

    // Filter by plan
    const planId = this.planFilter();
    if (planId) {
      const grp = this.group();
      const appsWithPlan =
        grp?.applications?.filter((app: Application) => app.plan_id === planId) || [];
      const appIds = appsWithPlan.map((app: Application) => app.id);
      members = members.filter((m) => appIds.includes(m.application_id));
    }

    return members;
  });

  // Member summary stats
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

  // Unique plans for filter dropdown
  readonly uniquePlans = computed(() => {
    const grp = this.group();
    if (!grp?.applications) return [];

    const planMap = new Map();
    grp.applications.forEach((app: Application) => {
      if (app.plan) {
        planMap.set(app.plan.id, {
          id: app.plan.id,
          name: app.plan.name,
          code: app.plan.code,
        });
      }
    });

    return Array.from(planMap.values());
  });

  // Policy count by plan for stats
  readonly policyCountByPlan = computed(() => {
    return this.group()?.stats?.policies_by_plan || [];
  });

  // Application count by plan for stats
  readonly applicationCountByPlan = computed(() => {
    return this.group()?.stats?.applications_by_plan || [];
  });

  // Helper to get count for a specific plan
  getApplicationCountForPlan(planId: string): number {
    const stats = this.applicationCountByPlan();
    const planStats = stats.find((p: any) => p.plan_id === planId);
    return planStats?.count || 0;
  }

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly GENDERS = GENDERS;
  readonly APPLICATION_STATUSES = APPLICATION_STATUSES;

  // Table columns
  readonly memberColumns = [
    'member_type',
    'name',
    'dob',
    'gender',
    'email',
    'employee_number',
    'application',
    'status',
  ];

  ngOnInit() {
    const groupId = this.route.snapshot.paramMap.get('id');
    if (groupId) {
      this.loadGroup(groupId);
    }
  }

  loadGroup(id: string) {
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

  getStatusConfig(status: string) {
    return getStatusConfig(APPLICATION_STATUSES as any, status);
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

  openGroupQuoteDialog() {
    const grp = this.group();
    if (!grp) return;

    // Get members from the selected application or all members
    const appId = this.applicationFilter();
    let members: any[] = [];

    if (appId) {
      const app = grp.applications?.find((a: Application) => a.id === appId);
      members = app?.members || [];
    } else {
      members = this.allMembers();
    }

    if (members.length === 0) {
      this.feedback.error('No members available. Please upload a census first.');
      return;
    }

    const dialogRef = this.dialog.open(MedicalGroupQuoteDialog, {
      maxWidth: '70vw',
      data: {
        group_id: grp.id,
        group_name: grp.name,
        members: members,
      },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result?.accepted) {
        this.feedback.success('Quote accepted! Ready for underwriting.');
        // Navigate to application detail or refresh
      }
    });
  }

  clearFilters() {
    this.memberSearchTerm.set('');
    this.memberTypeFilter.set('');
    this.applicationFilter.set('');
    this.planFilter.set('');
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

  backToGroups() {
    this.router.navigate(['/groups']);
  }
}
