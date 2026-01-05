// libs/medical/ui/src/lib/plans/medical-plan-detail.component.ts

import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
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

// Domain Imports
import {
  MedicalPlan,
  PlanListStore,
  PLAN_TYPES,
  NETWORK_TYPES,
  MEMBER_TYPES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalPlanListDialog } from '../dialogs/medical-plan-list-dialog/medical-plan-list-dialog';

// Child Components (UI)
import { MedicalPlanBenefitsConfig } from '../medical-plan-benefits-config/medical-plan-benefits-config';
import { MedicalPlanRateCardConfig } from '../medical-plan-rate-card-config/medical-plan-rate-card-config';
import { MedicalPlanAddonConfig } from '../medical-plan-addon-config/medical-plan-addon-config';
import { MedicalPlanExclusionConfig } from '../medical-plan-exclusion-config/medical-plan-exclusion-config';

@Component({
  selector: 'lib-medical-plan-detail',
  standalone: true,
  imports: [
    CommonModule,
    MatTabsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MedicalPlanBenefitsConfig,
    MedicalPlanRateCardConfig,
    MedicalPlanAddonConfig,
    MedicalPlanExclusionConfig,
  ],
  templateUrl: './medical-plan-detail.html',
})
export class MedicalPlanDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly store = inject(PlanListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Route param
  planId = signal<string>('');
  activeTabIndex = signal(0);

  // Plan data from store
  plan = computed(() => this.store.selectedPlan());
  isLoading = computed(() => this.store.isLoading());

  // Tier configuration for badges
  readonly tierConfig: Record<number, { label: string; class: string; bgClass: string }> = {
    1: {
      label: 'Platinum',
      class: 'bg-violet-50 text-violet-700 border-violet-200',
      bgClass: 'bg-gradient-to-r from-violet-600 to-violet-700',
    },
    2: {
      label: 'Gold',
      class: 'bg-amber-50 text-amber-700 border-amber-200',
      bgClass: 'bg-gradient-to-r from-amber-500 to-amber-600',
    },
    3: {
      label: 'Silver',
      class: 'bg-slate-100 text-slate-700 border-slate-300',
      bgClass: 'bg-gradient-to-r from-slate-400 to-slate-500',
    },
    4: {
      label: 'Bronze',
      class: 'bg-orange-50 text-orange-700 border-orange-200',
      bgClass: 'bg-gradient-to-r from-orange-600 to-orange-700',
    },
    5: {
      label: 'Basic',
      class: 'bg-gray-100 text-gray-600 border-gray-200',
      bgClass: 'bg-slate-200',
    },
  };

  // Tab configuration
  readonly tabs = [
    { label: 'Overview', icon: 'info', key: 'overview' },
    { label: 'Benefits', icon: 'medical_services', key: 'benefits' },
    { label: 'Rate Cards', icon: 'payments', key: 'rate-cards' },
    { label: 'Addons', icon: 'extension', key: 'addons' },
    { label: 'Exclusions', icon: 'block', key: 'exclusions' },
  ];

  ngOnInit() {
    // Get plan ID from route params
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.planId.set(id);
        this.loadPlan(id);
      }
    });

    // Check for tab query param
    this.route.queryParamMap.subscribe((params) => {
      const tab = params.get('tab');
      if (tab) {
        const index = this.tabs.findIndex((t) => t.key === tab);
        if (index >= 0) {
          this.activeTabIndex.set(index);
        }
      }
    });
  }

  private loadPlan(id: string) {
    this.store.loadOne(id).subscribe({
      error: (err) => {
        this.feedback.error('Failed to load plan details');
        this.router.navigate(['/plans']);
      },
    });
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    // Update URL with tab key (without navigation)
    const tab = this.tabs[index].key;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { tab },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
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

  // Navigation
  goBack() {
    this.router.navigate(['/plans']);
  }

  // Actions
  openEditDialog() {
    const plan = this.plan();
    if (!plan) return;

    const dialogRef = this.dialog.open(MedicalPlanListDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { ...plan },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.update(plan.id, result).subscribe({
        next: () => {
          this.feedback.success('Plan updated successfully');
          this.loadPlan(plan.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update plan'),
      });
    });
  }

  async toggleStatus() {
    const plan = this.plan();
    if (!plan) return;

    const action = plan.is_active ? 'deactivate' : 'activate';
    const confirmed = await this.feedback.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} Plan?`,
      plan.is_active
        ? 'This plan will no longer be available for new policies.'
        : 'This plan will become available for policy creation.'
    );

    if (!confirmed) return;

    this.store.activate(plan.id).subscribe({
      next: () => {
        this.feedback.success(`Plan ${action}d successfully`);
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action} plan`),
    });
  }

  exportToPdf() {
    const plan = this.plan();
    if (!plan) return;

    this.store.exportPdf(plan.id).subscribe({
      next: (response) => {
        // For now, we'll create a JSON file with the plan data
        // In production, the backend will return a PDF blob
        const dataStr = JSON.stringify(response.data, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        const url = window.URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${plan.code}_plan_details.json`;
        link.click();
        window.URL.revokeObjectURL(url);

        this.feedback.success('Plan details exported successfully');
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to export plan'),
    });
  }

  async clonePlan() {
    const plan = this.plan();
    if (!plan) return;

    const confirmed = await this.feedback.confirm(
      'Clone Plan?',
      'This will create a copy of this plan with all its benefits and configuration.'
    );

    if (!confirmed) return;

    this.store.clone(plan.id).subscribe({
      next: (res) => {
        this.feedback.success('Plan cloned successfully');
        if (res.data?.id) {
          this.router.navigate(['/plans', res.data.id]);
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to clone plan'),
    });
  }
}
