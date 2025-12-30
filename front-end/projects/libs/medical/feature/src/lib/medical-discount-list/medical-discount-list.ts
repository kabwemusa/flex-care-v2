// libs/medical/ui/src/lib/discounts/medical-discount-list.ts

import { Component, OnInit, inject, signal, computed, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatDialog } from '@angular/material/dialog';
import { MatSidenav, MatSidenavModule } from '@angular/material/sidenav';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { MatTabsModule } from '@angular/material/tabs';

// Domain Imports
import {
  DiscountRule,
  PromoCode,
  DiscountListStore,
  DISCOUNT_TYPES,
  DISCOUNT_APPLICATION,
  VALUE_TYPES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalDiscountDialog } from '../dialogs/medical-discount-dialog/medical-discount-dialog';
import { PromoCodeDialog } from '../dialogs/medical-promocode-dialog/medical-promocode-dialog';

@Component({
  selector: 'lib-medical-discount-list',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatSidenavModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatDividerModule,
    MatTabsModule,
  ],
  templateUrl: './medical-discount-list.html',
})
export class MedicalDiscountList implements OnInit {
  readonly store = inject(DiscountListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  @ViewChild('drawer') drawer!: MatSidenav;
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

  // Table columns
  rulesColumns = ['name', 'type', 'value', 'application', 'scope', 'status', 'actions'];
  promosColumns = ['code', 'discount', 'validity', 'usage', 'status', 'actions'];

  // Constants
  readonly adjustmentTypes = DISCOUNT_TYPES;
  readonly applicationMethods = DISCOUNT_APPLICATION;

  // Computed stats
  totalRules = computed(() => this.store.discountRules().length);
  activeDiscounts = computed(() => this.store.discounts().filter((d) => d.is_active).length);
  activeLoadings = computed(() => this.store.loadings().filter((l) => l.is_active).length);
  activePromos = computed(() => this.store.activePromoCodes().length);

  ngOnInit() {
    this.store.loadRules();
    this.store.loadPromoCodes();

    // Subscribe to store changes
    this.setupDataSources();
  }

  private setupDataSources() {
    // Update data sources when store changes
    setTimeout(() => {
      this.updateRulesDataSource();
      this.updatePromosDataSource();
    });
  }

  private updateRulesDataSource() {
    let rules = this.store.discountRules();

    // Apply filters
    if (this.adjustmentTypeFilter()) {
      rules = rules.filter((r) => r.adjustment_type === this.adjustmentTypeFilter());
    }
    if (this.applicationFilter()) {
      rules = rules.filter((r) => r.application_method === this.applicationFilter());
    }
    if (this.searchQuery()) {
      const q = this.searchQuery().toLowerCase();
      rules = rules.filter(
        (r) =>
          r.name.toLowerCase().includes(q) ||
          r.code.toLowerCase().includes(q) ||
          r.description?.toLowerCase().includes(q)
      );
    }

    this.rulesDataSource.data = rules;
    if (this.rulesPaginator) this.rulesDataSource.paginator = this.rulesPaginator;
    if (this.rulesSort) this.rulesDataSource.sort = this.rulesSort;
  }

  private updatePromosDataSource() {
    let promos = this.store.promoCodes();

    if (this.searchQuery()) {
      const q = this.searchQuery().toLowerCase();
      promos = promos.filter(
        (p) => p.code.toLowerCase().includes(q) || p.name?.toLowerCase().includes(q)
      );
    }

    this.promosDataSource.data = promos;
    if (this.promosPaginator) this.promosDataSource.paginator = this.promosPaginator;
    if (this.promosSort) this.promosDataSource.sort = this.promosSort;
  }

  applyFilters() {
    if (this.activeTabIndex() === 0) {
      this.updateRulesDataSource();
    } else {
      this.updatePromosDataSource();
    }
  }

  clearFilters() {
    this.searchQuery.set('');
    this.adjustmentTypeFilter.set('');
    this.applicationFilter.set('');
    this.applyFilters();
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    this.applyFilters();
  }

  // Label helpers
  getValueTypeLabel(rule: DiscountRule): string {
    if (rule.value_type === 'percentage') {
      return `${rule.value}%`;
    }
    return `ZMW ${rule.value}`;
  }

  getApplicationLabel(value: string): string {
    return getLabelByValue(DISCOUNT_APPLICATION, value);
  }

  getScopeLabel(rule: DiscountRule): string {
    if (rule.plan) return rule.plan.name;
    if (rule.scheme) return rule.scheme.name;
    return 'Global';
  }

  // Drawer
  openDrawer(rule: DiscountRule) {
    this.selectedRule.set(rule);
    this.drawer.open();
  }

  closeDrawer() {
    this.drawer.close();
    this.selectedRule.set(null);
  }

  // CRUD - Rules
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

      const request$ = rule
        ? this.store.updateRule(rule.id, result)
        : this.store.createRule(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Rule ${rule ? 'updated' : 'created'} successfully`);
          this.store.loadRules();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rule'),
      });
    });
  }

  async deleteRule(rule: DiscountRule) {
    const confirmed = await this.feedback.confirm(
      'Delete Rule?',
      `Are you sure you want to delete "${rule.name}"?`
    );
    if (!confirmed) return;

    this.store.deleteRule(rule.id).subscribe({
      next: () => {
        this.feedback.success('Rule deleted');
        this.closeDrawer();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }

  async toggleRuleStatus(rule: DiscountRule) {
    const action = rule.is_active ? 'deactivate' : 'activate';

    this.store.updateRule(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Rule ${action}d`);
        this.store.loadRules();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action}`),
    });
  }

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
