// libs/medical/feature/src/lib/medical-plan-loading-config/medical-plan-loading-config.ts
// Embedded in Plan Detail — manages global medical underwriting loading rules.
// Loading rules are system-wide (no plan_id); this view provides convenient
// management from within the plan context.

import {
  Component,
  Input,
  OnInit,
  inject,
  signal,
  computed,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

// Domain Imports
import {
  LoadingRule,
  LoadingRuleStore,
  CONDITION_CATEGORIES,
  LOADING_TYPES,
  getLabelByValue,
} from 'medical-data';
import { LoadingRuleDialog } from '../dialogs/medical-loading-rule-dialog/medical-loading-rule-dialog';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-plan-loading-config',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
  ],
  templateUrl: './medical-plan-loading-config.html',
})
export class MedicalPlanLoadingConfig implements OnInit {
  // planId received for context; loading rules are global (no plan scoping)
  @Input({ required: true }) planId!: string;

  readonly store = inject(LoadingRuleStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Filter state
  searchTerm = signal('');
  categoryFilter = signal('');

  // Constants
  readonly categories = CONDITION_CATEGORIES;
  readonly loadingTypes = LOADING_TYPES;

  // Computed — filtered + grouped by category
  filteredRules = computed(() => {
    const search = this.searchTerm().toLowerCase().trim();
    const cat = this.categoryFilter();

    return this.store.loadingRules().filter((r) => {
      const matchesSearch =
        !search ||
        r.condition_name.toLowerCase().includes(search) ||
        (r.code?.toLowerCase().includes(search) ?? false) ||
        (r.icd10_code?.toLowerCase().includes(search) ?? false);
      const matchesCat = !cat || r.condition_category === cat;
      return matchesSearch && matchesCat;
    });
  });

  groupedRules = computed<{ category: string; label: string; icon: string; rules: LoadingRule[] }[]>(() => {
    const byCategory = new Map<string, LoadingRule[]>();

    for (const rule of this.filteredRules()) {
      const cat = rule.condition_category;
      if (!byCategory.has(cat)) byCategory.set(cat, []);
      byCategory.get(cat)!.push(rule);
    }

    return Array.from(byCategory.entries()).map(([cat, rules]) => ({
      category: cat,
      label: getLabelByValue(CONDITION_CATEGORIES, cat),
      icon: this.getCategoryIcon(cat),
      rules,
    }));
  });

  totalRules = computed(() => this.store.loadingRules().length);
  activeRules = computed(() => this.store.loadingRules().filter((r) => r.is_active).length);

  ngOnInit() {
    this.store.loadAll();
  }

  // ─── Display Helpers ─────────────────────────────────────────────────────

  getCategoryIcon(cat: string): string {
    const icons: Record<string, string> = {
      chronic: 'medication',
      pre_existing: 'history_toggle_off',
      lifestyle: 'sports_tennis',
    };
    return icons[cat] ?? 'category';
  }

  getCategoryClass(cat: string): string {
    const classes: Record<string, string> = {
      chronic: 'bg-orange-100 text-orange-600',
      pre_existing: 'bg-blue-100 text-blue-600',
      lifestyle: 'bg-purple-100 text-purple-600',
    };
    return classes[cat] ?? 'bg-slate-100 text-slate-600';
  }

  getCategoryBadgeClass(cat: string): string {
    const classes: Record<string, string> = {
      chronic: 'bg-orange-50 text-orange-700 border border-orange-200',
      pre_existing: 'bg-blue-50 text-blue-700 border border-blue-200',
      lifestyle: 'bg-purple-50 text-purple-700 border border-purple-200',
    };
    return classes[cat] ?? 'bg-slate-50 text-slate-700 border border-slate-200';
  }

  getLoadingLabel(rule: LoadingRule): string {
    if (rule.loading_type === 'exclusion') return 'Exclusion';
    const suffix = rule.loading_type === 'percentage' ? '%' : ' ZMW';
    return rule.loading_value != null ? `+${rule.loading_value}${suffix}` : 'N/A';
  }

  getDurationLabel(rule: LoadingRule): string {
    if (rule.duration_label) return rule.duration_label;
    if (rule.is_permanent || rule.duration_type === 'permanent') return 'Permanent';
    if (rule.duration_months) return `${rule.duration_months} mo`;
    if (rule.is_reviewable) return 'Annual Review';
    return 'N/A';
  }

  // ─── Filter Actions ───────────────────────────────────────────────────────

  onSearch(value: string) {
    this.searchTerm.set(value);
  }

  onCategoryFilter(value: string) {
    this.categoryFilter.set(value);
  }

  clearFilters() {
    this.searchTerm.set('');
    this.categoryFilter.set('');
  }

  // ─── CRUD Actions ─────────────────────────────────────────────────────────

  openDialog(rule?: LoadingRule) {
    const dialogRef = this.dialog.open(LoadingRuleDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: rule ? { ...rule } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const req$ = rule
        ? this.store.update(rule.id, result)
        : this.store.create(result);

      req$.subscribe({
        next: () => {
          this.feedback.success(`Loading rule ${rule ? 'updated' : 'created'} successfully`);
          this.store.loadAll();
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to save rule'),
      });
    });
  }

  async toggleStatus(rule: LoadingRule) {
    const action = rule.is_active ? 'deactivate' : 'activate';
    const confirmed = await this.feedback.confirm(
      `${rule.is_active ? 'Deactivate' : 'Activate'} Rule?`,
      rule.is_active
        ? 'This rule will no longer be applied to new applications.'
        : 'This rule will become effective for all plans immediately.'
    );
    if (!confirmed) return;

    this.store.update(rule.id, { is_active: !rule.is_active }).subscribe({
      next: () => this.feedback.success(`Rule ${action}d successfully`),
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action}`),
    });
  }

  async deleteRule(rule: LoadingRule) {
    const confirmed = await this.feedback.confirm(
      'Delete Loading Rule?',
      `Delete "${rule.condition_name}"? This will remove it from all underwriting calculations.`
    );
    if (!confirmed) return;

    this.store.delete(rule.id).subscribe({
      next: () => this.feedback.success('Loading rule deleted'),
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete'),
    });
  }
}
