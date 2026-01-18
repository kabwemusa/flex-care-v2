// libs/medical/data/src/lib/stores/member.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  Member,
  MemberLoading,
  MemberExclusion,
  CreateMemberPayload,
  MemberBenefitSummary,
  BenefitHistoryResponse,
} from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse } from 'medical-data';

export interface MemberPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

export interface MemberFilters {
  search?: string;
  status?: string;
  policy_id?: string;
  member_type?: string;
  principals_only?: boolean;
  dependents_only?: boolean;
  page?: number;
  per_page?: number;
}

interface MemberState {
  items: Member[];
  selected: Member | null;
  loading: boolean;
  saving: boolean;
  pagination: MemberPaginationMeta | null;
  filters: MemberFilters;
}

@Injectable({ providedIn: 'root' })
export class MemberStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/members';

  private readonly state = signal<MemberState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
    pagination: null,
    filters: {},
  });

  // Selectors
  readonly members = computed(() => this.state().items);
  readonly selectedMember = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly pagination = computed(() => this.state().pagination);
  readonly filters = computed(() => this.state().filters);

  // Pagination computed selectors
  readonly currentPage = computed(() => this.pagination()?.current_page ?? 1);
  readonly totalPages = computed(() => this.pagination()?.last_page ?? 1);
  readonly totalItems = computed(() => this.pagination()?.total ?? 0);
  readonly pageSize = computed(() => this.pagination()?.per_page ?? 10);
  readonly hasNextPage = computed(() => this.currentPage() < this.totalPages());
  readonly hasPrevPage = computed(() => this.currentPage() > 1);

  // Computed selectors
  readonly activeMembers = computed(() => this.members().filter((m) => m.status === 'active'));
  readonly principalMembers = computed(() => this.members().filter((m) => m.is_principal));
  readonly dependentMembers = computed(() => this.members().filter((m) => m.is_dependent));

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: MemberFilters) {
    this.state.update((s) => ({ ...s, loading: true, filters: filters ?? {} }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.member_type) params = params.set('member_type', filters.member_type);
    if (filters?.principals_only) params = params.set('principals_only', 'true');
    if (filters?.dependents_only) params = params.set('dependents_only', 'true');
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http.get<PaginatedResponse<Member>>(this.apiUrl, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          pagination: res.meta ?? null,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  // Load specific page
  loadPage(page: number) {
    if (page < 1 || page > this.totalPages() || this.isLoading()) return;

    const currentFilters = this.filters();
    this.loadAll({
      ...currentFilters,
      page,
    });
  }

  // Change page size
  setPageSize(perPage: number) {
    const currentFilters = this.filters();
    this.loadAll({
      ...currentFilters,
      per_page: perPage,
      page: 1,
    });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Member>>(`${this.apiUrl}/${id}`).pipe(
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

  loadByPolicy(policyId: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http
      .get<ApiResponse<Member[]>>(`/api/v1/medical/policies/${policyId}/members`)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              items: res.data,
              loading: false,
            })),
          error: () => this.state.update((s) => ({ ...s, loading: false })),
        })
      );
  }

  // =========================================================================
  // CRUD OPERATIONS
  // =========================================================================

  create(data: CreateMemberPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(this.apiUrl, data).pipe(
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

  update(id: string, changes: Partial<CreateMemberPayload>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<Member>>(`${this.apiUrl}/${id}`, changes).pipe(
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
          selected: s.selected?.id === id ? null : s.selected,
        }))
      )
    );
  }

  // =========================================================================
  // STATUS ACTIONS
  // =========================================================================

  activate(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
      tap({
        next: (res) => this.updateMemberInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  suspend(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/suspend`, { reason }).pipe(
      tap({
        next: (res) => this.updateMemberInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  terminate(id: string, reason: string, effectiveDate?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Member>>(`${this.apiUrl}/${id}/terminate`, {
        reason,
        effective_date: effectiveDate,
      })
      .pipe(
        tap({
          next: (res) => this.updateMemberInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // CARD MANAGEMENT
  // =========================================================================

  issueCard(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/issue-card`, {}).pipe(
      tap({
        next: (res) => this.updateMemberInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  activateCard(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/activate-card`, {}).pipe(
      tap({
        next: (res) => this.updateMemberInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  blockCard(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/block-card`, { reason }).pipe(
      tap({
        next: (res) => this.updateMemberInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // LOADING & EXCLUSION OPERATIONS
  // =========================================================================

  addLoading(memberId: string, loading: Partial<MemberLoading>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${memberId}/loadings`, loading).pipe(
      tap({
        next: (res) => this.updateMemberInState(memberId, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  removeLoading(memberId: string, loadingId: string) {
    return this.http
      .delete<ApiResponse<Member>>(`${this.apiUrl}/${memberId}/loadings/${loadingId}`)
      .pipe(
        tap({
          next: (res) => this.updateMemberInState(memberId, res.data),
        })
      );
  }

  addExclusion(memberId: string, exclusion: Partial<MemberExclusion>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Member>>(`${this.apiUrl}/${memberId}/exclusions`, exclusion)
      .pipe(
        tap({
          next: (res) => this.updateMemberInState(memberId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeExclusion(memberId: string, exclusionId: string) {
    return this.http
      .delete<ApiResponse<Member>>(`${this.apiUrl}/${memberId}/exclusions/${exclusionId}`)
      .pipe(
        tap({
          next: (res) => this.updateMemberInState(memberId, res.data),
        })
      );
  }

  // =========================================================================
  // BENEFIT UTILIZATION
  // =========================================================================

  private readonly benefitsState = signal<{
    loading: boolean;
    summary: MemberBenefitSummary | null;
    history: BenefitHistoryResponse | null;
    historyLoading: boolean;
  }>({
    loading: false,
    summary: null,
    history: null,
    historyLoading: false,
  });

  // Benefit selectors
  readonly benefitSummary = computed(() => this.benefitsState().summary);
  readonly benefitsLoading = computed(() => this.benefitsState().loading);
  readonly benefitHistory = computed(() => this.benefitsState().history);
  readonly benefitHistoryLoading = computed(() => this.benefitsState().historyLoading);

  /**
   * Load benefit balances for a member
   */
  loadBenefitBalances(memberId: string) {
    this.benefitsState.update((s) => ({ ...s, loading: true, summary: null }));

    return this.http
      .get<ApiResponse<MemberBenefitSummary>>(`${this.apiUrl}/${memberId}/benefits`)
      .pipe(
        tap({
          next: (res) => {
            this.benefitsState.update((s) => ({
              ...s,
              loading: false,
              summary: res.data,
            }));
          },
          error: () => {
            this.benefitsState.update((s) => ({ ...s, loading: false }));
          },
        })
      );
  }

  /**
   * Load benefit utilization history for a specific benefit
   */
  loadBenefitHistory(memberId: string, benefitId: string) {
    this.benefitsState.update((s) => ({ ...s, historyLoading: true, history: null }));

    return this.http
      .get<ApiResponse<BenefitHistoryResponse>>(`${this.apiUrl}/${memberId}/benefits/${benefitId}/history`)
      .pipe(
        tap({
          next: (res) => {
            this.benefitsState.update((s) => ({
              ...s,
              historyLoading: false,
              history: res.data,
            }));
          },
          error: () => {
            this.benefitsState.update((s) => ({ ...s, historyLoading: false }));
          },
        })
      );
  }

  /**
   * Clear benefit state
   */
  clearBenefits() {
    this.benefitsState.set({
      loading: false,
      summary: null,
      history: null,
      historyLoading: false,
    });
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  private updateMemberInState(id: string, data: Member) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
