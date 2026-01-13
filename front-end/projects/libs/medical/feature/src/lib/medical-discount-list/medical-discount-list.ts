// libs/medical/ui/src/lib/discounts/medical-discount-list.ts

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
import { MatTabsModule } from '@angular/material/tabs';

// Domain/Shared Imports
import {
  DiscountRule,
  PromoCode,
  DiscountListStore,
  DISCOUNT_TYPES,
  DISCOUNT_APPLICATION,
  getLabelByValue,
} from 'medical-data';
import { MedicalDiscountDialog } from '../dialogs/medical-discount-dialog/medical-discount-dialog';
import { PromoCodeDialog } from '../dialogs/medical-promocode-dialog/medical-promocode-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-discount-list',
  standalone: true,
  imports: [
    CommonModule,
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
    MatTabsModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-discount-list.html',
})
export class MedicalDiscountList implements OnInit, AfterViewInit {
  readonly store = inject(DiscountListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;
  @ViewChild('rulesPaginator') rulesPaginator!: MatPaginator;
  @ViewChild('rulesSort') rulesSort!: MatSort;
  @ViewChild('promosPaginator') promosPaginator!: MatPaginator;
  @ViewChild('promosSort') promosSort!: MatSort;

  // Filters
  searchQuery = signal('');
  adjustmentTypeFilter = signal<string>('');
  applicationFilter = signal<string>('');
  activeTabIndex = signal(0);

  // Drawer
  selectedRule = signal<DiscountRule | null>(null);

  // Data sources
  rulesDataSource = new MatTableDataSource<DiscountRule>([]);
  promosDataSource = new MatTableDataSource<PromoCode>([]);

  // Columns
  rulesColumns = ['name', 'type', 'value', 'scope', 'status', 'actions'];
  promosColumns = ['code', 'discount', 'validity', 'usage', 'status', 'actions'];

  // Constants
  readonly adjustmentTypes = DISCOUNT_TYPES;
  readonly applicationMethods = DISCOUNT_APPLICATION;

  // Computed KPIs
  totalRules = computed(() => this.store.discountRules().length);
  activeDiscounts = computed(
    () =>
      this.store.discountRules().filter((d) => d.is_active && d.adjustment_type === 'discount')
        .length
  );
  activeLoadings = computed(
    () =>
      this.store.discountRules().filter((d) => d.is_active && d.adjustment_type === 'loading')
        .length
  );
  activePromos = computed(() => this.store.promoCodes().filter((p) => p.is_usable).length);

  constructor() {
    // Sync Store Data to Table
    effect(() => {
      this.rulesDataSource.data = this.store.discountRules();
      this.promosDataSource.data = this.store.promoCodes();
    });
  }

  ngOnInit() {
    this.store.loadRules();
    this.store.loadPromoCodes();
    this.setupFilterPredicates();
  }

  ngAfterViewInit() {
    // Assign paginators/sorts
    this.rulesDataSource.paginator = this.rulesPaginator;
    this.rulesDataSource.sort = this.rulesSort;
    this.promosDataSource.paginator = this.promosPaginator;
    this.promosDataSource.sort = this.promosSort;
  }

  private setupFilterPredicates() {
    // Rules Predicate
    this.rulesDataSource.filterPredicate = (data: DiscountRule, filter: string): boolean => {
      const searchStr = this.searchQuery().toLowerCase();
      const typeFilter = this.adjustmentTypeFilter();
      const appFilter = this.applicationFilter();

      const matchesSearch =
        !searchStr ||
        data.name.toLowerCase().includes(searchStr) ||
        data.code.toLowerCase().includes(searchStr);

      const matchesType = !typeFilter || data.adjustment_type === typeFilter;
      const matchesApp = !appFilter || data.application_method === appFilter;

      return matchesSearch && matchesType && matchesApp;
    };

    // Promos Predicate
    this.promosDataSource.filterPredicate = (data: PromoCode, filter: string): boolean => {
      const searchStr = this.searchQuery().toLowerCase();
      // Only search filter applies to promos currently
      return (
        !searchStr ||
        data.code.toLowerCase().includes(searchStr) ||
        (data.name?.toLowerCase().includes(searchStr) ?? false)
      );
    };
  }

  // Filter Actions
  applyFilter(event: Event) {
    const val = (event.target as HTMLInputElement).value;
    this.searchQuery.set(val);
    this.triggerFilter();
  }

  filterByType(val: string) {
    this.adjustmentTypeFilter.set(val);
    this.triggerFilter();
  }

  filterByApp(val: string) {
    this.applicationFilter.set(val);
    this.triggerFilter();
  }

  private triggerFilter() {
    // Trigger filter update on both sources
    this.rulesDataSource.filter = 'trigger';
    this.promosDataSource.filter = 'trigger';

    if (this.activeTabIndex() === 0) this.rulesDataSource.paginator?.firstPage();
    else this.promosDataSource.paginator?.firstPage();
  }

  clearFilters() {
    this.searchQuery.set('');
    this.adjustmentTypeFilter.set('');
    this.applicationFilter.set('');
    this.triggerFilter();
    this.rulesDataSource.filter = '';
    this.promosDataSource.filter = '';
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    // Optional: Reset filters when switching tabs if desired
  }

  // Labels
  getValueTypeLabel(rule: DiscountRule): string {
    return rule.value_type === 'percentage' ? `${rule.value}%` : `ZMW ${rule.value}`;
  }

  getApplicationLabel(val: string): string {
    return getLabelByValue(DISCOUNT_APPLICATION, val);
  }

  getScopeLabel(rule: DiscountRule): string {
    if (rule.plan) return `Plan: ${rule.plan.name}`;
    if (rule.scheme) return `Scheme: ${rule.scheme.name}`;
    return 'Global';
  }

  // Drawer
  viewDetails(rule: DiscountRule) {
    this.selectedRule.set(rule);
    this.detailDrawer.open();
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedRule.set(null);
  }

  // CRUD
  openRuleDialog(rule?: DiscountRule) {
    const dialogRef = this.dialog.open(MedicalDiscountDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: rule ? { ...rule } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;
      const req$ = rule ? this.store.updateRule(rule.id, result) : this.store.createRule(result);
      req$.subscribe({
        next: () => this.feedback.success('Rule saved successfully'),
        error: (e) => this.feedback.error(e?.error?.message || 'Failed to save'),
      });
    });
  }

