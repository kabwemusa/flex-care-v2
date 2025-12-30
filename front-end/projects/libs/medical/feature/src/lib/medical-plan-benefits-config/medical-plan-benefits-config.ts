// libs/medical/ui/src/lib/benefits/plan-benefits-config.component.ts

import {
  Component,
  OnInit,
  Input,
  inject,
  computed,
  signal,
  OnChanges,
  SimpleChanges,
} from '@angular/core';
import { CommonModule } from '@angular/common';

// Material Imports
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

// Domain Imports
import {
  PlanBenefit,
  Benefit,
  BenefitStore,
  BENEFIT_TYPES,
  LIMIT_TYPES,
  getLabelByValue,
} from 'medical-data';
import { MedicalPlanBenefitsConfigDialog } from '../dialogs/medical-plan-benefits-config-dialog/medical-plan-benefits-config-dialog';
import { MedicalAddPlanBenefitsDialog } from '../dialogs/medical-add-plan-benefits-dialog/medical-add-plan-benefits-dialog';
import { FeedbackService } from 'shared';

interface BenefitGroup {
  category: string;
  categoryId: string;
  icon: string;
  color: string;
  benefits: PlanBenefit[];
}

@Component({
  selector: 'lib-plan-benefits-config',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
    MatMenuModule,
    MatDividerModule,
    MatExpansionModule,
    MatChipsModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './medical-plan-benefits-config.html',
})
export class MedicalPlanBenefitsConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;
  @Input() readonly = false;

  readonly store = inject(BenefitStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // State
  expandedCategories = signal<Set<string>>(new Set());

  // Computed
  benefitGroups = computed<BenefitGroup[]>(() => {
    const planBenefits = this.store.planBenefits();
    const categories = this.store.categories();

    // Group by category
    const grouped = new Map<string, PlanBenefit[]>();

    planBenefits.forEach((pb) => {
      const categoryId = pb.benefit?.category_id || 'uncategorized';
      if (!grouped.has(categoryId)) {
        grouped.set(categoryId, []);
      }
      // Only include root benefits (non sub-benefits)
      if (!pb.parent_plan_benefit_id) {
        grouped.get(categoryId)!.push(pb);
      }
    });

    // Convert to array with category info
    return Array.from(grouped.entries()).map(([categoryId, benefits]) => {
      const category = categories.find((c) => c.id === categoryId);
      return {
        category: category?.name || 'Uncategorized',
        categoryId,
        icon: category?.icon || 'folder',
        color: category?.color || '#6b7280',
        benefits: benefits.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)),
      };
    });
  });

  totalBenefits = computed(() => this.store.planBenefits().length);
  coveredBenefits = computed(() => this.store.planBenefits().filter((pb) => pb.is_covered).length);

  ngOnInit() {
    this.loadData();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadData();
    }
  }

  private loadData() {
    if (this.planId) {
      this.store.loadCategories();
      this.store.loadPlanBenefits(this.planId);
    }
  }

  // Expansion
  toggleCategory(categoryId: string) {
    const expanded = this.expandedCategories();
    const newSet = new Set(expanded);
    if (newSet.has(categoryId)) {
      newSet.delete(categoryId);
    } else {
      newSet.add(categoryId);
    }
    this.expandedCategories.set(newSet);
  }

  isCategoryExpanded(categoryId: string): boolean {
    return this.expandedCategories().has(categoryId);
  }

  expandAll() {
    const allIds = this.benefitGroups().map((g) => g.categoryId);
    this.expandedCategories.set(new Set(allIds));
  }

  collapseAll() {
    this.expandedCategories.set(new Set());
  }

  // Labels
  getBenefitTypeIcon(value: string): string {
    return BENEFIT_TYPES.find((t) => t.value === value)?.icon || 'medical_services';
  }

  getLimitTypeLabel(value: string | undefined): string {
    if (!value) return 'Not set';
    return getLabelByValue(LIMIT_TYPES, value);
  }

  formatLimitDisplay(pb: PlanBenefit): string {
    if (pb.display_value) return pb.display_value;

    const type = pb.limit_type || pb.benefit?.default_limit_type;
    if (type === 'unlimited') return 'Unlimited';

    if (pb.limit_amount) {
      return `ZMW ${pb.limit_amount.toLocaleString()}`;
    }
    if (pb.limit_count) {
      return `${pb.limit_count} ${pb.limit_frequency === 'per_visit' ? 'visits' : 'times'}`;
    }
    if (pb.limit_days) {
      return `${pb.limit_days} days`;
    }

    return 'Not configured';
  }

  getSubBenefits(planBenefitId: string): PlanBenefit[] {
    return this.store.planBenefits().filter((pb) => pb.parent_plan_benefit_id === planBenefitId);
  }

  // Dialogs
  openAddBenefitsDialog() {
    const dialogRef = this.dialog.open(MedicalAddPlanBenefitsDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: {
        planId: this.planId,
        existingBenefitIds: this.store.planBenefits().map((pb) => pb.benefit_id),
      },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result?.added) {
        this.feedback.success(`Added ${result.added} benefits to plan`);
        this.store.loadPlanBenefits(this.planId);
      }
    });
  }

  openConfigureDialog(planBenefit: PlanBenefit) {
    const dialogRef = this.dialog.open(MedicalPlanBenefitsConfigDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { planBenefit, planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.updatePlanBenefit(planBenefit.id, result).subscribe({
        next: () => this.feedback.success('Benefit configuration updated'),
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update benefit'),
      });
    });
  }

  addSubBenefit(parentPlanBenefit: PlanBenefit) {
    const dialogRef = this.dialog.open(MedicalAddPlanBenefitsDialog, {
      width: '700px',
      maxHeight: '90vh',
      data: {
        planId: this.planId,
        parentPlanBenefitId: parentPlanBenefit.id,
        parentBenefitId: parentPlanBenefit.benefit_id,
        categoryId: parentPlanBenefit.benefit?.category_id,
        existingBenefitIds: this.store.planBenefits().map((pb) => pb.benefit_id),
      },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result?.added) {
        this.feedback.success(`Added ${result.added} sub-benefits`);
        this.store.loadPlanBenefits(this.planId);
      }
    });
  }

  async toggleCoverage(planBenefit: PlanBenefit) {
    const newValue = !planBenefit.is_covered;

    this.store.updatePlanBenefit(planBenefit.id, { is_covered: newValue }).subscribe({
      next: () => this.feedback.success(`Benefit ${newValue ? 'enabled' : 'disabled'}`),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update benefit'),
    });
  }

  async removeBenefit(planBenefit: PlanBenefit) {
    const subBenefits = this.getSubBenefits(planBenefit.id);
    const message =
      subBenefits.length > 0
        ? `This will also remove ${subBenefits.length} sub-benefits. Continue?`
        : 'Remove this benefit from the plan?';

    const confirmed = await this.feedback.confirm('Remove Benefit?', message);

    if (!confirmed) return;

    this.store.removeBenefitFromPlan(planBenefit.id).subscribe({
      next: () => this.feedback.success('Benefit removed from plan'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to remove benefit'),
    });
  }
}
