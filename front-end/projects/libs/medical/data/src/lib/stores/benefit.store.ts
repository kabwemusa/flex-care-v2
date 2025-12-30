// libs/medical/data/src/lib/stores/benefit.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { Benefit, BenefitCategory, PlanBenefit } from '../models/medical-interfaces';

interface BenefitState {
  categories: BenefitCategory[];
  benefits: Benefit[];
  planBenefits: PlanBenefit[];
  selected: Benefit | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class BenefitStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical';

  private readonly state = signal<BenefitState>({
    categories: [],
    benefits: [],
    planBenefits: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly categories = computed(() => this.state().categories);
  readonly benefits = computed(() => this.state().benefits);
  readonly planBenefits = computed(() => this.state().planBenefits);
  readonly selectedBenefit = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly benefitTree = computed(() => {
    const cats = this.categories();
    const bens = this.benefits();
    return cats.map((cat) => ({
      ...cat,
      benefits: bens.filter((b) => b.category_id === cat.id && !b.parent_id),
    }));
  });

  // =========================================================================
  // CATEGORIES
  // =========================================================================
  loadCategories() {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http.get<ApiResponse<BenefitCategory[]>>(`${this.apiUrl}/benefit-categories`).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          categories: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  createCategory(category: Partial<BenefitCategory>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<BenefitCategory>>(`${this.apiUrl}/benefit-categories`, category)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              categories: [...s.categories, res.data],
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // BENEFITS CATALOG
  // =========================================================================
  loadBenefits(params?: { category_id?: string; benefit_type?: string }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.category_id) queryParams.set('category_id', params.category_id);
    if (params?.benefit_type) queryParams.set('benefit_type', params.benefit_type);

    const url = queryParams.toString()
      ? `${this.apiUrl}/benefits?${queryParams}`
      : `${this.apiUrl}/benefits`;

    this.http.get<ApiResponse<Benefit[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          benefits: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadBenefitTree() {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http.get<ApiResponse<BenefitCategory[]>>(`${this.apiUrl}/benefits/tree`).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          categories: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadOneBenefit(id: string) {
    return this.http
      .get<ApiResponse<Benefit>>(`${this.apiUrl}/benefits/${id}`)
      .pipe(tap((res) => this.state.update((s) => ({ ...s, selected: res.data }))));
  }

  createBenefit(benefit: Partial<Benefit>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Benefit>>(`${this.apiUrl}/benefits`, benefit).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            benefits: [...s.benefits, res.data],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  updateBenefit(id: string, changes: Partial<Benefit>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<Benefit>>(`${this.apiUrl}/benefits/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            benefits: s.benefits.map((b) => (b.id === id ? res.data : b)),
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  deleteBenefit(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/benefits/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          benefits: s.benefits.filter((b) => b.id !== id),
        }))
      )
    );
  }

  // =========================================================================
  // PLAN BENEFITS
  // =========================================================================
  loadPlanBenefits(planId: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http.get<ApiResponse<PlanBenefit[]>>(`${this.apiUrl}/plans/${planId}/benefits`).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          planBenefits: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  addBenefitToPlan(planId: string, data: Partial<PlanBenefit>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<PlanBenefit>>(`${this.apiUrl}/plans/${planId}/benefits`, data)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              planBenefits: [...s.planBenefits, res.data],
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  updatePlanBenefit(id: string, changes: Partial<PlanBenefit>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .put<ApiResponse<PlanBenefit>>(`${this.apiUrl}/plan-benefits/${id}`, changes)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              planBenefits: s.planBenefits.map((pb) => (pb.id === id ? res.data : pb)),
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeBenefitFromPlan(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/plan-benefits/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          planBenefits: s.planBenefits.filter((pb) => pb.id !== id),
        }))
      )
    );
  }

  bulkAddBenefits(planId: string, benefits: { benefit_id: string; is_covered?: boolean }[]) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<{ added_count: number }>>(`${this.apiUrl}/plans/${planId}/benefits/bulk`, {
        benefits,
      })
      .pipe(tap(() => this.state.update((s) => ({ ...s, saving: false }))));
  }

  // Benefit schedule
  loadBenefitSchedule(planId: string, memberType?: string) {
    const url = memberType
      ? `${this.apiUrl}/plans/${planId}/benefit-schedule?member_type=${memberType}`
      : `${this.apiUrl}/plans/${planId}/benefit-schedule`;
    return this.http.get<ApiResponse<Record<string, PlanBenefit[]>>>(url);
  }

  // Eligibility check
  checkEligibility(data: {
    plan_id: string;
    benefit_id: string;
    member_type: string;
    member_age: number;
    cover_start_date?: string;
    claim_amount?: number;
    used_amount?: number;
  }) {
    return this.http.post<
      ApiResponse<{
        eligible: boolean;
        reason?: string;
        limit?: number;
        used?: number;
        remaining?: number;
      }>
    >(`${this.apiUrl}/benefits/check-eligibility`, data);
  }

  clearPlanBenefits() {
    this.state.update((s) => ({ ...s, planBenefits: [] }));
  }
}
