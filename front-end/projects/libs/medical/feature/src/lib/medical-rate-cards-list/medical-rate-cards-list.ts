// libs/medical/ui/src/lib/rate-cards/medical-rate-card-list.ts

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
import { MatChipsModule } from '@angular/material/chips';

// Domain/Shared Imports
import {
  RateCard,
  RateCardListStore,
  PlanListStore,
  PREMIUM_FREQUENCIES,
  PREMIUM_BASES,
  getLabelByValue,
} from 'medical-data';
import { MedicalRateCardListDialog } from '../dialogs/medical-rate-cards-list-dialog/medical-rate-cards-list-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-rate-card-list',
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
    MatChipsModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-rate-cards-list.html',
})
export class MedicalRateCardsList implements OnInit, AfterViewInit {
  readonly store = inject(RateCardListStore);
  readonly planStore = inject(PlanListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = [
    'status',
    'name',
    'plan',
    'frequency',
    'basis',
    'entries',
    'effective',
    'actions',
  ];
  dataSource = new MatTableDataSource<RateCard>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  selectedPlan = signal<string>('');
  selectedStatus = signal<string>('');
  searchQuery = signal<string>('');

  // Detail View
  selectedRateCard = signal<RateCard | null>(null);

  // Constants
  readonly premiumFrequencies = PREMIUM_FREQUENCIES;
  readonly premiumBases = PREMIUM_BASES;

  // KPIs
  totalRateCards = computed(() => this.store.rateCards()?.length || 0);
  activeRateCards = computed(() => this.store.rateCards()?.filter((r) => r.is_active).length || 0);
  draftRateCards = computed(() => this.store.rateCards()?.filter((r) => r.is_draft).length || 0);

  constructor() {
    effect(() => {
      const rateCards = this.store.rateCards();
      if (Array.isArray(rateCards)) {
        this.dataSource.data = rateCards;
      }
    });
  }

  ngOnInit() {
    this.store.loadAll();
    this.planStore.loadAll();

    this.dataSource.filterPredicate = (data: RateCard, filter: string) => {
      const searchStr = filter.toLowerCase();
      const name = data.name?.toLowerCase() || '';
      const code = data.code?.toLowerCase() || '';
      const planName = data.plan?.name?.toLowerCase() || '';
      return name.includes(searchStr) || code.includes(searchStr) || planName.includes(searchStr);
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

  filterByPlan(planId: string) {
    this.selectedPlan.set(planId);
    this.applyFilters();
  }

  filterByStatus(status: string) {
    this.selectedStatus.set(status);
    this.applyFilters();
  }

  private applyFilters() {
    let filtered = this.store.rateCards() || [];

    if (this.selectedPlan()) {
      filtered = filtered.filter((r) => r.plan_id === this.selectedPlan());
    }

    if (this.selectedStatus()) {
      if (this.selectedStatus() === 'active') {
        filtered = filtered.filter((r) => r.is_active);
      } else if (this.selectedStatus() === 'draft') {
        filtered = filtered.filter((r) => r.is_draft);
      } else if (this.selectedStatus() === 'inactive') {
        filtered = filtered.filter((r) => !r.is_active && !r.is_draft);
      }
    }

    this.dataSource.data = filtered;
  }

  clearFilters() {
    this.selectedPlan.set('');
    this.selectedStatus.set('');
    this.searchQuery.set('');
    this.dataSource.filter = '';
    this.dataSource.data = this.store.rateCards() || [];
  }

  // Labels
  getFrequencyLabel(value: string): string {
    return getLabelByValue(PREMIUM_FREQUENCIES, value);
  }

  getBasisLabel(value: string): string {
    return getLabelByValue(PREMIUM_BASES, value);
  }

  getStatusBadge(rateCard: RateCard): { label: string; class: string } {
    if (rateCard.is_active) {
      return { label: 'Active', class: 'bg-green-50 text-green-700 border-green-200' };
    }
    if (rateCard.is_draft) {
      return { label: 'Draft', class: 'bg-amber-50 text-amber-700 border-amber-200' };
    }
    return { label: 'Inactive', class: 'bg-gray-100 text-gray-600 border-gray-200' };
  }

  // Drawer
  viewDetails(rateCard: RateCard) {
    this.store.loadOne(rateCard.id).subscribe({
      next: () => {
        this.selectedRateCard.set(this.store.selectedRateCard());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load rate card details'),
    });
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedRateCard.set(null);
  }

  // Dialog
  openCreateDialog(rateCard?: RateCard) {
    const dialogRef = this.dialog.open(MedicalRateCardListDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: rateCard ? { ...rateCard } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = rateCard
        ? this.store.update(rateCard.id, result)
        : this.store.create(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Rate card ${rateCard ? 'updated' : 'created'} successfully`);
          if (this.selectedRateCard()?.id === rateCard?.id) {
            this.selectedRateCard.set({ ...this.selectedRateCard()!, ...result });
          }
        },
        error: (err) =>
          this.feedback.error(
            err?.error?.message ?? `Failed to ${rateCard ? 'update' : 'create'} rate card`
          ),
      });
    });
  }

  async activateRateCard(rateCard: RateCard) {
    const confirmed = await this.feedback.confirm(
      'Activate Rate Card?',
      'This will make this rate card the active version for the plan. Any existing active rate card will be deactivated.'
    );

    if (!confirmed) return;

    this.store.activate(rateCard.id).subscribe({
      next: () => {
        this.feedback.success('Rate card activated successfully');
        if (this.selectedRateCard()?.id === rateCard.id) {
          this.selectedRateCard.set({
            ...this.selectedRateCard()!,
            is_active: true,
            is_draft: false,
          });
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate rate card'),
    });
  }

  async cloneRateCard(rateCard: RateCard) {
    const confirmed = await this.feedback.confirm(
      'Clone Rate Card?',
      'This will create a draft copy with all entries and configurations.'
    );

    if (!confirmed) return;

    this.store.clone(rateCard.id).subscribe({
      next: () => this.feedback.success('Rate card cloned successfully'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to clone rate card'),
    });
  }

  async deleteRateCard(rateCard: RateCard) {
    if (rateCard.is_active) {
      this.feedback.error('Cannot delete an active rate card. Deactivate it first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Rate Card?',
      `Are you sure you want to delete "${rateCard.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(rateCard.id).subscribe({
      next: () => {
        this.feedback.success('Rate card deleted successfully');
        if (this.selectedRateCard()?.id === rateCard.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete rate card'),
    });
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-ZA', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }
}
