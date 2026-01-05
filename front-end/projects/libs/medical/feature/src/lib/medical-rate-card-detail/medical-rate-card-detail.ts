// libs/medical/ui/src/lib/rate-cards/medical-rate-card-detail.ts
// Rate Card Detail page with Entries (age bands) and Tiers (family sizes) management

import { Component, OnInit, inject, signal, computed, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatDialog } from '@angular/material/dialog';
import { MatTabsModule } from '@angular/material/tabs';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';

// Domain Imports
import {
  RateCard,
  RateCardEntry,
  RateCardTier,
  RateCardListStore,
  PREMIUM_FREQUENCIES,
  PREMIUM_BASES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { RateCardEntryDialog } from '../dialogs/medical-rate-card-entry-dialog/medical-rate-card-entry-dialog';
import { RateCardTierDialog } from '../dialogs/medical-rate-card-tier-dialog/medical-rate-card-tier-dialog';
import { RateCardBulkImportDialog } from '../dialogs/medical-rate-card-bulk-dialog/medical-rate-card-bulk-dialog';

@Component({
  selector: 'lib-medical-rate-card-detail',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTabsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatChipsModule,
  ],
  templateUrl: './medical-rate-card-detail.html',
})
export class MedicalRateCardDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  readonly store = inject(RateCardListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  @ViewChild('entriesPaginator') entriesPaginator!: MatPaginator;
  @ViewChild('entriesSort') entriesSort!: MatSort;
  @ViewChild('tiersPaginator') tiersPaginator!: MatPaginator;
  @ViewChild('tiersSort') tiersSort!: MatSort;

  // Route param
  rateCardId = signal<string>('');
  activeTabIndex = signal(0);

  // Data
  rateCard = computed(() => this.store.selectedRateCard());
  isLoading = computed(() => this.store.isLoading());

  // Entries table
  entriesDataSource = new MatTableDataSource<RateCardEntry>([]);
  entriesColumns = ['age_band', 'gender', 'region', 'base_premium', 'actions'];

  // Tiers table
  tiersDataSource = new MatTableDataSource<RateCardTier>([]);
  tiersColumns = ['tier_name', 'member_range', 'tier_premium', 'extra_member', 'actions'];

  ngOnInit() {
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.rateCardId.set(id);
        this.loadRateCard(id);
      }
    });

    this.route.queryParamMap.subscribe((params) => {
      const tab = params.get('tab');
      if (tab === 'tiers') {
        this.activeTabIndex.set(1);
      }
    });
  }

  private loadRateCard(id: string) {
    this.store.loadOne(id).subscribe({
      next: () => {
        const rc = this.store.selectedRateCard();
        if (rc) {
          this.entriesDataSource.data = rc.entries || [];
          this.tiersDataSource.data = rc.tiers || [];

          setTimeout(() => {
            if (this.entriesPaginator) {
              this.entriesDataSource.paginator = this.entriesPaginator;
            }
            if (this.entriesSort) {
              this.entriesDataSource.sort = this.entriesSort;
            }
            if (this.tiersPaginator) {
              this.tiersDataSource.paginator = this.tiersPaginator;
            }
            if (this.tiersSort) {
              this.tiersDataSource.sort = this.tiersSort;
            }
          });
        }
      },
      error: () => {
        this.feedback.error('Failed to load rate card');
        this.router.navigate(['/rate-cards']);
      },
    });
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    const tab = index === 1 ? 'tiers' : 'entries';
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { tab },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  // Navigation
  goBack() {
    const rc = this.rateCard();
    if (rc?.plan_id) {
      this.router.navigate(['/plans', rc.plan_id], { queryParams: { tab: 'rate-cards' } });
    } else {
      this.router.navigate(['/rate-cards']);
    }
  }

  // Label helpers
  getFrequencyLabel(value: string): string {
    return getLabelByValue(PREMIUM_FREQUENCIES, value);
  }

  getBasisLabel(value: string): string {
    return getLabelByValue(PREMIUM_BASES, value);
  }

  getAgeBandLabel(entry: RateCardEntry): string {
    if (entry.age_band_label) return entry.age_band_label;
    if (entry.max_age === 999 || entry.max_age === 150) {
      return `${entry.min_age}+`;
    }
    return `${entry.min_age} - ${entry.max_age}`;
  }

  getGenderLabel(gender?: string): string {
    if (!gender) return 'All';
    return gender === 'M' ? 'Male' : 'Female';
  }

  getMemberRangeLabel(tier: RateCardTier): string {
    if (tier.member_range_label) return tier.member_range_label;
    if (!tier.max_members) return `${tier.min_members}+`;
    if (tier.min_members === tier.max_members) return `${tier.min_members}`;
    return `${tier.min_members} - ${tier.max_members}`;
  }

  // ==================== ENTRIES ====================

  openEntryDialog(entry?: RateCardEntry) {
    const rc = this.rateCard();
    if (!rc) return;

    const dialogRef = this.dialog.open(RateCardEntryDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: entry ? { ...entry } : { rate_card_id: rc.id },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = entry
        ? this.store.updateEntry(entry.id, result)
        : this.store.addEntry(rc.id, result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Entry ${entry ? 'updated' : 'added'} successfully`);
          this.loadRateCard(rc.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save entry'),
      });
    });
  }

  async deleteEntry(entry: RateCardEntry) {
    const confirmed = await this.feedback.confirm(
      'Delete Entry?',
      `Remove the ${this.getAgeBandLabel(entry)} age band?`
    );
    if (!confirmed) return;

    this.store.deleteEntry(entry.id).subscribe({
      next: () => {
        this.feedback.success('Entry deleted');
        this.loadRateCard(this.rateCardId());
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }

  openBulkImportDialog() {
    const rc = this.rateCard();
    if (!rc) return;

    const dialogRef = this.dialog.open(RateCardBulkImportDialog, {
      maxWidth: '80vw',
      maxHeight: '90vh',
      data: { rateCardId: rc.id },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadRateCard(rc.id);
      }
    });
  }

  // ==================== TIERS ====================

  openTierDialog(tier?: RateCardTier) {
    const rc = this.rateCard();
    if (!rc) return;

    const dialogRef = this.dialog.open(RateCardTierDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: tier ? { ...tier } : { rate_card_id: rc.id },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = tier
        ? this.store.updateTier(tier.id, result)
        : this.store.addTier(rc.id, result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Tier ${tier ? 'updated' : 'added'} successfully`);
          this.loadRateCard(rc.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save tier'),
      });
    });
  }

  async deleteTier(tier: RateCardTier) {
    const confirmed = await this.feedback.confirm(
      'Delete Tier?',
      `Remove the "${tier.tier_name}" tier?`
    );
    if (!confirmed) return;

    this.store.deleteTier(tier.id).subscribe({
      next: () => {
        this.feedback.success('Tier deleted');
        this.loadRateCard(this.rateCardId());
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }

  // ==================== ACTIONS ====================

  async toggleStatus() {
    const rc = this.rateCard();
    if (!rc) return;

    const action = rc.is_active ? 'deactivate' : 'activate';
    const confirmed = await this.feedback.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} Rate Card?`,
      rc.is_active
        ? 'This rate card will no longer be used for new quotes and applications.'
        : 'This will make this the active rate card for the plan. The current active rate card will be deactivated.'
    );
    if (!confirmed) return;

    this.store.activate(rc.id).subscribe({
      next: () => {
        this.feedback.success(`Rate card ${action}d successfully`);
        this.loadRateCard(rc.id);
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action} rate card`),
    });
  }
}
