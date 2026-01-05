// libs/medical/data/src/lib/stores/policy.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse, Policy, Member, PolicyAddon } from '../models/medical-interfaces';

interface PolicyState {
  items: Policy[];
  selected: Policy | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class PolicyStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/policies';

  private readonly state = signal<PolicyState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly policies = computed(() => this.state().items);
  readonly selectedPolicy = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed selectors
  readonly activePolicies = computed(() => this.policies().filter((p) => p.status === 'active'));
  readonly suspendedPolicies = computed(() =>
    this.policies().filter((p) => p.status === 'suspended')
  );
  readonly forRenewal = computed(() =>
    this.policies().filter((p) => {
      if (!p.expiry_date) return false;
      const daysToExpiry = Math.ceil(
        (new Date(p.expiry_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)
      );
      return daysToExpiry <= 60 && daysToExpiry > 0 && p.status === 'active';
    })
  );

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: {
    search?: string;
    status?: string;
    policy_type?: string;
    scheme_id?: string;
    plan_id?: string;
    group_id?: string;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.policy_type) params = params.set('policy_type', filters.policy_type);
    if (filters?.scheme_id) params = params.set('scheme_id', filters.scheme_id);
    if (filters?.plan_id) params = params.set('plan_id', filters.plan_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);

    this.http.get<ApiResponse<Policy[]>>(this.apiUrl, { params }).subscribe({
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

    return this.http.get<ApiResponse<Policy>>(`${this.apiUrl}/${id}`).pipe(
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
    return this.http.get<ApiResponse<Policy[]>>(`${this.apiUrl}/dropdown`);
  }

  // =========================================================================
  // UPDATE OPERATIONS (No create - policies come from application conversion)
  // =========================================================================

  update(id: string, changes: Partial<Policy>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<Policy>>(`${this.apiUrl}/${id}`, changes).pipe(
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

  // =========================================================================
  // STATUS ACTIONS
  // =========================================================================

  activate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
      tap({
        next: (res) => this.updatePolicyInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  suspend(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/suspend`, { reason }).pipe(
      tap({
        next: (res) => this.updatePolicyInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  reinstate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/reinstate`, {}).pipe(
      tap({
        next: (res) => this.updatePolicyInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  cancel(id: string, reason: string, effectiveDate?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/cancel`, {
        reason,
        effective_date: effectiveDate,
      })
      .pipe(
        tap({
          next: (res) => this.updatePolicyInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // ADDON OPERATIONS
  // =========================================================================

  addAddon(policyId: string, addonId: string, addonRateId?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Policy>>(`${this.apiUrl}/${policyId}/addons`, {
        addon_id: addonId,
        addon_rate_id: addonRateId,
      })
      .pipe(
        tap({
          next: (res) => this.updatePolicyInState(policyId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeAddon(policyId: string, addonId: string) {
    return this.http
      .delete<ApiResponse<Policy>>(`${this.apiUrl}/${policyId}/addons/${addonId}`)
      .pipe(
        tap({
          next: (res) => this.updatePolicyInState(policyId, res.data),
        })
      );
  }

  // =========================================================================
  // PREMIUM
  // =========================================================================

  calculatePremium(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/calculate-premium`, {}).pipe(
      tap({
        next: (res) => this.updatePolicyInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  private updatePolicyInState(id: string, data: Policy) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
