// libs/medical/ui/src/lib/medical-plan-rate-card-config/medical-plan-rate-card-config.ts
// Embedded in Plan Detail page — shows all rate cards for ONE plan.

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
import { RouterLink } from '@angular/router';

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
  RateCard,
  RateCardListStore,
  PREMIUM_FREQUENCIES,
  getLabelByValue,
} from 'medical-data';
import { MedicalRateCardListDialog } from '../dialogs/medical-rate-cards-list-dialog/medical-rate-cards-list-dialog';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-plan-rate-card-config',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
  ],
  templateUrl: `./medical-plan-rate-card-config.html`,
})
export class MedicalPlanRateCardConfig implements OnInit, OnChanges {
  @Input({ required: true }) planId!: string;

  readonly store = inject(RateCardListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // All rate cards for this plan — active one first, then drafts, then inactive
  rateCards = computed(() => {
    const all = this.store.rateCards();
    return [
      ...all.filter((rc) => rc.is_active),
      ...all.filter((rc) => !rc.is_active && rc.is_draft),
      ...all.filter((rc) => !rc.is_active && !rc.is_draft),
    ];
  });

  ngOnInit() {
    this.loadRateCards();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['planId'] && !changes['planId'].firstChange) {
      this.loadRateCards();
    }
  }

  private loadRateCards() {
    if (this.planId) {
      this.store.loadByPlan(this.planId).subscribe();
    }
  }

  getFrequencyLabel(value: string): string {
    return getLabelByValue(PREMIUM_FREQUENCIES, value);
  }

  // ── Create / Edit ─────────────────────────────────────────────────────────

  openCreateDialog(rateCard?: RateCard) {
    const dialogRef = this.dialog.open(MedicalRateCardListDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: rateCard ? { ...rateCard } : { plan_id: this.planId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = rateCard
        ? this.store.update(rateCard.id, result)
        : this.store.create({ ...result, plan_id: this.planId });

      request$.subscribe({
        next: () => {
          this.feedback.success(`Rate card ${rateCard ? 'updated' : 'created'} successfully`);
          this.loadRateCards();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rate card'),
      });
    });
  }

  // ── Activate ──────────────────────────────────────────────────────────────

  async activateRateCard(rc: RateCard) {
    const confirmed = await this.feedback.confirm(
      'Activate Rate Card?',
      'This will make this the active rate card for the plan. The currently active rate card will be deactivated.'
    );
    if (!confirmed) return;

    this.store.activate(rc.id).subscribe({
      next: () => {
        this.feedback.success(`"${rc.name}" is now the active rate card`);
        this.loadRateCards();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate'),
    });
  }

  // ── Deactivate ────────────────────────────────────────────────────────────

  async deactivateRateCard(rc: RateCard) {
    const confirmed = await this.feedback.confirm(
      'Deactivate Rate Card?',
      `"${rc.name}" will no longer be used for new quotes and applications. Members already on this plan are not affected.`
    );
    if (!confirmed) return;

    this.store.activate(rc.id).subscribe({
      next: () => {
        this.feedback.success(`"${rc.name}" has been deactivated`);
        this.loadRateCards();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to deactivate'),
    });
  }

  // ── Clone ─────────────────────────────────────────────────────────────────

  async cloneRateCard(rc: RateCard) {
    const confirmed = await this.feedback.confirm(
      'Clone Rate Card?',
      'Create a draft copy with all entries and configurations.'
    );
    if (!confirmed) return;

    this.store.clone(rc.id).subscribe({
      next: () => {
        this.feedback.success('Rate card cloned as draft');
        this.loadRateCards();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to clone'),
    });
  }

  // ── Delete ────────────────────────────────────────────────────────────────

  async deleteRateCard(rc: RateCard) {
    if (rc.is_active) {
      this.feedback.error('Cannot delete an active rate card. Deactivate it first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Rate Card?',
      `Are you sure you want to delete "${rc.name}"? This action cannot be undone.`
    );
    if (!confirmed) return;

    this.store.delete(rc.id).subscribe({
      next: () => {
        this.feedback.success('Rate card deleted');
        this.loadRateCards();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }
}
