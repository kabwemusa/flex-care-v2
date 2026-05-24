// libs/medical/feature/src/lib/medical-plan-discount-config/medical-plan-discount-config.ts
// Embedded in Plan Detail — manages discount & loading-adjustment rules scoped to this plan

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
  DiscountRule,
  DiscountListStore,
  DISCOUNT_APPLICATION,
  getLabelByValue,
} from 'medical-data';
import { MedicalDiscountDialog } from '../dialogs/medical-discount-dialog/medical-discount-dialog';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-plan-discount-config',
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
  templateUrl: './medical-plan-discount-config.html',
})
export class MedicalPlanDiscountConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(DiscountListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Grouped by adjustment_type
  discountRules = computed(() =>
    this.store.discountRules().filter((r) => r.adjustment_type === 'discount')
  );
  loadingRules = computed(() =>
    this.store.discountRules().filter((r) => r.adjustment_type === 'loading')
  );
  totalRules = computed(() => this.store.discountRules().length);

  ngOnInit() {
    this.loadRules();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadRules();
    }
  }

  private loadRules() {
    if (this.planId) {
      this.store.loadRules({ plan_id: this.planId });
    }
  }

  // ─── Display Helpers ─────────────────────────────────────────────────────

  getValueLabel(rule: DiscountRule): string {
    return rule.value_type === 'percentage'
      ? `${rule.value}%`
      : `ZMW ${(rule.value ?? 0).toLocaleString()}`;
  }

  getMethodLabel(method: string): string {
    return getLabelByValue(DISCOUNT_APPLICATION, method);
  }

  getMethodClass(method: string): string {
    return method === 'automatic'
      ? 'bg-blue-50 text-blue-700 border border-blue-200'
      : 'bg-amber-50 text-amber-700 border border-amber-200';
  }

  getDiscountIconClass(): string {
    return 'bg-green-100 text-green-600';
  }

  getLoadingIconClass(): string {
    return 'bg-orange-100 text-orange-600';
  }

  // ─── CRUD Actions ─────────────────────────────────────────────────────────

  openDialog(rule?: DiscountRule) {
    // For creation, pre-fill plan_id so the rule is scoped to this plan
    const dialogData = rule ? { ...rule } : { plan_id: this.planId };

    const dialogRef = this.dialog.open(MedicalDiscountDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: dialogData,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const req$ = rule
        ? this.store.updateRule(rule.id, result)
        : this.store.createRule({ ...result, plan_id: this.planId });

      req$.subscribe({
        next: () => {
          this.feedback.success(`Rule ${rule ? 'updated' : 'created'} successfully`);
          this.loadRules();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rule'),
      });
    });
  }

  async toggleStatus(rule: DiscountRule) {
    const action = rule.is_active ? 'deactivate' : 'activate';
    this.store.updateRule(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Rule ${action}d`);
        this.loadRules();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action} rule`),
    });
  }

  async deleteRule(rule: DiscountRule) {
    const confirmed = await this.feedback.confirm(
      'Delete Rule?',
      `Delete "${rule.name}"? This cannot be undone.`
    );
    if (!confirmed) return;

    this.store.deleteRule(rule.id).subscribe({
      next: () => this.feedback.success('Rule deleted'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }
}
