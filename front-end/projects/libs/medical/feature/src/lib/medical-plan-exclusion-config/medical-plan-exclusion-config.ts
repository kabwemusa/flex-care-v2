// libs/medical/feature/src/lib/medical-plan-exclusion-config/medical-plan-exclusion-config.ts

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
  PlanExclusion,
  PlanExclusionStore,
  PLAN_EXCLUSION_TYPES,
  getLabelByValue,
} from 'medical-data';

import { FeedbackService } from 'shared';
import { MedicalPlanExclusionDialog } from '../dialogs/medical-plan-exclusion-dialog/medical-plan-exclusion-dialog';

@Component({
  selector: 'lib-plan-exclusion-config',
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
  templateUrl: `./medical-plan-exclusion-config.html`,
})
export class MedicalPlanExclusionConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(PlanExclusionStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  exclusions = computed(() => this.store.exclusions());

  // Grouped by type
  absoluteExclusions = computed(() =>
    this.exclusions().filter((e) => e.exclusion_type === 'absolute')
  );
  conditionalExclusions = computed(() =>
    this.exclusions().filter((e) => e.exclusion_type === 'conditional')
  );
  timeLimitedExclusions = computed(() =>
    this.exclusions().filter((e) => e.exclusion_type === 'time_limited')
  );
  preExistingExclusions = computed(() =>
    this.exclusions().filter((e) => e.exclusion_type === 'pre_existing')
  );

  // Grouped by scope
  generalExclusions = computed(() => this.store.generalExclusions());
  benefitSpecificExclusions = computed(() => this.store.benefitSpecificExclusions());

  ngOnInit() {
    this.loadExclusions();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadExclusions();
    }
  }

  private loadExclusions() {
    if (this.planId) {
      this.store.loadForPlan(this.planId, { per_page: 100 }).subscribe();
    }
  }

  getExclusionTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      absolute: 'cancel',
      conditional: 'rule',
      time_limited: 'schedule',
      pre_existing: 'history',
    };
    return icons[type] || 'block';
  }

  getExclusionTypeClass(type: string): string {
    const classes: Record<string, string> = {
      absolute: 'bg-red-100 text-red-600',
      conditional: 'bg-amber-100 text-amber-600',
      time_limited: 'bg-blue-100 text-blue-600',
      pre_existing: 'bg-purple-100 text-purple-600',
    };
    return classes[type] || 'bg-slate-100 text-slate-600';
  }

  getExclusionTypeLabel(type: string): string {
    return getLabelByValue(PLAN_EXCLUSION_TYPES, type);
  }

  openAddDialog() {
    const dialogRef = this.dialog.open(MedicalPlanExclusionDialog, {
      maxWidth: '600px',
      maxHeight: '90vh',
      data: { planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.create(this.planId, result).subscribe({
        next: () => {
          this.feedback.success('Exclusion added successfully');
        },
        error: (err) => {
          this.feedback.error(err?.error?.message ?? 'Failed to add exclusion');
        },
      });
    });
  }

  openEditDialog(exclusion: PlanExclusion) {
    const dialogRef = this.dialog.open(MedicalPlanExclusionDialog, {
      maxWidth: '600px',
      maxHeight: '90vh',
      data: { exclusion, planId: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.update(exclusion.id, result).subscribe({
        next: () => {
          this.feedback.success('Exclusion updated successfully');
        },
        error: (err) => {
          this.feedback.error(err?.error?.message ?? 'Failed to update exclusion');
        },
      });
    });
  }

  async toggleStatus(exclusion: PlanExclusion) {
    const action = exclusion.is_active ? 'deactivate' : 'activate';

    const confirmed = await this.feedback.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} Exclusion?`,
      exclusion.is_active
        ? 'This exclusion will no longer be enforced.'
        : 'This exclusion will be enforced for this plan.'
    );

    if (!confirmed) return;

    this.store.activate(exclusion.id).subscribe({
      next: () => {
        this.feedback.success(`Exclusion ${action}d successfully`);
      },
      error: (err) => {
        this.feedback.error(err?.error?.message ?? `Failed to ${action} exclusion`);
      },
    });
  }

  async removeExclusion(exclusion: PlanExclusion) {
    const confirmed = await this.feedback.confirm(
      'Delete Exclusion?',
      `Are you sure you want to delete "${exclusion.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(exclusion.id).subscribe({
      next: () => {
        this.feedback.success('Exclusion deleted successfully');
      },
      error: (err) => {
        this.feedback.error(err?.error?.message ?? 'Failed to delete exclusion');
      },
    });
  }
}
