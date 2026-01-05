// libs/medical/ui/src/lib/addons/plan-addons-config.ts
// This component is EMBEDDED in Plan Detail page, showing addons configured for ONE plan

import {
  Component,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  inject,
  computed,
} from '@angular/core';
import { CommonModule } from '@angular/common';

// Material Imports
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';

// Domain Imports
import {
  PlanAddon,
  AddonCatalogStore,
  ADDON_AVAILABILITY,
  getLabelByValue,
} from 'medical-data';

import { FeedbackService } from 'shared';
import { MedicalAddAddonPlanDialog } from '../dialogs/medical-add-addon-plan-dialog/medical-add-addon-plan-dialog';
import { PlanAddonConfigDialog } from '../dialogs/medical-plan-addon-config-dialog/medical-plan-addon-config-dialog';

@Component({
  selector: 'lib-plan-addon-config',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
  ],
  templateUrl: `./medical-plan-addon-config.html`,
})
export class MedicalPlanAddonConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(AddonCatalogStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  planAddons = computed(() => this.store.planAddons());

  // Grouped by availability
  includedAddons = computed(() => this.planAddons().filter((pa) => pa.availability === 'included'));
  mandatoryAddons = computed(() =>
    this.planAddons().filter((pa) => pa.availability === 'mandatory')
  );
  optionalAddons = computed(() => this.planAddons().filter((pa) => pa.availability === 'optional'));
  conditionalAddons = computed(() =>
    this.planAddons().filter((pa) => pa.availability === 'conditional')
  );

  ngOnInit() {
    this.loadPlanAddons();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadPlanAddons();
    }
  }

  private loadPlanAddons() {
    if (this.planId) {
      this.store.loadPlanAddons(this.planId);
    }
  }

  getAddonTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      optional: 'add_circle',
      mandatory: 'verified',
      conditional: 'rule',
    };
    return icons[type] || 'extension';
  }

  getAddonTypeClass(type: string): string {
    const classes: Record<string, string> = {
      optional: 'bg-purple-100 text-purple-600',
      mandatory: 'bg-blue-100 text-blue-600',
      conditional: 'bg-teal-100 text-teal-600',
    };
    return classes[type] || 'bg-slate-100 text-slate-600';
  }

  getAvailabilityLabel(value: string): string {
    return getLabelByValue(ADDON_AVAILABILITY, value);
  }

  getAvailabilityClass(availability: string): string {
    const classes: Record<string, string> = {
      included: 'bg-green-100 text-green-700',
      mandatory: 'bg-blue-100 text-blue-700',
      optional: 'bg-purple-100 text-purple-700',
      conditional: 'bg-amber-100 text-amber-700',
    };
    return classes[availability] || 'bg-slate-100 text-slate-600';
  }

  openAddDialog() {
    // Get IDs of addons already configured
    const existingAddonIds = this.planAddons().map((pa) => pa.addon_id);

    const dialogRef = this.dialog.open(MedicalAddAddonPlanDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { planId: this.planId, existingAddonIds },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadPlanAddons();
      }
    });
  }

  openConfigDialog(planAddon: PlanAddon) {
    const dialogRef = this.dialog.open(PlanAddonConfigDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: { planAddon, planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.updatePlanAddon(planAddon.id, result).subscribe({
        next: () => {
          this.feedback.success('Addon configuration updated');
          this.loadPlanAddons();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update'),
      });
    });
  }

  async toggleStatus(planAddon: PlanAddon) {
    const action = planAddon.is_active ? 'disable' : 'enable';

    this.store.updatePlanAddon(planAddon.id, { is_active: !planAddon.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Addon ${action}d`);
        this.loadPlanAddons();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action}`),
    });
  }

  async removeAddon(planAddon: PlanAddon) {
    const confirmed = await this.feedback.confirm(
      'Remove Addon?',
      `Remove "${planAddon.addon?.name}" from this plan?`
    );
    if (!confirmed) return;

    this.store.removePlanAddon(planAddon.id).subscribe({
      next: () => {
        this.feedback.success('Addon removed from plan');
        this.loadPlanAddons();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to remove'),
    });
  }
}
