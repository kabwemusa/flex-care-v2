// libs/medical/data/src/lib/stores/addon.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import {
  Addon,
  AddonBenefit,
  AddonRate,
  PlanAddon,
  DropdownOption,
} from '../models/medical-interfaces';

interface AddonState {
  items: Addon[];
  planAddons: PlanAddon[];
  selected: Addon | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class AddonCatalogStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/addons';

  private readonly state = signal<AddonState>({
    items: [],
    planAddons: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly addons = computed(() => this.state().items);
  readonly planAddons = computed(() => this.state().planAddons);
  readonly selectedAddon = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activeAddons = computed(() => this.addons().filter((a) => a.is_active));

  // =========================================================================
  // ADDON CATALOG
  // =========================================================================
  loadAll(params?: { addon_type?: string; active_only?: boolean }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.addon_type) queryParams.set('addon_type', params.addon_type);
    if (params?.active_only) queryParams.set('active_only', 'true');

    const url = queryParams.toString() ? `${this.apiUrl}?${queryParams}` : this.apiUrl;

    this.http.get<ApiResponse<Addon[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Addon>>(`${this.apiUrl}/${id}`).pipe(
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

  loadDropdown() {
    return this.http.get<ApiResponse<DropdownOption[]>>(`${this.apiUrl}/dropdown`);
  }

  create(addon: Partial<Addon>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Addon>>(this.apiUrl, addon).pipe(
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

  update(id: string, changes: Partial<Addon>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<Addon>>(`${this.apiUrl}/${id}`, changes).pipe(
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

  // =========================================================================
  // ADDON BENEFITS
  // =========================================================================
  addBenefit(addonId: string, benefit: Partial<AddonBenefit>) {
    return this.http.post<ApiResponse<AddonBenefit>>(`${this.apiUrl}/${addonId}/benefits`, benefit);
  }

  removeBenefit(addonBenefitId: string) {
    return this.http.delete<ApiResponse<void>>(`/api/v1/medical/addon-benefits/${addonBenefitId}`);
  }

  // =========================================================================
  // ADDON RATES
  // =========================================================================
  addRate(addonId: string, rate: Partial<AddonRate>) {
    return this.http.post<ApiResponse<AddonRate>>(`${this.apiUrl}/${addonId}/rates`, rate);
  }

  activateRate(rateId: string) {
    return this.http.post<ApiResponse<AddonRate>>(
      `/api/v1/medical/addon-rates/${rateId}/activate`,
      {}
    );
  }

  // =========================================================================
  // PLAN ADDONS
  // =========================================================================
  loadPlanAddons(planId: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http.get<ApiResponse<PlanAddon[]>>(`/api/v1/medical/plans/${planId}/addons`).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          planAddons: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadAvailableAddons(planId: string) {
    return this.http.get<ApiResponse<Addon[]>>(`/api/v1/medical/plans/${planId}/available-addons`);
  }

  configurePlanAddon(planId: string, data: Partial<PlanAddon>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<PlanAddon>>(`/api/v1/medical/plans/${planId}/addons`, data)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              planAddons: [
                ...s.planAddons.filter((pa) => pa.addon_id !== res.data.addon_id),
                res.data,
              ],
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  updatePlanAddon(id: string, changes: Partial<PlanAddon>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<PlanAddon>>(`/api/v1/medical/plan-addons/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            planAddons: s.planAddons.map((pa) => (pa.id === id ? res.data : pa)),
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  removePlanAddon(id: string) {
    return this.http.delete<ApiResponse<void>>(`/api/v1/medical/plan-addons/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          planAddons: s.planAddons.filter((pa) => pa.id !== id),
        }))
      )
    );
  }

  clearPlanAddons() {
    this.state.update((s) => ({ ...s, planAddons: [] }));
  }

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