  // openPromoDialog(promo?: PromoCode) {
  //   const dialogRef = this.dialog.open(PromoCodeDialog, {
  //     maxWidth: '50vw',
  //     data: promo ? { ...promo } : null,
  //     panelClass: ['responsive-dialog', 'bg-white'],
  //     autoFocus: false,
  //   });

  //   dialogRef.afterClosed().subscribe((result) => {
  //     if(!result) return;
  //     // Handle promo save
  //   });
  // }

  async deleteRule(rule: DiscountRule) {
    if (
      await this.feedback.confirm('Delete Rule?', `Are you sure you want to delete ${rule.name}?`)
    ) {
      this.store.deleteRule(rule.id).subscribe({
        next: () => {
          this.feedback.success('Rule deleted');
          this.closeDrawer();
        },
        error: () => this.feedback.error('Failed to delete'),
      });
    }
  }

  async toggleRuleStatus(rule: DiscountRule) {
    const action = rule.is_active ? 'deactivate' : 'activate';
    this.store.updateRule(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => this.feedback.success(`Rule ${action}d`),
      error: () => this.feedback.error('Failed to update status'),
    });
  }

  // CSV Export
  exportToCsv() {
    const isRules = this.activeTabIndex() === 0;
    const data = isRules ? this.rulesDataSource.filteredData : this.promosDataSource.filteredData;

    if (!data.length) {
      this.feedback.error('No data to export');
      return;
    }

    // Simple CSV logic ...
    const filename = isRules ? 'discount_rules.csv' : 'promo_codes.csv';
    // Implementation of CSV generation here...
    this.feedback.success(`Exporting ${filename}...`);
  }

  // Method stubs for Promo CRUD

  // CRUD - Promo Codes
  openPromoDialog(promo?: PromoCode, ruleId?: string) {
    const dialogRef = this.dialog.open(PromoCodeDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: promo ? { ...promo } : { discount_rule_id: ruleId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = promo
        ? this.store.updatePromoCode(promo.id, result)
        : this.store.createPromoCode(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Promo code ${promo ? 'updated' : 'created'} successfully`);
          this.store.loadPromoCodes();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save promo code'),
      });
    });
  }

  async deletePromo(promo: PromoCode) {
    const confirmed = await this.feedback.confirm(
      'Delete Promo Code?',
      `Are you sure you want to delete "${promo.code}"?`
    );
    if (!confirmed) return;

    this.store.deletePromoCode(promo.id).subscribe({
      next: () => this.feedback.success('Promo code deleted'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }

  async togglePromoStatus(promo: PromoCode) {
    const action = promo.is_active ? 'deactivate' : 'activate';

    this.store.updatePromoCode(promo.id, { is_active: !promo.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Promo code ${action}d`);
        this.store.loadPromoCodes();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action}`),
    });
  }
}
