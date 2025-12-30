// libs/medical/data/src/lib/stores/loading-rule.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { LoadingRule } from '../models/medical-interfaces';

interface LoadingRuleState {
  items: LoadingRule[];
  categories: { value: string; label: string }[];
  selected: LoadingRule | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class LoadingRuleStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/loading-rules';

  private readonly state = signal<LoadingRuleState>({
    items: [],
    categories: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly loadingRules = computed(() => this.state().items);
  readonly categories = computed(() => this.state().categories);
  readonly selectedRule = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activeRules = computed(() => this.loadingRules().filter((r) => r.is_active));
  readonly rulesByCategory = computed(() => {
    const rules = this.loadingRules();
    return rules.reduce((acc, rule) => {
      const cat = rule.condition_category;
      if (!acc[cat]) acc[cat] = [];
      acc[cat].push(rule);
      return acc;
    }, {} as Record<string, LoadingRule[]>);
  });

  // Actions
  loadAll(params?: { condition_category?: string; loading_type?: string; active_only?: boolean }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.condition_category)
      queryParams.set('condition_category', params.condition_category);
    if (params?.loading_type) queryParams.set('loading_type', params.loading_type);
    if (params?.active_only) queryParams.set('active_only', 'true');

    const url = queryParams.toString() ? `${this.apiUrl}?${queryParams}` : this.apiUrl;

    this.http.get<ApiResponse<LoadingRule[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadByCategory(category: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<LoadingRule[]>>(`${this.apiUrl}/by-category/${category}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  loadCategories() {
    this.http
      .get<ApiResponse<{ value: string; label: string }[]>>(`${this.apiUrl}/categories`)
      .subscribe({
        next: (res) => this.state.update((s) => ({ ...s, categories: res.data })),
      });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<LoadingRule>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selected: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  search(term: string) {
    return this.http.get<ApiResponse<LoadingRule[]>>(`${this.apiUrl}/search?q=${term}`);
  }

  getOptions(identifier: string) {
    return this.http.get<
      ApiResponse<{
        found: boolean;
        condition_name: string;
        options: {
          loading_type: string;
          loading_value: number;
          duration_type: string;
          duration_months?: number;
        }[];
        exclusion_available: boolean;
        exclusion_terms?: string;
      }>
    >(`${this.apiUrl}/options/${identifier}`);
  }

  create(rule: Partial<LoadingRule>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<LoadingRule>>(this.apiUrl, rule).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: [res.data, ...s.items],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  update(id: string, changes: Partial<LoadingRule>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<LoadingRule>>(`${this.apiUrl}/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((item) => (item.id === id ? res.data : item)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  delete(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((item) => item.id !== id),
        }))
      )
    );
  }

  calculateLoadings(premium: number, conditions: string[], coverStartDate?: string) {
    return this.http.post<
      ApiResponse<{
        original_premium: number;
        loadings: {
          condition: string;
          loading_type: string;
          loading_amount: number;
        }[];
        total_loading: number;
        final_premium: number;
      }>
    >('/api/v1/medical/loadings/calculate', {
      premium,
      conditions,
      cover_start_date: coverStartDate,
    });
  }

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
