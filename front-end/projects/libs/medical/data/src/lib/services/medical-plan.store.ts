// @medical/data/plan.store.ts
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap, catchError } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { FeedbackService } from 'shared';

export interface MedicalPlan {
  id: number;
  scheme_id: number;
  name: string;
  code: string;
  type: 'Individual' | 'Family' | 'SME' | 'Corporate';
  features_count?: number;
  addons_count?: number; // Added
  features?: any[]; // Benefits with pivot limits
  addons?: any[]; // Linked optional covers
  scheme?: { name: string };
}

@Injectable({ providedIn: 'root' })
export class PlanStore {
  private http = inject(HttpClient);
  private feedback = inject(FeedbackService);
  private apiUrl = '/api/v1/medical/plans';

  private state = signal<{
    items: MedicalPlan[];
    loading: boolean;
    selectedSchemeId: number | null;
  }>({
    items: [],
    loading: false,
    selectedSchemeId: null,
  });

  // Public Selectors
  readonly plans = computed(() => {
    const schemeId = this.state().selectedSchemeId;
    return schemeId
      ? this.state().items.filter((p) => p.scheme_id === schemeId)
      : this.state().items;
  });

  readonly isLoading = computed(() => this.state().loading);

  setFilter(schemeId: number | null) {
    this.state.update((s) => ({ ...s, selectedSchemeId: schemeId }));
  }

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<MedicalPlan[]>>(this.apiUrl).subscribe((res) => {
      this.state.update((s) => ({ ...s, items: res.data, loading: false }));
    });
  }

  upsertPlan(plan: MedicalPlan) {
    const request = plan.id
      ? this.http.put<ApiResponse<MedicalPlan>>(`${this.apiUrl}/${plan.id}`, plan)
      : this.http.post<ApiResponse<MedicalPlan>>(this.apiUrl, plan);

    return request.pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          items: plan.id
            ? s.items.map((p) => (p.id === res.data.id ? res.data : p))
            : [res.data, ...s.items],
        }));
        this.feedback.success(res.message);
      })
    );
  }
  deletePlan(id: number) {
    return this.http.delete<ApiResponse<any>>(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((item) => item.id !== id),
        }));
      })
    );
  }
  // Add to PlanStore class
  syncFeatures(planId: number, features: any) {
    return this.http.post<ApiResponse<any>>(`${this.apiUrl}/${planId}/features`, { features }).pipe(
      tap(() => {
        this.feedback.success('Plan benefits updated');
        this.loadAll(); // Refresh to update feature counts
      })
    );
  }

  // @medical/data/plan.store.ts

  syncAddons(planId: number, addonIds: number[]) {
    return this.http
      .post<ApiResponse<any>>(`${this.apiUrl}/${planId}/addons`, { addon_ids: addonIds })
      .pipe(
        tap(() => {
          this.feedback.success('Optional add-ons updated');
          this.loadAll();
        })
      );
  }
}
