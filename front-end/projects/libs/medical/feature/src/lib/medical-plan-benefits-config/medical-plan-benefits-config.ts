// libs/medical/feature/src/lib/medical-plan-benefits-config/medical-plan-benefits-config.ts
//
// POOL MODEL — hierarchical view
// • Shows ALL catalog benefits (parents + children) grouped by type
// • Parent rows are expandable to reveal sub-benefits
// • Each row has an independent slide-toggle; children are disabled until parent is enabled
// • "New Benefit" creates in the catalog and auto-enables on this plan

import {
  Component,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  inject,
  signal,
  computed,
} from '@angular/core';
import { CommonModule } from '@angular/common';

// Material
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSlideToggleModule, MatSlideToggleChange } from '@angular/material/slide-toggle';
import { MatChipsModule } from '@angular/material/chips';

// Domain
import {
  Benefit,
  PlanBenefit,
  BenefitStore,
  BENEFIT_TYPES,
  getLabelByValue,
} from 'medical-data';
import { AuthService, MEDICAL_PERMISSIONS } from 'core-auth';
import { FeedbackService } from 'shared';

// Dialogs
import { MedicalBenefitsCatalogDialog } from '../dialogs/medical-benefits-catalog-dialog/medical-benefits-catalog-dialog';
import { MedicalPlanBenefitsConfigDialog } from '../dialogs/medical-plan-benefits-config-dialog/medical-plan-benefits-config-dialog';

// ─── View-model ──────────────────────────────────────────────────────────────

export interface PoolItem {
  benefit: Benefit;
  planBenefit: PlanBenefit | null;
  isEnabled: boolean;
  children: PoolItem[];
}

interface BenefitGroup {
  typeValue: string;
  label: string;
  icon: string;
  items: PoolItem[];
  enabledCount: number; // parents + children
  totalCount: number;   // parents + children
}

// ─── Component ───────────────────────────────────────────────────────────────

