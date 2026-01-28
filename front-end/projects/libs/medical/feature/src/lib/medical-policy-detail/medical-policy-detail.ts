// libs/medical/feature/src/lib/medical-policy-detail/medical-policy-detail.ts

import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

// Material Imports
import { MatTabsModule } from '@angular/material/tabs';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog } from '@angular/material/dialog';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatPaginatorModule } from '@angular/material/paginator';

// Domain Imports
import {
  PolicyStore,
  MemberStore,
  MemberFilters,
  EndorsementStore,
  EndorsementFilters,
  PlanListStore,
  AddonCatalogStore,
  Policy,
  Member,
  Endorsement,
  MedicalPlan,
  PlanAddon,
  PolicyAddon,
  POLICY_STATUSES,
  POLICY_TYPES,
  MEMBER_TYPES,
  getLabelByValue,
  formatCurrency,
  getStatusConfig,
  MemberUtilService,
  MEMBER_TYPE_STYLES,
  MEMBER_STATUS_STYLES,
  MEMBER_FILTER_OPTIONS,
  POLICY_UI_CONFIG,
  PREMIUM_INDICATORS,
  ENDORSEMENT_TYPE_STYLES,
  ENDORSEMENT_STATUS_STYLES,
  ENDORSEMENT_FILTER_OPTIONS,
  ENDORSEMENT_UI_CONFIG,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent, PermissionDirective } from 'shared';
import { MEDICAL_PERMISSIONS } from 'core-auth';
import { MedicalMemberDetailDialog } from '../dialogs/medical-member-detail-dialog/medical-member-detail-dialog';
import { MedicalMemberBenefitsDialog } from '../dialogs/medical-member-benefits-dialog/medical-member-benefits-dialog';
import {
  MedicalEndorsementDialog,
  EndorsementDialogMode,
} from '../dialogs/medical-endorsement-dialog/medical-endorsement-dialog';
import { PolicyDocumentsComponent } from '../components/policy-documents/policy-documents';

