// @medical/data/addon.store.ts
import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';

export interface MedicalAddon {
  id?: number;
  name: string;
  description: string;
  price: number;
  is_mandatory: boolean;
}

@Injectable({ providedIn: 'root' })
export class AddonStore {
  private http = inject(HttpClient);
  private apiUrl = '/api/v1/medical/addons';

  private state = signal<{ items: MedicalAddon[]; loading: boolean }>({
    items: [],
    loading: false,
  });

  readonly addons = computed(() => this.state().items);
  readonly isLoading = computed(() => this.state().loading);

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<MedicalAddon[]>>(this.apiUrl).subscribe((res) => {
      this.state.update((s) => ({ ...s, items: res.data, loading: false }));
    });
  }

  upsert(addon: Partial<MedicalAddon>) {
    const request = addon.id
      ? this.http.put<ApiResponse<MedicalAddon>>(`${this.apiUrl}/${addon.id}`, addon)
      : this.http.post<ApiResponse<MedicalAddon>>(this.apiUrl, addon);

    return request.pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          items: addon.id
            ? s.items.map((a) => (a.id === res.data.id ? res.data : a))
            : [res.data, ...s.items],
        }));
      })
    );
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((a) => a.id !== id),
        }));
      })
    );
  }
}
