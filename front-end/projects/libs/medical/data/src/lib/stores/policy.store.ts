// libs/medical/data/src/lib/stores/policy.store.ts
// Policy Store - Signal-based state management

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap, catchError, of, finalize, Observable } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import {
  Policy,
  PolicyAddon,
  PolicyStats,
  CreatePolicyPayload,
  Member,
} from '../models/medical-interfaces';

interface PolicyFilters {
  status?: string;
  policy_type?: string;
  scheme_id?: string;
  plan_id?: string;
  group_id?: string;
  expiring_in_days?: number;
}

interface PolicyState {
  items: Policy[];
  selected: Policy | null;
  stats: PolicyStats | null;
  filters: PolicyFilters;
  loading: boolean;
  saving: boolean;
  error: string | null;
}

@Injectable({ providedIn: 'root' })
export class PolicyStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/policies';

  private readonly state = signal<PolicyState>({
    items: [],
    selected: null,
    stats: null,
    filters: {},
    loading: false,
    saving: false,
    error: null,
  });

  // =========================================================================
  // SELECTORS
  // =========================================================================

  readonly policies = computed(() => this.state().items);
  readonly selectedPolicy = computed(() => this.state().selected);
  readonly stats = computed(() => this.state().stats);
  readonly filters = computed(() => this.state().filters);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly error = computed(() => this.state().error);

  // Computed helpers
  readonly activePolicies = computed(() => this.policies().filter((p) => p.status === 'active'));

  readonly pendingPayment = computed(() =>
    this.policies().filter((p) => p.status === 'pending_payment')
  );

  readonly expiringPolicies = computed(() => this.policies().filter((p) => p.is_expiring_soon));

  readonly totalPremium = computed(() =>
    this.activePolicies().reduce((sum, p) => sum + (p.net_premium || 0), 0)
  );

  // =========================================================================
  // ACTIONS
  // =========================================================================

  loadAll(filters?: PolicyFilters): void {
    this.state.update((s) => ({ ...s, loading: true, error: null, filters: filters || {} }));

    let params = new HttpParams();
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params = params.set(key, String(value));
        }
      });
    }

    this.http
      .get<ApiResponse<Policy[]>>(this.apiUrl, { params })
      .pipe(
        tap((res) => {
          this.state.update((s) => ({
            ...s,
            items: res.data ?? [],
            loading: false,
          }));
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            loading: false,
            error: err.error?.message || 'Failed to load policies',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  loadOne(id: string): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, loading: true, error: null }));

    return this.http.get<ApiResponse<Policy>>(`${this.apiUrl}/${id}`).pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          selected: res.data,
          loading: false,
        }));
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          loading: false,
          error: err.error?.message || 'Failed to load policy',
        }));
        return of(null);
      })
    );
  }

  loadStats(): void {
    this.http.get<ApiResponse<PolicyStats>>(`${this.apiUrl}/stats`).subscribe({
      next: (res) => {
        this.state.update((s) => ({ ...s, stats: res.data }));
      },
    });
  }

  loadByGroup(groupId: string): void {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http
      .get<ApiResponse<Policy[]>>(`/api/v1/medical/groups/${groupId}/policies`)
      .pipe(
        tap((res) => {
          this.state.update((s) => ({
            ...s,
            items: res.data ?? [],
            loading: false,
          }));
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            loading: false,
            error: err.error?.message || 'Failed to load group policies',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  create(payload: CreatePolicyPayload): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.post<ApiResponse<Policy>>(this.apiUrl, payload).pipe(
      tap((res) => {
        if (res.data) {
          this.state.update((s) => ({
            ...s,
            items: [res.data!, ...s.items],
            saving: false,
          }));
        }
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to create policy',
        }));
        return of(null);
      })
    );
  }

  update(
    id: string,
    payload: Partial<CreatePolicyPayload>
  ): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.put<ApiResponse<Policy>>(`${this.apiUrl}/${id}`, payload).pipe(
      tap((res) => {
        if (res.data) {
          this.state.update((s) => ({
            ...s,
            items: s.items.map((p) => (p.id === id ? res.data! : p)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          }));
        }
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to update policy',
        }));
        return of(null);
      })
    );
  }

  delete(id: string): Observable<ApiResponse<null> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((p) => p.id !== id),
          selected: s.selected?.id === id ? null : s.selected,
          saving: false,
        }));
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to delete policy',
        }));
        return of(null);
      })
    );
  }

  // =========================================================================
  // STATUS ACTIONS
  // =========================================================================

  activate(id: string): Observable<ApiResponse<Policy> | null> {
    return this.updateStatus(id, 'activate');
  }

  suspend(id: string, reason?: string): Observable<ApiResponse<Policy> | null> {
    return this.updateStatus(id, 'suspend', { reason });
  }

  cancel(id: string, reason: string, notes?: string): Observable<ApiResponse<Policy> | null> {
    return this.updateStatus(id, 'cancel', { cancellation_reason: reason, notes });
  }

  renew(id: string): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/renew`, {}).pipe(
      tap((res) => {
        if (res.data) {
          // Add the new policy to the list
          this.state.update((s) => ({
            ...s,
            items: [res.data!, ...s.items],
            saving: false,
          }));
        }
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to renew policy',
        }));
        return of(null);
      })
    );
  }

  private updateStatus(
    id: string,
    action: string,
    payload?: object
  ): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/${action}`, payload || {})
      .pipe(
        tap((res) => {
          if (res.data) {
            this.state.update((s) => ({
              ...s,
              items: s.items.map((p) => (p.id === id ? res.data! : p)),
              selected: s.selected?.id === id ? res.data : s.selected,
              saving: false,
            }));
          }
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            saving: false,
            error: err.error?.message || `Failed to ${action} policy`,
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // MEMBERS
  // =========================================================================

  loadMembers(policyId: string): Observable<ApiResponse<Member[]>> {
    return this.http.get<ApiResponse<Member[]>>(`${this.apiUrl}/${policyId}/members`);
  }

  // =========================================================================
  // ADDONS
  // =========================================================================

  loadAddons(policyId: string): Observable<ApiResponse<PolicyAddon[]>> {
    return this.http.get<ApiResponse<PolicyAddon[]>>(`${this.apiUrl}/${policyId}/addons`);
  }

  addAddon(policyId: string, addonId: string): Observable<ApiResponse<PolicyAddon> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<PolicyAddon>>(`${this.apiUrl}/${policyId}/addons`, { addon_id: addonId })
      .pipe(
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to add addon',
          }));
          return of(null);
        })
      );
  }

  removeAddon(policyId: string, addonId: string): Observable<ApiResponse<null> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${policyId}/addons/${addonId}`).pipe(
      finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          error: err.error?.message || 'Failed to remove addon',
        }));
        return of(null);
      })
    );
  }

  // =========================================================================
  // PROMO CODE
  // =========================================================================

  applyPromoCode(policyId: string, code: string): Observable<ApiResponse<Policy> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Policy>>(`${this.apiUrl}/${policyId}/apply-promo`, { code })
      .pipe(
        tap((res) => {
          if (res.data) {
            this.state.update((s) => ({
              ...s,
              items: s.items.map((p) => (p.id === policyId ? res.data! : p)),
              selected: s.selected?.id === policyId ? res.data : s.selected,
              saving: false,
            }));
          }
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            saving: false,
            error: err.error?.message || 'Invalid promo code',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // CERTIFICATE
  // =========================================================================

  downloadCertificate(policyId: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${policyId}/certificate`, {
      responseType: 'blob',
    });
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  clearSelected(): void {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  clearFilters(): void {
    this.state.update((s) => ({ ...s, filters: {} }));
    this.loadAll();
  }

  clearError(): void {
    this.state.update((s) => ({ ...s, error: null }));
  }
}
