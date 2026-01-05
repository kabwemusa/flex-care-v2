// libs/medical/data/src/lib/stores/plan-exclusion.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse, PaginatedResponse, PlanExclusion } from '../models/medical-interfaces';

interface PlanExclusionState {
  items: PlanExclusion[];
  selected: PlanExclusion | null;
  loading: boolean;
  saving: boolean;
  total: number;
  currentPage: number;
}

@Injectable({ providedIn: 'root' })
export class PlanExclusionStore {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = '/api/v1/medical';

  private readonly state = signal<PlanExclusionState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
    total: 0,
    currentPage: 1,
  });

  // Selectors
  readonly exclusions = computed(() => this.state().items);
  readonly selectedExclusion = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly total = computed(() => this.state().total);
  readonly currentPage = computed(() => this.state().currentPage);

  // Computed
  readonly activeExclusions = computed(() => this.exclusions().filter((e) => e.is_active));
  readonly generalExclusions = computed(() => this.exclusions().filter((e) => e.is_general));
  readonly benefitSpecificExclusions = computed(() =>
    this.exclusions().filter((e) => e.is_benefit_specific)
  );

  // =========================================================================
  // CRUD OPERATIONS
  // =========================================================================

  /**
   * Load all exclusions for a plan
   */
  loadForPlan(
    planId: string,
    params?: {
      search?: string;
      exclusion_type?: string;
      active_only?: boolean;
      page?: number;
      per_page?: number;
    }
  ) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.search) queryParams.set('search', params.search);
    if (params?.exclusion_type) queryParams.set('exclusion_type', params.exclusion_type);
    if (params?.active_only) queryParams.set('active_only', 'true');
    if (params?.page) queryParams.set('page', params.page.toString());
    if (params?.per_page) queryParams.set('per_page', params.per_page.toString());

    const url = queryParams.toString()
      ? `${this.baseUrl}/plans/${planId}/exclusions?${queryParams}`
      : `${this.baseUrl}/plans/${planId}/exclusions`;

    return this.http.get<PaginatedResponse<PlanExclusion>>(url).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: res.data,
            total: res.meta?.total || res.data.length,
            currentPage: res.meta?.current_page || 1,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load single exclusion details
   */
  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<PlanExclusion>>(`${this.baseUrl}/plan-exclusions/${id}`).pipe(
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

  /**
   * Create new exclusion
   */
  create(planId: string, data: Partial<PlanExclusion>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<PlanExclusion>>(`${this.baseUrl}/plans/${planId}/exclusions`, data).pipe(
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

  /**
   * Update exclusion
   */
  update(id: string, data: Partial<PlanExclusion>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<PlanExclusion>>(`${this.baseUrl}/plan-exclusions/${id}`, data).pipe(
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

  /**
   * Delete exclusion
   */
  delete(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<void>>(`${this.baseUrl}/plan-exclusions/${id}`).pipe(
      tap({
        next: () =>
          this.state.update((s) => ({
            ...s,
            items: s.items.filter((item) => item.id !== id),
            selected: s.selected?.id === id ? null : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Activate/deactivate exclusion
   */
  activate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<PlanExclusion>>(`${this.baseUrl}/plan-exclusions/${id}/activate`, {}).pipe(
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

  /**
   * Bulk delete exclusions
   */
  bulkDelete(planId: string, ids: string[]) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<void>>(`${this.baseUrl}/plans/${planId}/exclusions/bulk-delete`, { ids })
      .pipe(
        tap({
          next: () =>
            this.state.update((s) => ({
              ...s,
              items: s.items.filter((item) => !ids.includes(item.id)),
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  /**
   * Clear selected exclusion
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  /**
   * Reset store
   */
  reset() {
    this.state.set({
      items: [],
      selected: null,
      loading: false,
      saving: false,
      total: 0,
      currentPage: 1,
    });
  }
}