@Component({
  selector: 'lib-medical-policy-detail',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTabsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatPaginatorModule,
    PolicyDocumentsComponent,
    PermissionDirective,
  ],
  templateUrl: './medical-policy-detail.html',
})
export class MedicalPolicyDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly store = inject(PolicyStore);
  readonly memberStore = inject(MemberStore);
  readonly endorsementStore = inject(EndorsementStore);
  private readonly planStore = inject(PlanListStore);
  private readonly addonStore = inject(AddonCatalogStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);
  readonly memberUtil = inject(MemberUtilService);

  readonly permissions = MEDICAL_PERMISSIONS;

  // Route param
  policyId = signal<string>('');
  activeTabIndex = signal(0);

  // Policy data from store
  policy = computed(() => this.store.selectedPolicy());
  isLoading = computed(() => this.store.isLoading());

  // Tab configuration
  readonly tabs = [
    { label: 'Overview', icon: 'info', key: 'overview' },
    { label: 'Members', icon: 'group', key: 'members' },
    { label: 'Endorsements', icon: 'assignment', key: 'endorsements' },
    { label: 'Documents', icon: 'description', key: 'documents' },
  ];

  // Constants
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;
  readonly getStatusConfig = getStatusConfig;
  readonly MEMBER_TYPE_STYLES = MEMBER_TYPE_STYLES;
  readonly MEMBER_STATUS_STYLES = MEMBER_STATUS_STYLES;
  readonly MEMBER_FILTER_OPTIONS = MEMBER_FILTER_OPTIONS;
  readonly PREMIUM_INDICATORS = PREMIUM_INDICATORS;
  readonly ENDORSEMENT_TYPE_STYLES = ENDORSEMENT_TYPE_STYLES;
  readonly ENDORSEMENT_STATUS_STYLES = ENDORSEMENT_STATUS_STYLES;
  readonly ENDORSEMENT_FILTER_OPTIONS = ENDORSEMENT_FILTER_OPTIONS;

  // Member table columns - simplified, moved loadings/exclusions to "More" dialog
  readonly memberColumns = ['name', 'type', 'age', 'gender', 'status', 'premium', 'actions'];

  // Endorsement table columns
  readonly endorsementColumns = [
    'number',
    'type',
    'effective_date',
    'premium_adjustment',
    'status',
    'actions',
  ];
  readonly endorsementPageSizeOptions = ENDORSEMENT_UI_CONFIG.PAGE_SIZE_OPTIONS;

  // Member filtering (local UI state - client-side filtering)
  memberSearchTerm = signal('');
  memberTypeFilter = signal('all');
  memberStatusFilter = signal('all');

  // Page size options for member paginator
  readonly memberPageSizeOptions = [10, 25, 50];

  // Client-side filtered members
  filteredMembers = computed(() => {
    let members = this.memberStore.members();
    const search = this.memberSearchTerm().toLowerCase().trim();
    const typeFilter = this.memberTypeFilter();
    const statusFilter = this.memberStatusFilter();

    // Apply search filter (client-side)
    if (search) {
      members = members.filter(
        (m) =>
          m.member_number?.toLowerCase().includes(search) ||
          m.first_name?.toLowerCase().includes(search) ||
          m.last_name?.toLowerCase().includes(search) ||
          m.email?.toLowerCase().includes(search) ||
          m.employee_number?.toLowerCase().includes(search) ||
          `${m.first_name} ${m.last_name}`.toLowerCase().includes(search)
      );
    }

    // Apply type filter
    if (typeFilter && typeFilter !== 'all') {
      members = members.filter((m) => m.member_type === typeFilter);
    }

    // Apply status filter
    if (statusFilter && statusFilter !== 'all') {
      members = members.filter((m) => m.status === statusFilter);
    }

    return members;
  });

  // Member stats from all loaded members (not filtered)
  memberStats = computed(() => {
    const total = this.memberStore.totalItems();
    const members = this.memberStore.members();
    return {
      total: total || members.length,
      active: members.filter((m) => m.status === 'active').length,
      principals: members.filter((m) => m.member_type === 'principal').length,
      dependents: members.filter((m) => m.member_type !== 'principal').length,
      withLoadings: members.filter((m) => this.memberUtil.hasLoadings(m)).length,
      withExclusions: members.filter((m) => this.memberUtil.hasExclusions(m)).length,
    };
  });

  hasActiveMemberFilters = computed(
    () =>
      this.memberSearchTerm() !== '' ||
      this.memberTypeFilter() !== 'all' ||
      this.memberStatusFilter() !== 'all'
  );

  // Endorsement filtering (local UI state)
  endorsementTypeFilter = signal('all');
  endorsementStatusFilter = signal('all');

  hasActiveEndorsementFilters = computed(
    () => this.endorsementTypeFilter() !== 'all' || this.endorsementStatusFilter() !== 'all'
  );

  // Endorsement stats computed
  endorsementStats = computed(() => {
    const total = this.endorsementStore.totalItems();
    const endorsements = this.endorsementStore.endorsements();
    return {
      total: total || endorsements.length,
      pending: endorsements.filter((e) => e.status === 'pending').length,
      approved: endorsements.filter((e) => e.status === 'approved').length,
      processed: endorsements.filter((e) => e.status === 'processed').length,
    };
  });

  ngOnInit() {
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.policyId.set(id);
        this.loadPolicy(id);
        this.loadMembers(); // Load members with pagination
      }
    });

    // Handle query params for tab selection
    this.route.queryParamMap.subscribe((params) => {
      const tab = params.get('tab');
      if (tab) {
        const tabIndex = this.tabs.findIndex((t) => t.key === tab);
        if (tabIndex !== -1) {
          this.activeTabIndex.set(tabIndex);

          // Load endorsements if that's the initial tab
          if (tab === 'endorsements' && this.policyId()) {
            this.loadEndorsements();
          }
        }
      }
    });
  }

  // Build member filters object - only pagination params, filtering is client-side
  private buildMemberFilters(page?: number, perPage?: number): MemberFilters {
    return {
      policy_id: this.policyId(),
      per_page: perPage ?? (this.memberStore.pageSize() || 10),
      page: page ?? this.memberStore.currentPage(),
    };
  }

  // Load members from server (only on initial load or pagination change)
  loadMembers(page = 1, perPage = 10): void {
    if (!this.policyId()) return;
    this.memberStore.loadAll(this.buildMemberFilters(page, perPage));
  }

  loadPolicy(id: string) {
    this.store.loadOne(id).subscribe({
      error: () => {
        this.feedback.error('Failed to load policy details');
        this.router.navigate(['/policies']);
      },
    });
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    const tab = this.tabs[index];
    if (tab) {
      this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { tab: tab.key },
        queryParamsHandling: 'merge',
      });

      // Load endorsements when navigating to that tab
      if (tab.key === 'endorsements' && this.policyId()) {
        this.loadEndorsements();
      }
    }
  }

  // Build endorsement filters object from current UI state
  private buildEndorsementFilters(): EndorsementFilters {
    const filters: EndorsementFilters = {
      policy_id: this.policyId(),
      per_page: this.endorsementStore.pageSize() || ENDORSEMENT_UI_CONFIG.DEFAULT_PAGE_SIZE,
      page: this.endorsementStore.currentPage(),
    };

    if (this.endorsementTypeFilter() && this.endorsementTypeFilter() !== 'all') {
      filters.endorsement_type = this.endorsementTypeFilter();
    }
    if (this.endorsementStatusFilter() && this.endorsementStatusFilter() !== 'all') {
      filters.status = this.endorsementStatusFilter();
    }

    return filters;
  }

  // Load endorsements with current filters
  loadEndorsements(): void {
    if (!this.policyId()) return;
    this.endorsementStore.loadForPolicy(this.policyId(), this.buildEndorsementFilters());
  }

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

  // Data for endorsement dialog
  availablePlans = signal<MedicalPlan[]>([]);
  availableAddons = signal<PlanAddon[]>([]);
  policyAddons = signal<PolicyAddon[]>([]);

  // Open endorsement dialog for creating endorsements
  private openEndorsementDialogWithData(
    mode: EndorsementDialogMode,
    member?: Member,
    plans?: MedicalPlan[],
    addons?: PlanAddon[]
  ) {
    const policy = this.policy();
    if (!policy) return;

    const dialogRef = this.dialog.open(MedicalEndorsementDialog, {
      minWidth: '70vw',
      maxHeight: '90vh',
      data: {
        policy,
        mode,
        member, // For remove_member
        members: this.memberStore.members(), // For principal selection
        availablePlans: plans ?? [], // For upgrade/downgrade
        availableAddons: addons ?? [], // For add_addon
        policyAddons: policy.policy_addons ?? [], // For remove_addon
      },
      disableClose: true,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      // Endorsement was created, reload endorsements
      this.loadEndorsements();
      this.feedback.success('Endorsement created and is pending approval.');
    });
  }

  // Convenience method for add member endorsement
  openAddMemberEndorsement() {
    this.openEndorsementDialogWithData('add_member');
  }

  // Convenience method for remove member endorsement
  openRemoveMemberEndorsement(member: Member) {
    this.openEndorsementDialogWithData('remove_member', member);
  }

  // Convenience method for upgrade plan endorsement
  openUpgradePlanEndorsement() {
    const policy = this.policy();
    if (!policy?.scheme_id) {
      this.feedback.error('Policy does not have a scheme assigned');
      return;
    }

    // Load plans first, then open dialog
    this.planStore.loadByScheme(policy.scheme_id).subscribe({
      next: (res) => {
        this.openEndorsementDialogWithData('upgrade_plan', undefined, res.data || []);
      },
      error: () => {
        this.feedback.error('Failed to load available plans');
      },
    });
  }

  // Convenience method for downgrade plan endorsement
  openDowngradePlanEndorsement() {
    const policy = this.policy();
    if (!policy?.scheme_id) {
      this.feedback.error('Policy does not have a scheme assigned');
      return;
    }

    // Load plans first, then open dialog
    this.planStore.loadByScheme(policy.scheme_id).subscribe({
      next: (res) => {
        this.openEndorsementDialogWithData('downgrade_plan', undefined, res.data || []);
      },
      error: () => {
        this.feedback.error('Failed to load available plans');
      },
    });
  }

  // Convenience method for add addon endorsement
  openAddAddonEndorsement() {
    const policy = this.policy();
    if (!policy?.plan_id) return;

    // Load plan addons first, then open dialog
    this.addonStore.loadPlanAddonsObs(policy.plan_id).subscribe({
      next: (res) => {
        this.openEndorsementDialogWithData('add_addon', undefined, undefined, res.data || []);
      },
      error: () => {
        this.feedback.error('Failed to load available add-ons');
      },
    });
  }

  // Convenience method for remove addon endorsement
  openRemoveAddonEndorsement() {
    this.openEndorsementDialogWithData('remove_addon');
  }

  async activatePolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    if (
      await this.feedback.confirm('Activate Policy', `Activate policy ${policy.policy_number}?`)
    ) {
      this.store.activate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy activated');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate policy'),
      });
    }
  }

  async suspendPolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    const reason = 'Administrative suspension';
    if (await this.feedback.confirm('Suspend Policy', `Suspend policy ${policy.policy_number}?`)) {
      this.store.suspend(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy suspended');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to suspend policy'),
      });
    }
  }

  async reinstatePolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    if (
      await this.feedback.confirm('Reinstate Policy', `Reinstate policy ${policy.policy_number}?`)
    ) {
      this.store.reinstate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy reinstated');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reinstate policy'),
      });
    }
  }

  async cancelPolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    const reason = 'Policy cancellation';
    if (
      await this.feedback.confirm(
        'Cancel Policy',
        `Are you sure you want to cancel policy ${policy.policy_number}? This action cannot be undone.`
      )
    ) {
      this.store.cancel(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy cancelled');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to cancel policy'),
      });
    }
  }

  goBack() {
    this.router.navigate(['/policies']);
  }

  getMemberTypeBadgeClass(type: string): string {
    const style = MEMBER_TYPE_STYLES[type];
    return style ? `${style.bg} ${style.text}` : 'bg-slate-100 text-slate-700';
  }

  getMemberStatusBadgeClass(status: string): string {
    const style = MEMBER_STATUS_STYLES[status];
    return style ? `${style.bg} ${style.text}` : 'bg-slate-100 text-slate-600';
  }

  getMemberStatusDotClass(status: string): string {
    const style = MEMBER_STATUS_STYLES[status];
    return style?.dot || 'bg-slate-400';
  }

  // Member filter handlers - client-side only, no API calls
  onMemberSearchChange(value: string): void {
    this.memberSearchTerm.set(value);
  }

  onMemberTypeFilterChange(value: string): void {
    this.memberTypeFilter.set(value);
  }

  onMemberStatusFilterChange(value: string): void {
    this.memberStatusFilter.set(value);
  }

  clearMemberFilters(): void {
    this.memberSearchTerm.set('');
    this.memberTypeFilter.set('all');
    this.memberStatusFilter.set('all');
  }

  // Member pagination handler - this DOES call the API for new page data
  onMemberPageChange(event: { pageIndex: number; pageSize: number }): void {
    const newPage = event.pageIndex + 1; // MatPaginator is 0-indexed, API is 1-indexed
    this.loadMembers(newPage, event.pageSize);
  }

  // Open member detail dialog (More button)
  viewMemberDetails(member: Member): void {
    const policy = this.policy();
    this.dialog.open(MedicalMemberDetailDialog, {
      maxWidth: '70vw',
      data: {
        member,
        policyNumber: policy?.policy_number,
      },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });
  }

  // Open member benefits dialog
  viewMemberBenefits(member: Member): void {
    this.memberStore.loadBenefitBalances(member.id);
    this.dialog.open(MedicalMemberBenefitsDialog, {
      maxWidth: '90vw',
      data: {
        member_id: member.id,
        member_name: member.full_name,
        policy_number: member.member_number || '',
      },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });
  }

  // Helper to check if member has loadings or exclusions (for indicator display)
  memberHasTerms(member: Member): boolean {
    return this.memberUtil.hasLoadings(member) || this.memberUtil.hasExclusions(member);
  }

  // Helper to get terms summary for tooltip
  getMemberTermsSummary(member: Member): string {
    const loadings = this.memberUtil.getLoadingsCount(member);
    const exclusions = this.memberUtil.getExclusionsCount(member);
    const parts: string[] = [];
    if (loadings > 0) parts.push(`${loadings} loading(s)`);
    if (exclusions > 0) parts.push(`${exclusions} exclusion(s)`);
    return parts.join(', ');
  }

  // Endorsement helper methods
  getEndorsementTypeBadgeClass(type: string): string {
    const style = ENDORSEMENT_TYPE_STYLES[type];
    return style ? `${style.bg} ${style.text}` : 'bg-slate-100 text-slate-700';
  }

  getEndorsementTypeIcon(type: string): string {
    return ENDORSEMENT_TYPE_STYLES[type]?.icon || 'assignment';
  }

  getEndorsementTypeLabel(type: string): string {
    return ENDORSEMENT_TYPE_STYLES[type]?.label || type;
  }

  getEndorsementStatusBadgeClass(status: string): string {
    const style = ENDORSEMENT_STATUS_STYLES[status];
    return style ? `${style.bg} ${style.text}` : 'bg-slate-100 text-slate-600';
  }

  getEndorsementStatusDotClass(status: string): string {
    return ENDORSEMENT_STATUS_STYLES[status]?.dot || 'bg-slate-400';
  }

  getEndorsementStatusLabel(status: string): string {
    return ENDORSEMENT_STATUS_STYLES[status]?.label || status;
  }

  // Endorsement filter handlers
  onEndorsementTypeFilterChange(value: string): void {
    this.endorsementTypeFilter.set(value);
    this.endorsementStore.loadForPolicy(this.policyId(), {
      ...this.buildEndorsementFilters(),
      page: 1,
    });
  }

  onEndorsementStatusFilterChange(value: string): void {
    this.endorsementStatusFilter.set(value);
    this.endorsementStore.loadForPolicy(this.policyId(), {
      ...this.buildEndorsementFilters(),
      page: 1,
    });
  }

  clearEndorsementFilters(): void {
    this.endorsementTypeFilter.set('all');
    this.endorsementStatusFilter.set('all');
    this.endorsementStore.loadForPolicy(this.policyId(), {
      per_page: this.endorsementStore.pageSize() || ENDORSEMENT_UI_CONFIG.DEFAULT_PAGE_SIZE,
      page: 1,
    });
  }

  // Endorsement pagination handler
  onEndorsementPageChange(event: { pageIndex: number; pageSize: number }): void {
    const newPage = event.pageIndex + 1;
    const filters = this.buildEndorsementFilters();
    filters.page = newPage;
    filters.per_page = event.pageSize;
    this.endorsementStore.loadForPolicy(this.policyId(), filters);
  }

  // Endorsement workflow actions
  async approveEndorsement(endorsement: Endorsement): Promise<void> {
    if (
      await this.feedback.confirm(
        'Approve Endorsement',
        `Approve endorsement ${endorsement.endorsement_number}?`
      )
    ) {
      this.endorsementStore.approve(endorsement.id).subscribe({
        next: () => {
          this.feedback.success('Endorsement approved');
          this.loadEndorsements();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to approve endorsement'),
      });
    }
  }

  async processEndorsement(endorsement: Endorsement): Promise<void> {
    if (
      await this.feedback.confirm(
        'Process Endorsement',
        `Process endorsement ${endorsement.endorsement_number}? This will apply the changes to the policy.`
      )
    ) {
      this.endorsementStore.process(endorsement.id).subscribe({
        next: () => {
          this.feedback.success('Endorsement processed successfully');
          this.loadEndorsements();
          this.loadPolicy(this.policyId()); // Reload policy to reflect changes
          this.loadMembers(); // Reload members in case of member changes
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to process endorsement'),
      });
    }
  }

  async rejectEndorsement(endorsement: Endorsement): Promise<void> {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;

    this.endorsementStore.reject(endorsement.id, reason).subscribe({
      next: () => {
        this.feedback.success('Endorsement rejected');
        this.loadEndorsements();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reject endorsement'),
    });
  }

  async cancelEndorsement(endorsement: Endorsement): Promise<void> {
    if (
      await this.feedback.confirm(
        'Cancel Endorsement',
        `Cancel endorsement ${endorsement.endorsement_number}?`
      )
    ) {
      const reason = prompt('Optional reason for cancellation:') || undefined;
      this.endorsementStore.cancel(endorsement.id, reason).subscribe({
        next: () => {
          this.feedback.success('Endorsement cancelled');
          this.loadEndorsements();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to cancel endorsement'),
      });
    }
  }
}
