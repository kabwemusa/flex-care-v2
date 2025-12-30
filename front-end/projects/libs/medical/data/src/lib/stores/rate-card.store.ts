// libs/medical/data/src/lib/stores/rate-card.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { RateCard, RateCardEntry, RateCardTier } from '../models/medical-interfaces';

interface RateCardState {
  items: RateCard[];
  selected: RateCard | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class RateCardListStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/rate-cards';

  private readonly state = signal<RateCardState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly rateCards = computed(() => this.state().items);
  readonly selectedRateCard = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Actions
  loadAll(params?: { plan_id?: string; active_only?: boolean }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.plan_id) queryParams.set('plan_id', params.plan_id);
    if (params?.active_only) queryParams.set('active_only', 'true');

    const url = queryParams.toString() ? `${this.apiUrl}?${queryParams}` : this.apiUrl;

    this.http.get<ApiResponse<RateCard[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadByPlan(planId: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http
      .get<ApiResponse<RateCard[]>>(`/api/v1/medical/plans/${planId}/rate-cards`)
      .pipe(
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

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<RateCard>>(`${this.apiUrl}/${id}`).pipe(
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

  create(rateCard: Partial<RateCard>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<RateCard>>(this.apiUrl, rateCard).pipe(
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

  update(id: string, changes: Partial<RateCard>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<RateCard>>(`${this.apiUrl}/${id}`, changes).pipe(
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

  activate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<RateCard>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
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

  clone(id: string, premiumAdjustment?: number) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<RateCard>>(`${this.apiUrl}/${id}/clone`, {
        premium_adjustment: premiumAdjustment || 0,
      })
      .pipe(
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

  // =========================================================================
  // ENTRIES
  // =========================================================================
  addEntry(rateCardId: string, entry: Partial<RateCardEntry>) {
    return this.http.post<ApiResponse<RateCardEntry>>(
      `${this.apiUrl}/${rateCardId}/entries`,
      entry
    );
  }

  updateEntry(entryId: string, changes: Partial<RateCardEntry>) {
    return this.http.put<ApiResponse<RateCardEntry>>(
      `/api/v1/medical/rate-card-entries/${entryId}`,
      changes
    );
  }

  deleteEntry(entryId: string) {
    return this.http.delete<ApiResponse<void>>(`/api/v1/medical/rate-card-entries/${entryId}`);
  }

  bulkImportEntries(
    rateCardId: string,
    entries: Partial<RateCardEntry>[],
    replaceExisting = false
  ) {
    return this.http.post<ApiResponse<{ imported_count: number }>>(
      `${this.apiUrl}/${rateCardId}/entries/bulk`,
      { entries, replace_existing: replaceExisting }
    );
  }

  // =========================================================================
  // TIERS
  // =========================================================================
  addTier(rateCardId: string, tier: Partial<RateCardTier>) {
    return this.http.post<ApiResponse<RateCardTier>>(`${this.apiUrl}/${rateCardId}/tiers`, tier);
  }

  updateTier(rateCardId: string, tier: Partial<RateCardTier>) {
    return this.http.patch<ApiResponse<RateCardTier>>(`${this.apiUrl}/${rateCardId}/tiers`, tier);
  }

  deleteTier(rateCardId: string) {
    return this.http.delete<ApiResponse<RateCardTier>>(`${this.apiUrl}/${rateCardId}/tiers`);
  }

  // =========================================================================
  // CALCULATION
  // =========================================================================
  calculatePremium(
    rateCardId: string,
    members: { age: number; member_type: string; gender?: 'M' | 'F' }[],
    addonIds?: string[]
  ) {
    return this.http.post<
      ApiResponse<{
        success: boolean;
        base_premium: number;
        addon_premium: number;
        total_premium: number;
        members: { member_type: string; premium: number }[];
        addons: { addon_id: string; premium: number }[];
      }>
    >(`${this.apiUrl}/${rateCardId}/calculate`, { members, addon_ids: addonIds });
  }

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
