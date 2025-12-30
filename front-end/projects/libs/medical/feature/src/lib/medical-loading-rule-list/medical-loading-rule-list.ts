// libs/medical/ui/src/lib/loading-rules/medical-loading-rule-list.ts

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

// Domain Imports
import {
  LoadingRule,
  LoadingRuleStore,
  LOADING_TYPES,
  DURATION_TYPES,
  CONDITION_CATEGORIES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService } from 'shared';
import { LoadingRuleDialog } from '../dialogs/medical-loading-rule-dialog/medical-loading-rule-dialog';

@Component({
  selector: 'lib-medical-loading-rule-list',
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
  ],
  templateUrl: './medical-loading-rule-list.html',
})
export class MedicalLoadingRuleList implements OnInit {
  readonly store = inject(LoadingRuleStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  @ViewChild('drawer') drawer!: MatSidenav;
  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;

  // Filters
  searchQuery = signal('');
  categoryFilter = signal<string>('');
  loadingTypeFilter = signal<string>('');

  // Drawer
  selectedRule = signal<LoadingRule | null>(null);

  // Data source
  dataSource = new MatTableDataSource<LoadingRule>([]);
  displayColumns = ['condition_name', 'category', 'loading', 'duration', 'status', 'actions'];

  // Constants
  readonly categories = CONDITION_CATEGORIES;
  readonly loadingTypes = LOADING_TYPES;
  readonly durationTypes = DURATION_TYPES;

  // Computed stats
  totalRules = computed(() => this.store.loadingRules().length);
  activeRules = computed(() => this.store.activeRules().length);
  chronicRules = computed(
    () => this.store.loadingRules().filter((r) => r.condition_category === 'chronic').length
  );
  preExistingRules = computed(
    () => this.store.loadingRules().filter((r) => r.condition_category === 'pre_existing').length
  );

  ngOnInit() {
    this.store.loadAll();
    this.setupDataSource();
  }

  private setupDataSource() {
    setTimeout(() => {
      this.applyFilters();
    });
  }

  applyFilters() {
    let rules = this.store.loadingRules();

    // Apply filters
    if (this.categoryFilter()) {
      rules = rules.filter((r) => r.condition_category === this.categoryFilter());
    }
    if (this.loadingTypeFilter()) {
      rules = rules.filter((r) => r.loading_type === this.loadingTypeFilter());
    }
    if (this.searchQuery()) {
      const q = this.searchQuery().toLowerCase();
      rules = rules.filter(
        (r) =>
          r.condition_name.toLowerCase().includes(q) ||
          r.code.toLowerCase().includes(q) ||
          r.icd10_code?.toLowerCase().includes(q)
      );
    }

    this.dataSource.data = rules;
    if (this.paginator) this.dataSource.paginator = this.paginator;
    if (this.sort) this.dataSource.sort = this.sort;
  }

  clearFilters() {
    this.searchQuery.set('');
    this.categoryFilter.set('');
    this.loadingTypeFilter.set('');
    this.applyFilters();
  }

  // Label helpers
  getCategoryLabel(value: string): string {
    return getLabelByValue(CONDITION_CATEGORIES, value);
  }

  getLoadingTypeLabel(value: string): string {
    return getLabelByValue(LOADING_TYPES, value);
  }

  getDurationLabel(rule: LoadingRule): string {
    if (rule.duration_label) return rule.duration_label;
    if (rule.is_permanent) return 'Permanent';
    if (rule.is_time_limited && rule.duration_months) return `${rule.duration_months} months`;
    if (rule.is_reviewable) return 'Annual Review';
    return 'N/A';
  }

  getCategoryClass(category: string): string {
    const classes: Record<string, string> = {
      chronic: 'bg-red-100 text-red-700',
      pre_existing: 'bg-amber-100 text-amber-700',
      lifestyle: 'bg-purple-100 text-purple-700',
      cardiovascular: 'bg-red-100 text-red-700',
      respiratory: 'bg-blue-100 text-blue-700',
      diabetes: 'bg-orange-100 text-orange-700',
      musculoskeletal: 'bg-green-100 text-green-700',
      mental_health: 'bg-violet-100 text-violet-700',
      oncology: 'bg-pink-100 text-pink-700',
      renal: 'bg-cyan-100 text-cyan-700',
    };
    return classes[category] || 'bg-slate-100 text-slate-700';
  }

  // Drawer
  openDrawer(rule: LoadingRule) {
    this.selectedRule.set(rule);
    this.drawer.open();
  }

  closeDrawer() {
    this.drawer.close();
    this.selectedRule.set(null);
  }

  // CRUD
  openRuleDialog(rule?: LoadingRule) {
    const dialogRef = this.dialog.open(LoadingRuleDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: rule ? { ...rule } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = rule ? this.store.update(rule.id, result) : this.store.create(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Loading rule ${rule ? 'updated' : 'created'} successfully`);
          this.store.loadAll();
          this.applyFilters();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rule'),
      });
    });
  }

  async deleteRule(rule: LoadingRule) {
    const confirmed = await this.feedback.confirm(
      'Delete Loading Rule?',
      `Are you sure you want to delete "${rule.condition_name}"?`
    );
    if (!confirmed) return;

    this.store.delete(rule.id).subscribe({
      next: () => {
        this.feedback.success('Rule deleted');
        this.closeDrawer();
        this.applyFilters();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }

  async toggleRuleStatus(rule: LoadingRule) {
    const action = rule.is_active ? 'deactivate' : 'activate';

    this.store.update(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Rule ${action}d`);
        this.store.loadAll();
        this.applyFilters();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action}`),
    });
  }
}
