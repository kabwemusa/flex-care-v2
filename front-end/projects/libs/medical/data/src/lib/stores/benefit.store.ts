// libs/medical/data/src/lib/stores/benefit.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { Benefit, PlanBenefit } from '../models/medical-interfaces';

interface BenefitTypeGroup {
  benefit_type: string;
  label: string;
  benefits: Benefit[];
}

interface BenefitState {
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
    benefits: [],
    planBenefits: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly benefits = computed(() => this.state().benefits);
  readonly planBenefits = computed(() => this.state().planBenefits);
  readonly selectedBenefit = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed - group benefits by benefit_type
  readonly benefitTree = computed(() => {
    const bens = this.benefits();
    const rootBenefits = bens.filter((b) => !b.parent_id);
    const grouped = new Map<string, Benefit[]>();

    for (const b of rootBenefits) {
      const type = b.benefit_type;
      if (!grouped.has(type)) grouped.set(type, []);
      grouped.get(type)!.push(b);
    }

    return Array.from(grouped.entries()).map(([type, benefits]) => ({
      benefit_type: type,
      label: benefits[0]?.benefit_type_label || type,
      benefits,
    }));
  });

  // =========================================================================
  // BENEFITS CATALOG
  // =========================================================================
  loadBenefits(params?: { benefit_type?: string }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
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

    this.http.get<ApiResponse<BenefitTypeGroup[]>>(`${this.apiUrl}/benefits/tree`).subscribe({
      next: (res) => {
        // Flatten the tree groups into a flat benefits list
        const allBenefits = res.data.flatMap((group) => group.benefits as Benefit[]);
        this.state.update((s) => ({
          ...s,
          benefits: allBenefits,
          loading: false,
        }));
      },
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

    return this.http
      .get<ApiResponse<PlanBenefit[]>>(`${this.apiUrl}/plans/${planId}/benefits`)
      .subscribe({
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
