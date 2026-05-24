// libs/medical/feature/src/lib/medical-plan-addon-config/medical-plan-addon-config.ts
//
// POOL MODEL: loads the full global add-on catalog + plan associations.
// Every catalog add-on appears in the list; a slide-toggle enables/disables
// it for THIS plan.  "New Add-on" creates in the catalog and auto-enables here.

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
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatSlideToggleModule, MatSlideToggleChange } from '@angular/material/slide-toggle';
import { MatChipsModule } from '@angular/material/chips';

// Domain
import {
  Addon,
  PlanAddon,
  AddonCatalogStore,
  ADDON_TYPES,
  ADDON_AVAILABILITY,
  getLabelByValue,
} from 'medical-data';
import { AuthService, MEDICAL_PERMISSIONS } from 'core-auth';
import { FeedbackService } from 'shared';

// Dialogs (all reused — no new dialogs)
import { MedicalAddonCatalogDialog } from '../dialogs/medical-addon-catalog-dialog/medical-addon-catalog-dialog';
import { PlanAddonConfigDialog } from '../dialogs/medical-plan-addon-config-dialog/medical-plan-addon-config-dialog';

// ─── Local view-model ────────────────────────────────────────────────────────

interface PoolItem {
  addon: Addon;
  planAddon: PlanAddon | null;
  isEnabled: boolean;
}

// ─── Component ───────────────────────────────────────────────────────────────

@Component({
  selector: 'lib-plan-addon-config',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatSlideToggleModule,
    MatChipsModule,
  ],
  templateUrl: './medical-plan-addon-config.html',
})
export class MedicalPlanAddonConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(AddonCatalogStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);
  private readonly authService = inject(AuthService);

  // ─── Filter signals ───────────────────────────────────────────────────────
  searchTerm = signal('');
  typeFilter = signal('');

  readonly addonTypes = ADDON_TYPES;
  readonly canConfigurePlan = computed(() =>
    this.authService.isAllowed(MEDICAL_PERMISSIONS.PLANS_CONFIGURE)
  );
  readonly canCreateAddon = computed(() =>
    this.authService.isAllowed(MEDICAL_PERMISSIONS.ADDONS_CREATE)
  );

  // ─── Merged pool ──────────────────────────────────────────────────────────
  private mergedPool = computed<PoolItem[]>(() => {
    const catalog = this.store.addons();
    const planMap = new Map(this.store.planAddons().map((pa) => [pa.addon_id, pa]));
    return catalog.map((addon) => ({
      addon,
      planAddon: planMap.get(addon.id) ?? null,
      isEnabled: planMap.has(addon.id) && (planMap.get(addon.id)?.is_active !== false),
    }));
  });

  filteredPool = computed<PoolItem[]>(() => {
    const search = this.searchTerm().toLowerCase().trim();
    const type = this.typeFilter();
    return this.mergedPool().filter((item) => {
      const matchSearch =
        !search ||
        item.addon.name.toLowerCase().includes(search) ||
        item.addon.code.toLowerCase().includes(search) ||
        (item.addon.description?.toLowerCase().includes(search) ?? false);
      const matchType = !type || item.addon.addon_type === type;
      return matchSearch && matchType;
    });
  });

  totalCount = computed(() => this.mergedPool().length);
  enabledCount = computed(() => this.mergedPool().filter((i) => i.isEnabled).length);

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
    this.store.loadAll();
    this.store.loadPlanAddons(this.planId);
  }

  // ─── Display helpers ──────────────────────────────────────────────────────
  getTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      optional: 'add_circle',
      mandatory: 'verified',
      conditional: 'rule',
    };
    return icons[type] ?? 'extension';
  }

  getTypeClass(type: string): string {
    const classes: Record<string, string> = {
      optional: 'bg-purple-100 text-purple-600',
      mandatory: 'bg-blue-100 text-blue-600',
      conditional: 'bg-teal-100 text-teal-600',
    };
    return classes[type] ?? 'bg-slate-100 text-slate-600';
  }

  getAvailabilityLabel(val: string): string {
    return getLabelByValue(ADDON_AVAILABILITY, val);
  }

  getAvailabilityClass(val: string): string {
    const classes: Record<string, string> = {
      included: 'bg-green-50 text-green-700 border-green-200',
      mandatory: 'bg-blue-50 text-blue-700 border-blue-200',
      optional: 'bg-purple-50 text-purple-700 border-purple-200',
      conditional: 'bg-amber-50 text-amber-700 border-amber-200',
    };
    return classes[val] ?? 'bg-slate-50 text-slate-600 border-slate-200';
  }

  getPricingLabel(addon: Addon): string {
    if (addon.pricing_type === 'fixed') return `ZMW ${(addon.amount ?? 0).toLocaleString()}`;
    if (addon.pricing_type === 'per_member') return `ZMW ${(addon.amount ?? 0).toLocaleString()}/member`;
    if (addon.pricing_type === 'percentage') return `${addon.percentage ?? 0}% of premium`;
    return 'Pricing not set';
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
  async onToggle(item: PoolItem, event: MatSlideToggleChange) {
    if (!this.canConfigurePlan()) {
      event.source.checked = item.isEnabled;
      this.feedback.error('You do not have permission to configure this plan');
      return;
    }

    if (event.checked) {
      // Enable: add to plan with default availability 'optional'
      this.store
        .configurePlanAddon(this.planId, {
          addon_id: item.addon.id,
          is_active: true,
          availability: 'optional',
        })
        .subscribe({
          next: () =>
            this.feedback.success(
              `"${item.addon.name}" enabled — use ⚙ to set availability type`
            ),
          error: (err) => {
            event.source.checked = false;
            this.feedback.error(err?.error?.message ?? 'Failed to enable add-on');
          },
        });
    } else {
      // Disable: confirm then remove
      const confirmed = await this.feedback.confirm(
        'Disable Add-on?',
        `Remove "${item.addon.name}" from this plan? Members will no longer have access to it.`
      );
      if (!confirmed) {
        event.source.checked = true;
        return;
      }
      this.store.removePlanAddon(item.planAddon!.id).subscribe({
        next: () => this.feedback.success(`"${item.addon.name}" removed from this plan`),
        error: (err) => {
          event.source.checked = true;
          this.feedback.error(err?.error?.message ?? 'Failed to disable add-on');
        },
      });
    }
  }

  // ─── Configure ────────────────────────────────────────────────────────────
  openConfigure(item: PoolItem) {
    if (!item.planAddon) return;

    const ref = this.dialog.open(PlanAddonConfigDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { planAddon: item.planAddon, planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    ref.afterClosed().subscribe((result) => {
      if (!result) return;
      this.store.updatePlanAddon(item.planAddon!.id, result).subscribe({
        next: () => this.feedback.success('Add-on configuration updated'),
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update'),
      });
    });
  }

  // ─── Create new (catalog + auto-enable) ──────────────────────────────────
  openCreateDialog() {
    if (!this.canCreateAddon()) return;

    const ref = this.dialog.open(MedicalAddonCatalogDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    ref.afterClosed().subscribe((result) => {
      if (!result) return;
      this.store.create(result).subscribe({
        next: (res) => {
          // Auto-enable the new add-on on this plan
          this.store
            .configurePlanAddon(this.planId, {
              addon_id: res.data.id,
              is_active: true,
              availability: 'optional',
            })
            .subscribe({
              next: () =>
                this.feedback.success(`"${res.data.name}" created and enabled on this plan`),
              error: () =>
                this.feedback.success(
                  `"${res.data.name}" created — enable it from the list below`
                ),
            });
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to create add-on'),
      });
    });
  }
}
