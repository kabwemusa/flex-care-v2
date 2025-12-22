// @medical/data/services/scheme-store.service.ts
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { MedicalScheme } from 'medical-feature';
import { ApiResponse } from '../models/api-reponse';

@Injectable({ providedIn: 'root' })
export class SchemeStore {
  private http = inject(HttpClient);
  private apiUrl = '/api/v1/medical/schemes';

  private state = signal<{
    items: MedicalScheme[];
    loading: boolean;
  }>({
    items: [],
    loading: false,
  });

  readonly schemes = computed(() => this.state().items);
  readonly isLoading = computed(() => this.state().loading);

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<MedicalScheme[]>>(this.apiUrl).subscribe({
      next: (res) => this.state.update((s) => ({ ...s, items: res.data, loading: false })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  addScheme(newScheme: MedicalScheme) {
    return this.http.post<ApiResponse<MedicalScheme>>(this.apiUrl, newScheme).pipe(
      tap((res) => {
        this.state.update((s) => ({ ...s, items: [res.data, ...s.items] }));
      })
    );
  }

  updateScheme(id: number, changes: Partial<MedicalScheme>) {
    return this.http.patch<ApiResponse<MedicalScheme>>(`${this.apiUrl}/${id}`, changes).pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          items: s.items.map((item) => (item.id === id ? res.data : item)),
        }));
      })
    );
  }

  deleteScheme(id: number) {
    return this.http.delete<ApiResponse<any>>(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((item) => item.id !== id),
        }));
      })
    );
  }
  planCount(id: number) {
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/${id}/plans`);
  }
}
