// libs/medical/data/src/lib/stores/plan.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { MedicalPlan, DropdownOption } from '../models/medical-interfaces';

interface PlanState {
  items: MedicalPlan[];
  selected: MedicalPlan | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class PlanListStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/plans';

  private readonly state = signal<PlanState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly plans = computed(() => this.state().items);
  readonly selectedPlan = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activePlans = computed(() => this.plans().filter((p) => p.is_active));

  // Actions
  loadAll(params?: { scheme_id?: string; plan_type?: string; active_only?: boolean }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.scheme_id) queryParams.set('scheme_id', params.scheme_id);
    if (params?.plan_type) queryParams.set('plan_type', params.plan_type);
    if (params?.active_only) queryParams.set('active_only', 'true');

    const url = queryParams.toString() ? `${this.apiUrl}?${queryParams}` : this.apiUrl;

    this.http.get<ApiResponse<MedicalPlan[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadByScheme(schemeId: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http
      .get<ApiResponse<MedicalPlan[]>>(`/api/v1/medical/schemes/${schemeId}/plans`)
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

    return this.http.get<ApiResponse<MedicalPlan>>(`${this.apiUrl}/${id}`).pipe(
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

  loadDropdown(schemeId?: string) {
    const url = schemeId
      ? `${this.apiUrl}/dropdown?scheme_id=${schemeId}`
      : `${this.apiUrl}/dropdown`;
    return this.http.get<ApiResponse<DropdownOption[]>>(url);
  }

  create(plan: Partial<MedicalPlan>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<MedicalPlan>>(this.apiUrl, plan).pipe(
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

  update(id: string, changes: Partial<MedicalPlan>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<MedicalPlan>>(`${this.apiUrl}/${id}`, changes).pipe(
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

  activate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<MedicalPlan>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
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

  clone(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<MedicalPlan>>(`${this.apiUrl}/${id}/clone`, {}).pipe(
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

  exportPdf(id: string) {
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/${id}/export-pdf`);
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

  compare(planIds: string[]) {
    return this.http.post<
      ApiResponse<{ plans: MedicalPlan[]; comparison: Record<string, unknown> }>
    >(`${this.apiUrl}/compare`, { plan_ids: planIds });
  }

  generateQuickQuote(payload: { plan_id: string; members: any[] }) {
    return this.http.post<ApiResponse<any>>('/api/v1/medical/quotes', payload);
  }

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