@Component({
  selector: 'lib-plan-benefits-config',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatSlideToggleModule,
    MatChipsModule,
  ],
  templateUrl: './medical-plan-benefits-config.html',
})
export class MedicalPlanBenefitsConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(BenefitStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);
  private readonly authService = inject(AuthService);

  // ─── Permission signals ───────────────────────────────────────────────────
  readonly canConfigurePlan = computed(() =>
    this.authService.isAllowed(MEDICAL_PERMISSIONS.PLANS_CONFIGURE)
  );
  readonly canCreateBenefit = computed(() =>
    this.authService.isAllowed(MEDICAL_PERMISSIONS.BENEFITS_CREATE)
  );

  // ─── Filter signals ───────────────────────────────────────────────────────
  searchTerm = signal('');
  typeFilter = signal('');
  readonly benefitTypes = BENEFIT_TYPES;

  // ─── Expand state ─────────────────────────────────────────────────────────
  // Set of parent benefit IDs that are currently expanded
  private expandedIds = signal(new Set<string>());

  isExpanded(id: string): boolean {
    return this.expandedIds().has(id);
  }

  toggleExpand(id: string): void {
    this.expandedIds.update((set) => {
      const next = new Set(set);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  }

  // ─── Merged pool (hierarchical) ───────────────────────────────────────────
  private mergedPool = computed<PoolItem[]>(() => {
    const allBenefits = this.store.benefits();
    const planMap = new Map(this.store.planBenefits().map((pb) => [pb.benefit_id, pb]));

    // Separate root and child benefits
    const childrenByParent = new Map<string, Benefit[]>();
    const rootBenefits: Benefit[] = [];

    for (const b of allBenefits) {
      if (b.parent_id) {
        const list = childrenByParent.get(b.parent_id) ?? [];
        list.push(b);
        childrenByParent.set(b.parent_id, list);
      } else {
        rootBenefits.push(b);
      }
    }

    return rootBenefits.map((benefit) => {
      const childBenefits = (childrenByParent.get(benefit.id) ?? []).sort(
        (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)
      );
      return {
        benefit,
        planBenefit: planMap.get(benefit.id) ?? null,
        isEnabled: planMap.has(benefit.id),
        children: childBenefits.map((child) => ({
          benefit: child,
          planBenefit: planMap.get(child.id) ?? null,
          isEnabled: planMap.has(child.id),
          children: [],
        })),
      };
    });
  });

  // ─── Filtered pool (with child filtering for search) ─────────────────────
  filteredPool = computed<PoolItem[]>(() => {
    const search = this.searchTerm().toLowerCase().trim();
    const type = this.typeFilter();

    return this.mergedPool().reduce<PoolItem[]>((acc, item) => {
      // Apply type filter on parent
      if (type && item.benefit.benefit_type !== type) return acc;

      if (!search) {
        acc.push(item);
        return acc;
      }

      const parentMatch =
        item.benefit.name.toLowerCase().includes(search) ||
        item.benefit.code.toLowerCase().includes(search) ||
        (item.benefit.description?.toLowerCase().includes(search) ?? false);

      const matchingChildren = item.children.filter(
        (c) =>
          c.benefit.name.toLowerCase().includes(search) ||
          c.benefit.code.toLowerCase().includes(search) ||
          (c.benefit.description?.toLowerCase().includes(search) ?? false)
      );

      if (parentMatch) {
        // Parent matches — show it with all children
        acc.push(item);
      } else if (matchingChildren.length > 0) {
        // Only children match — show parent with matching children only
        acc.push({ ...item, children: matchingChildren });
      }

      return acc;
    }, []);
  });

  // ─── Grouped + counted ────────────────────────────────────────────────────
  groupedBenefits = computed<BenefitGroup[]>(() => {
    const grouped = new Map<string, PoolItem[]>();
    for (const item of this.filteredPool()) {
      const t = item.benefit.benefit_type;
      if (!grouped.has(t)) grouped.set(t, []);
      grouped.get(t)!.push(item);
    }

    return Array.from(grouped.entries()).map(([typeValue, items]) => {
      const enabledCount = items.reduce(
        (sum, i) =>
          sum +
          (i.isEnabled ? 1 : 0) +
          i.children.filter((c) => c.isEnabled).length,
        0
      );
      const totalCount = items.reduce((sum, i) => sum + 1 + i.children.length, 0);
      return {
        typeValue,
        label: getLabelByValue(BENEFIT_TYPES, typeValue) || typeValue,
        icon: BENEFIT_TYPES.find((t) => t.value === typeValue)?.icon ?? 'medical_services',
        items,
        enabledCount,
        totalCount,
      };
    });
  });

  // Overall counts across all groups (parents + children)
  totalCount = computed(() =>
    this.mergedPool().reduce((sum, i) => sum + 1 + i.children.length, 0)
  );
  enabledCount = computed(() =>
    this.mergedPool().reduce(
      (sum, i) =>
        sum + (i.isEnabled ? 1 : 0) + i.children.filter((c) => c.isEnabled).length,
      0
    )
  );

  // ─── Lifecycle ────────────────────────────────────────────────────────────
  ngOnInit() {
    this.loadData();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadData();
    }
  }

  private loadData() {
    if (!this.planId) return;
    this.store.loadBenefits();
    this.store.loadPlanBenefits(this.planId);
  }

  // ─── Display helpers ──────────────────────────────────────────────────────
  getLimitSummary(pb: PlanBenefit): string {
    if (pb.display_value) return pb.display_value;
    if (pb.limit_type === 'unlimited') return 'Unlimited';
    if (pb.limit_amount) {
      const freq = pb.limit_frequency ? `/${this.freqShort(pb.limit_frequency)}` : '';
      return `ZMW ${pb.limit_amount.toLocaleString()}${freq}`;
    }
    if (pb.limit_count) return `${pb.limit_count} visit${pb.limit_count > 1 ? 's' : ''}`;
    if (pb.limit_days) return `${pb.limit_days} day${pb.limit_days > 1 ? 's' : ''}`;
    return 'Limits not set';
  }

  private freqShort(freq: string): string {
    const map: Record<string, string> = {
      per_annum: 'yr',
      per_claim: 'claim',
      per_visit: 'visit',
      per_condition: 'condition',
      lifetime: 'lifetime',
    };
    return map[freq] ?? freq;
  }

  getLimitTypeLabel(pb: PlanBenefit): string {
    if (!pb.limit_type) return '';
    const map: Record<string, string> = {
      unlimited: 'Unlimited',
      monetary: 'Amount',
      count: 'Visits',
      days: 'Days',
      combined: 'Combined',
    };
    return map[pb.limit_type] ?? pb.limit_type;
  }

  getLimitBadgeClass(pb: PlanBenefit): string {
    const map: Record<string, string> = {
      unlimited: 'bg-green-50 text-green-700 border-green-200',
      monetary: 'bg-blue-50 text-blue-700 border-blue-200',
      count: 'bg-amber-50 text-amber-700 border-amber-200',
      days: 'bg-purple-50 text-purple-700 border-purple-200',
      combined: 'bg-slate-100 text-slate-700 border-slate-200',
    };
    return map[pb.limit_type ?? ''] ?? 'bg-slate-100 text-slate-600 border-slate-200';
  }

  // How many children of this parent are enabled
  enabledChildCount(item: PoolItem): number {
    return item.children.filter((c) => c.isEnabled).length;
  }

  // ─── Filter actions ───────────────────────────────────────────────────────
  onSearch(value: string) {
    this.searchTerm.set(value);
  }

  onTypeFilter(value: string) {
    this.typeFilter.set(value);
  }

  clearFilters() {
    this.searchTerm.set('');
    this.typeFilter.set('');
  }

  // ─── Toggle ───────────────────────────────────────────────────────────────
  async onToggle(item: PoolItem, event: MatSlideToggleChange, parent?: PoolItem) {
    if (!this.canConfigurePlan()) {
      event.source.checked = item.isEnabled;
      this.feedback.error('You do not have permission to configure this plan');
      return;
    }

    if (event.checked) {
      this.store
        .addBenefitToPlan(this.planId, { benefit_id: item.benefit.id, is_covered: true })
        .subscribe({
          next: () => this.feedback.success(`"${item.benefit.name}" enabled`),
          error: (err) => {
            event.source.checked = false;
            this.feedback.error(err?.error?.message ?? 'Failed to enable benefit');
          },
        });
    } else {
      // If disabling a parent that has enabled children, warn
      const enabledKids = item.children.filter((c) => c.isEnabled).length;
      const childWarning =
        enabledKids > 0
          ? ` This will also disable its ${enabledKids} enabled sub-benefit${enabledKids > 1 ? 's' : ''}.`
          : '';

      const confirmed = await this.feedback.confirm(
        'Disable Benefit?',
        `Remove "${item.benefit.name}" from this plan? Members will lose access to this benefit.${childWarning}`
      );
      if (!confirmed) {
        event.source.checked = true;
        return;
      }
      this.store.removeBenefitFromPlan(item.planBenefit!.id).subscribe({
        next: () => this.feedback.success(`"${item.benefit.name}" removed from this plan`),
        error: (err) => {
          event.source.checked = true;
          this.feedback.error(err?.error?.message ?? 'Failed to disable benefit');
        },
      });
    }
  }

  // ─── Configure ────────────────────────────────────────────────────────────
  openConfigure(item: PoolItem) {
    if (!item.planBenefit) return;

    const ref = this.dialog.open(MedicalPlanBenefitsConfigDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { planBenefit: item.planBenefit, planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    ref.afterClosed().subscribe((result) => {
      if (!result) return;
      this.store.updatePlanBenefit(item.planBenefit!.id, result).subscribe({
        next: () => this.feedback.success('Benefit limits updated'),
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update'),
      });
    });
  }

  // ─── Create new (catalog + auto-enable) ──────────────────────────────────
  openCreateDialog() {
    if (!this.canCreateBenefit()) return;

    const ref = this.dialog.open(MedicalBenefitsCatalogDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    ref.afterClosed().subscribe((result) => {
      if (!result) return;
      this.store.createBenefit(result).subscribe({
        next: (res) => {
          this.store
            .addBenefitToPlan(this.planId, { benefit_id: res.data.id, is_covered: true })
            .subscribe({
              next: () =>
                this.feedback.success(`"${res.data.name}" created and enabled on this plan`),
              error: () =>
                this.feedback.success(`"${res.data.name}" created — enable it from the list below`),
            });
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to create benefit'),
      });
    });
  }
}
