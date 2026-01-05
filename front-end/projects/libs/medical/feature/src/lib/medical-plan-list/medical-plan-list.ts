// libs/medical/ui/src/lib/plans/medical-plans.component.ts

import {
  Component,
  OnInit,
  AfterViewInit,
  ViewChild,
  inject,
  effect,
  computed,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

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
import { MatDrawer, MatSidenavModule } from '@angular/material/sidenav';

// Domain/Shared Imports
import {
  MedicalPlan,
  PlanListStore,
  SchemeListStore,
  PLAN_TYPES,
  NETWORK_TYPES,
  MEMBER_TYPES,
  getLabelByValue,
} from 'medical-data';
import { MedicalPlanListDialog } from '../dialogs/medical-plan-list-dialog/medical-plan-list-dialog';
import { MedicalQuickQuoteDialog } from '../dialogs/medical-quick-quote-dialog/medical-quick-quote-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-plans',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
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
    MatSidenavModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-plan-list.html',
})
export class MedicalPlanList implements OnInit, AfterViewInit {
  readonly planStore = inject(PlanListStore);
  readonly schemeStore = inject(SchemeListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = ['status', 'name', 'scheme', 'type', 'tier', 'benefits', 'actions'];
  dataSource = new MatTableDataSource<MedicalPlan>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  selectedScheme = signal<string>('');
  selectedType = signal<string>('');
  searchQuery = signal<string>('');

  // Detail View
  selectedPlan = signal<MedicalPlan | null>(null);

  // Constants
  planTypes = PLAN_TYPES;
  networkTypes = NETWORK_TYPES;
  memberTypes = MEMBER_TYPES;

  // Tier configuration
  readonly tierConfig: Record<number, { label: string; class: string }> = {
    1: { label: 'Platinum', class: 'bg-violet-50 text-violet-700 border-violet-200' },
    2: { label: 'Gold', class: 'bg-amber-50 text-amber-700 border-amber-200' },
    3: { label: 'Silver', class: 'bg-slate-100 text-slate-700 border-slate-300' },
    4: { label: 'Bronze', class: 'bg-orange-50 text-orange-700 border-orange-200' },
    5: { label: 'Basic', class: 'bg-gray-100 text-gray-600 border-gray-200' },
  };

  // Computed KPIs
  totalPlans = computed(() => this.planStore.plans()?.length || 0);
  activePlans = computed(() => this.planStore.plans()?.filter((p) => p.is_active).length || 0);
  avgBenefits = computed(() => {
    const plans = this.planStore.plans() || [];
    if (plans.length === 0) return 0;
    const total = plans.reduce((sum, p) => sum + (p.plan_benefits_count || 0), 0);
    return Math.round(total / plans.length);
  });

  constructor() {
    effect(() => {
      const plans = this.planStore.plans();
      if (Array.isArray(plans)) {
        this.dataSource.data = plans;
      }
    });
  }

  ngOnInit() {
    this.planStore.loadAll();
    this.schemeStore.loadAll();

    // Custom filter predicate
    this.dataSource.filterPredicate = (data: MedicalPlan, filter: string) => {
      const searchStr = filter.toLowerCase();
      const name = data.name?.toLowerCase() || '';
      const code = data.code?.toLowerCase() || '';
      const schemeName = data.scheme?.name?.toLowerCase() || '';

      return name.includes(searchStr) || code.includes(searchStr) || schemeName.includes(searchStr);
    };
  }

  ngAfterViewInit() {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.searchQuery.set(filterValue);
    this.dataSource.filter = filterValue.trim().toLowerCase();

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  filterByScheme(schemeId: string) {
    this.selectedScheme.set(schemeId);
    this.applyFilters();
  }

  filterByType(type: string) {
    this.selectedType.set(type);
    this.applyFilters();
  }

  private applyFilters() {
    let filtered = this.planStore.plans() || [];

    if (this.selectedScheme()) {
      filtered = filtered.filter((p) => p.scheme_id === this.selectedScheme());
    }

    if (this.selectedType()) {
      filtered = filtered.filter((p) => p.plan_type === this.selectedType());
    }

    this.dataSource.data = filtered;
  }

  clearFilters() {
    this.selectedScheme.set('');
    this.selectedType.set('');
    this.searchQuery.set('');
    this.dataSource.filter = '';
    this.dataSource.data = this.planStore.plans() || [];
  }

  // Label helpers
  getPlanTypeLabel(value: string): string {
    return getLabelByValue(PLAN_TYPES, value);
  }

  getNetworkTypeLabel(value: string): string {
    return getLabelByValue(NETWORK_TYPES, value);
  }

  getMemberTypeLabel(value: string): string {
    return getLabelByValue(MEMBER_TYPES, value);
  }

  getTierLabel(tier: number): string {
    return this.tierConfig[tier]?.label || `Tier ${tier}`;
  }

  getTierBadgeClass(tier: number): string {
    return this.tierConfig[tier]?.class || 'bg-slate-100 text-slate-600 border-slate-200';
  }

  // Detail Drawer
  viewDetails(plan: MedicalPlan) {
    this.planStore.loadOne(plan.id).subscribe({
      next: () => {
        this.selectedPlan.set(this.planStore.selectedPlan());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load plan details'),
    });
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedPlan.set(null);
  }

  // Dialog Actions
  openCreateDialog(plan?: MedicalPlan) {
    const dialogRef = this.dialog.open(MedicalPlanListDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: plan ? { ...plan } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = plan
        ? this.planStore.update(plan.id, result)
        : this.planStore.create(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Plan ${plan ? 'updated' : 'created'} successfully`);
          if (this.selectedPlan()?.id === plan?.id) {
            this.selectedPlan.set({ ...this.selectedPlan()!, ...result });
          }
        },
        error: (err) =>
          this.feedback.error(
            err?.error?.message ?? `Failed to ${plan ? 'update' : 'create'} plan`
          ),
      });
    });
  }

  async toggleStatus(plan: MedicalPlan) {
    const action = plan.is_active ? 'Deactivate' : 'Activate';

    const confirmed = await this.feedback.confirm(
      `${action} Plan?`,
      plan.is_active
        ? 'This plan will no longer be available for new enrollments.'
        : 'This plan will become available for new enrollments.'
    );

    if (!confirmed) return;

    this.planStore.update(plan.id, { is_active: !plan.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Plan ${action.toLowerCase()}d successfully`);
        // Update drawer if open
        if (this.selectedPlan()?.id === plan.id) {
          this.selectedPlan.set({ ...this.selectedPlan()!, is_active: !plan.is_active });
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update plan status'),
    });
  }

  async clonePlan(plan: MedicalPlan) {
    const confirmed = await this.feedback.confirm(
      'Clone Plan?',
      `This will create a copy of "${plan.name}" with all its configurations.`
    );

    if (!confirmed) return;

    // Create a copy without id and with modified name
    const clonedPlan: Partial<MedicalPlan> = {
      ...plan,
      name: `${plan.name} (Copy)`,
      code: undefined, // Let backend generate new code
      is_active: false, // Start as inactive
    };
    delete (clonedPlan as any).id;
    delete (clonedPlan as any).created_at;
    delete (clonedPlan as any).updated_at;

    this.planStore.create(clonedPlan).subscribe({
      next: () => this.feedback.success('Plan cloned successfully'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to clone plan'),
    });
  }

  openQuickQuote(plan: MedicalPlan) {
    this.dialog.open(MedicalQuickQuoteDialog, {
      width: '800px',
      maxHeight: '90vh',
      data: { planId: plan.id, plan },
      disableClose: true,
    });
  }

  async deletePlan(plan: MedicalPlan) {
    if (plan.is_active) {
      this.feedback.error('Cannot delete an active plan. Please deactivate it first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Plan?',
      `Are you sure you want to delete "${plan.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.planStore.delete(plan.id).subscribe({
      next: () => {
        this.feedback.success('Plan deleted successfully');
        if (this.selectedPlan()?.id === plan.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete plan'),
    });
  }

  exportToCsv() {
    const dataToExport = this.dataSource.filteredData.length
      ? this.dataSource.filteredData
      : this.planStore.plans() || [];

    if (dataToExport.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = ['Code', 'Name', 'Scheme', 'Type', 'Tier', 'Benefits', 'Status'];
    const rows = dataToExport.map((p) => [
      p.code,
      `"${p.name}"`,
      p.scheme?.name || '',
      this.getPlanTypeLabel(p.plan_type),
      this.getTierLabel(p.tier_level ?? 0),
      p.plan_benefits_count || 0,
      p.is_active ? 'Active' : 'Inactive',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `medical_plans_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
