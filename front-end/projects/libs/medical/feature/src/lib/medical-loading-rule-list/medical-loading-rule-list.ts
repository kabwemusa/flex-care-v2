// libs/medical/ui/src/lib/loading-rules/medical-loading-rule-list.ts

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

// Domain/Shared Imports
import {
  LoadingRule,
  LoadingRuleStore,
  CONDITION_CATEGORIES,
  LOADING_TYPES,
  getLabelByValue,
} from 'medical-data';
import { LoadingRuleDialog } from '../dialogs/medical-loading-rule-dialog/medical-loading-rule-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-loading-rule-list',
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
    PageHeaderComponent,
  ],
  templateUrl: './medical-loading-rule-list.html',
})
export class MedicalLoadingRuleList implements OnInit, AfterViewInit {
  readonly store = inject(LoadingRuleStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = ['condition_name', 'category', 'loading', 'duration', 'status', 'actions'];
  dataSource = new MatTableDataSource<LoadingRule>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  searchQuery = signal<string>('');
  categoryFilter = signal<string>('');
  loadingTypeFilter = signal<string>('');

  // Detail View
  selectedRule = signal<LoadingRule | null>(null);

  // Constants
  categories = CONDITION_CATEGORIES;
  loadingTypes = LOADING_TYPES;

  // Computed KPIs
  totalRules = computed(() => this.store.loadingRules().length);
  activeRules = computed(() => this.store.loadingRules().filter((r) => r.is_active).length);
  chronicRules = computed(
    () => this.store.loadingRules().filter((r) => r.condition_category === 'chronic').length
  );

  constructor() {
    effect(() => {
      this.dataSource.data = this.store.loadingRules();
    });
  }

  ngOnInit() {
    this.store.loadAll();

    // Custom filter predicate
    this.dataSource.filterPredicate = (data: LoadingRule, filter: string): boolean => {
      const searchStr = this.searchQuery().toLowerCase();
      const catFilter = this.categoryFilter();
      const typeFilter = this.loadingTypeFilter();

      // FIXED: Added ( ?? false ) to handle undefined values from optional chaining
      const matchesSearch =
        !searchStr ||
        data.condition_name.toLowerCase().includes(searchStr) ||
        (data.code?.toLowerCase().includes(searchStr) ?? false) ||
        (data.icd10_code?.toLowerCase().includes(searchStr) ?? false);

      const matchesCategory = !catFilter || data.condition_category === catFilter;
      const matchesType = !typeFilter || data.loading_type === typeFilter;

      return matchesSearch && matchesCategory && matchesType;
    };
  }

  ngAfterViewInit() {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  // Filter Actions
  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.searchQuery.set(filterValue);
    this.triggerFilter();
  }

  filterByCategory(category: string) {
    this.categoryFilter.set(category);
    this.triggerFilter();
  }

  filterByType(type: string) {
    this.loadingTypeFilter.set(type);
    this.triggerFilter();
  }

  private triggerFilter() {
    this.dataSource.filter = 'trigger'; // Any string triggers the predicate
    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  clearFilters() {
    this.searchQuery.set('');
    this.categoryFilter.set('');
    this.loadingTypeFilter.set('');
    this.dataSource.filter = '';
  }

  // Labels & Styling
  getCategoryLabel(value: string): string {
    return getLabelByValue(CONDITION_CATEGORIES, value);
  }

  getLoadingTypeLabel(value: string): string {
    return getLabelByValue(LOADING_TYPES, value);
  }

  getDurationLabel(rule: LoadingRule): string {
    if (rule.duration_label) return rule.duration_label;
    if (rule.is_permanent) return 'Permanent';
    if (rule.is_time_limited && rule.duration_months) return `${rule.duration_months} Months`;
    if (rule.is_reviewable) return 'Annual Review';
    return 'N/A';
  }

  // libs/medical/ui/src/lib/loading-rules/medical-loading-rule-list.ts

  getCategoryClass(category: string): string {
    const classes: Record<string, string> = {
      // Backend: 'chronic'
      chronic: 'bg-orange-50 text-orange-700 border-orange-200',

      // Backend: 'pre_existing'
      pre_existing: 'bg-blue-50 text-blue-700 border-blue-200',

      // Backend: 'lifestyle'
      lifestyle: 'bg-purple-50 text-purple-700 border-purple-200',
    };

    return classes[category] || 'bg-slate-50 text-slate-700 border-slate-200';
  }

  // Detail Drawer
  viewDetails(rule: LoadingRule) {
    this.selectedRule.set(rule);
    this.detailDrawer.open();
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedRule.set(null);
  }

  // CRUD Actions
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
          this.feedback.success(`Rule ${rule ? 'updated' : 'created'} successfully`);
          if (this.selectedRule()?.id === rule?.id) {
            this.selectedRule.set({ ...this.selectedRule()!, ...result });
          }
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rule'),
      });
    });
  }

  async toggleStatus(rule: LoadingRule) {
    const action = rule.is_active ? 'Deactivate' : 'Activate';
    const confirmed = await this.feedback.confirm(
      `${action} Rule?`,
      rule.is_active
        ? 'This rule will no longer be applied to new quotes.'
        : 'This rule will become effective immediately.'
    );

    if (!confirmed) return;

    this.store.update(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => this.feedback.success(`Rule ${action.toLowerCase()}d successfully`),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to update status'),
    });
  }

  async deleteRule(rule: LoadingRule) {
    const confirmed = await this.feedback.confirm(
      'Delete Rule?',
      `Are you sure you want to delete "${rule.condition_name}"?`
    );

    if (!confirmed) return;

    this.store.delete(rule.id).subscribe({
      next: () => {
        this.feedback.success('Rule deleted successfully');
        if (this.selectedRule()?.id === rule.id) this.closeDrawer();
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete rule'),
    });
  }

  exportToCsv() {
    const dataToExport = this.dataSource.filteredData.length
      ? this.dataSource.filteredData
      : this.store.loadingRules();

    if (dataToExport.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = ['Condition', 'Code', 'Category', 'Loading', 'Type', 'Status'];
    const rows = dataToExport.map((r) => [
      `"${r.condition_name}"`,
      r.code,
      this.getCategoryLabel(r.condition_category),
      r.loading_value,
      r.loading_type,
      r.is_active ? 'Active' : 'Inactive',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `loading_rules_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
