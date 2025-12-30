// libs/medical/data/src/lib/stores/scheme.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { MedicalScheme, PaginatedResponse } from '../models/medical-interfaces';

interface SchemeState {
  items: MedicalScheme[];
  selected: MedicalScheme | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class SchemeListStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/schemes';

  private readonly state = signal<SchemeState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly schemes = computed(() => this.state().items);
  readonly selectedScheme = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activeSchemes = computed(() => this.schemes().filter((s) => s.is_active));
  readonly totalPlans = computed(() =>
    this.schemes().reduce((sum, s) => sum + (s.plans_count || 0), 0)
  );

  // Actions
  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http.get<ApiResponse<MedicalScheme[]>>(this.apiUrl).subscribe({
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

    return this.http.get<ApiResponse<MedicalScheme>>(`${this.apiUrl}/${id}`).pipe(
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
    return this.http.get<ApiResponse<MedicalScheme[]>>(`${this.apiUrl}/dropdown`);
  }

  create(scheme: Partial<MedicalScheme>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<MedicalScheme>>(this.apiUrl, scheme).pipe(
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

  update(id: string, changes: Partial<MedicalScheme>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<MedicalScheme>>(`${this.apiUrl}/${id}`, changes).pipe(
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

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
