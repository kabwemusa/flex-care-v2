// @medical/data/feature.store.ts
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { FeedbackService } from 'shared';

export interface MedicalFeature {
  id: number;
  name: string;
  category: 'Clinical' | 'In-Patient' | 'Out-Patient' | 'Specialist' | 'Dental' | 'Optical';
  code: string;
}

@Injectable({ providedIn: 'root' })
export class FeatureStore {
  private http = inject(HttpClient);
  private feedback = inject(FeedbackService);
  private apiUrl = '/api/v1/medical/features';

  private state = signal<{ items: MedicalFeature[]; loading: boolean }>({
    items: [],
    loading: false,
  });

  readonly features = computed(() => this.state().items);
  readonly isLoading = computed(() => this.state().loading);

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<MedicalFeature[]>>(this.apiUrl).subscribe((res) => {
      this.state.update((s) => ({ ...s, items: res.data, loading: false }));
    });
  }

  upsert(feature: Partial<MedicalFeature>) {
    const request = feature.id
      ? this.http.patch<ApiResponse<MedicalFeature>>(`${this.apiUrl}/${feature.id}`, feature)
      : this.http.post<ApiResponse<MedicalFeature>>(this.apiUrl, feature);

    return request.pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          items: feature.id
            ? s.items.map((f) => (f.id === res.data.id ? res.data : f))
            : [res.data, ...s.items],
        }));
        this.feedback.success(res.message);
      })
    );
  }

  delete(id: number) {
    return this.http.delete<ApiResponse<any>>(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({ ...s, items: s.items.filter((f) => f.id !== id) }));
        this.feedback.success('Feature removed from library');
      })
    );
  }
}
