// libs/medical/data/src/lib/stores/discount.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import { DiscountRule, PromoCode } from '../models/medical-interfaces';

interface DiscountState {
  rules: DiscountRule[];
  promoCodes: PromoCode[];
  selected: DiscountRule | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class DiscountListStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical';

  private readonly state = signal<DiscountState>({
    rules: [],
    promoCodes: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly discountRules = computed(() => this.state().rules);
  readonly promoCodes = computed(() => this.state().promoCodes);
  readonly selectedRule = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly discounts = computed(() =>
    this.discountRules().filter((r) => r.adjustment_type === 'discount')
  );
  readonly loadings = computed(() =>
    this.discountRules().filter((r) => r.adjustment_type === 'loading')
  );
  readonly activePromoCodes = computed(() => this.promoCodes().filter((p) => p.is_usable));

  // =========================================================================
  // DISCOUNT RULES
  // =========================================================================
  loadRules(params?: { adjustment_type?: string; scheme_id?: string; plan_id?: string }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.adjustment_type) queryParams.set('adjustment_type', params.adjustment_type);
    if (params?.scheme_id) queryParams.set('scheme_id', params.scheme_id);
    if (params?.plan_id) queryParams.set('plan_id', params.plan_id);

    const url = queryParams.toString()
      ? `${this.apiUrl}/discount-rules?${queryParams}`
      : `${this.apiUrl}/discount-rules`;

    this.http.get<ApiResponse<DiscountRule[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          rules: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadOneRule(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<DiscountRule>>(`${this.apiUrl}/discount-rules/${id}`).pipe(
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

  createRule(rule: Partial<DiscountRule>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<DiscountRule>>(`${this.apiUrl}/discount-rules`, rule).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            rules: [res.data, ...s.rules],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  updateRule(id: string, changes: Partial<DiscountRule>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .put<ApiResponse<DiscountRule>>(`${this.apiUrl}/discount-rules/${id}`, changes)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              rules: s.rules.map((r) => (r.id === id ? res.data : r)),
              selected: s.selected?.id === id ? res.data : s.selected,
              saving: false,
            })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  deleteRule(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/discount-rules/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          rules: s.rules.filter((r) => r.id !== id),
        }))
      )
    );
  }

  simulateDiscount(premium: number, ruleIds: string[]) {
    return this.http.post<
      ApiResponse<{
        original_premium: number;
        discounts: { rule_id: string; amount: number }[];
        total_discount: number;
        final_premium: number;
      }>
    >(`${this.apiUrl}/discounts/simulate`, { premium, discount_rule_ids: ruleIds });
  }

  // =========================================================================
  // PROMO CODES
  // =========================================================================
  loadPromoCodes(params?: { discount_rule_id?: string; active_only?: boolean }) {
    this.state.update((s) => ({ ...s, loading: true }));

    const queryParams = new URLSearchParams();
    if (params?.discount_rule_id) queryParams.set('discount_rule_id', params.discount_rule_id);
    if (params?.active_only) queryParams.set('active_only', 'true');

    const url = queryParams.toString()
      ? `${this.apiUrl}/promo-codes?${queryParams}`
      : `${this.apiUrl}/promo-codes`;

    this.http.get<ApiResponse<PromoCode[]>>(url).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          promoCodes: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  createPromoCode(promoCode: Partial<PromoCode>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<PromoCode>>(`${this.apiUrl}/promo-codes`, promoCode).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            promoCodes: [res.data, ...s.promoCodes],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  updatePromoCode(id: string, changes: Partial<PromoCode>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<PromoCode>>(`${this.apiUrl}/promo-codes/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            promoCodes: s.promoCodes.map((p) => (p.id === id ? res.data : p)),
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  deletePromoCode(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/promo-codes/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          promoCodes: s.promoCodes.filter((p) => p.id !== id),
        }))
      )
    );
  }

  validatePromoCode(code: string, schemeId?: string, planId?: string) {
    return this.http.post<
      ApiResponse<{ valid: boolean; discount_value: string; discount_name: string }>
    >(`${this.apiUrl}/promo-codes/validate`, { code, scheme_id: schemeId, plan_id: planId });
  }

  applyPromoCode(code: string, premium: number, planId?: string) {
    return this.http.post<
      ApiResponse<{
        success: boolean;
        original_premium: number;
        discount_amount: number;
        final_premium: number;
      }>
    >(`${this.apiUrl}/promo-codes/apply`, { code, premium, plan_id: planId });
  }

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }
}
