// libs/medical/data/src/lib/stores/endorsement.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  Endorsement,
  EndorsementFilters,
  EndorsementStats,
  CreateEndorsementPayload,
} from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse } from 'medical-data';

export interface EndorsementPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

interface EndorsementState {
  items: Endorsement[];
  selected: Endorsement | null;
  loading: boolean;
  saving: boolean;
  pagination: EndorsementPaginationMeta | null;
  filters: EndorsementFilters;
  stats: EndorsementStats | null;
}

@Injectable({ providedIn: 'root' })
export class EndorsementStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/endorsements';

  private readonly state = signal<EndorsementState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
    pagination: null,
    filters: {},
    stats: null,
  });

  // Selectors
  readonly endorsements = computed(() => this.state().items);
  readonly selectedEndorsement = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly pagination = computed(() => this.state().pagination);
  readonly filters = computed(() => this.state().filters);
  readonly stats = computed(() => this.state().stats);

  // Pagination computed selectors
  readonly currentPage = computed(() => this.pagination()?.current_page ?? 1);
  readonly totalPages = computed(() => this.pagination()?.last_page ?? 1);
  readonly totalItems = computed(() => this.pagination()?.total ?? 0);
  readonly pageSize = computed(() => this.pagination()?.per_page ?? 20);
  readonly hasNextPage = computed(() => this.currentPage() < this.totalPages());
  readonly hasPrevPage = computed(() => this.currentPage() > 1);

  // Computed filtered selectors
  readonly pendingEndorsements = computed(() =>
    this.endorsements().filter((e) => e.status === 'pending')
  );
  readonly approvedEndorsements = computed(() =>
    this.endorsements().filter((e) => e.status === 'approved')
  );
  readonly processedEndorsements = computed(() =>
    this.endorsements().filter((e) => e.status === 'processed')
  );
  readonly rejectedEndorsements = computed(() =>
    this.endorsements().filter((e) => e.status === 'rejected')
  );

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: EndorsementFilters) {
    this.state.update((s) => ({ ...s, loading: true, filters: filters ?? {} }));

    let params = new HttpParams();
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.endorsement_type)
      params = params.set('endorsement_type', filters.endorsement_type);
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.from_date) params = params.set('from_date', filters.from_date);
    if (filters?.to_date) params = params.set('to_date', filters.to_date);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters?.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters?.sort_order) params = params.set('sort_order', filters.sort_order);

    this.http.get<PaginatedResponse<Endorsement>>(this.apiUrl, { params }).subscribe({
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

  loadForPolicy(policyId: string, filters?: Partial<EndorsementFilters>) {
    this.state.update((s) => ({
      ...s,
      loading: true,
      filters: { ...filters, policy_id: policyId },
    }));

    let params = new HttpParams();
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.endorsement_type) params = params.set('type', filters.endorsement_type);
    if (filters?.from_date) params = params.set('from_date', filters.from_date);
    if (filters?.to_date) params = params.set('to_date', filters.to_date);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http
      .get<PaginatedResponse<Endorsement>>(`/api/v1/medical/policies/${policyId}/endorsements`, {
        params,
      })
      .subscribe({
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

  // Load next page (for lazy loading / infinite scroll)
  loadNextPage() {
    if (!this.hasNextPage() || this.isLoading()) return;

    const currentFilters = this.filters();
    this.loadAll({
      ...currentFilters,
      page: this.currentPage() + 1,
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
      page: 1, // Reset to first page when changing page size
    });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Endorsement>>(`${this.apiUrl}/${id}`).pipe(
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

  loadStats() {
    this.http.get<ApiResponse<EndorsementStats>>(`${this.apiUrl}/stats`).subscribe({
      next: (res) => this.state.update((s) => ({ ...s, stats: res.data })),
    });
  }

  // =========================================================================
  // CREATE OPERATIONS
  // =========================================================================

  create(data: CreateEndorsementPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Endorsement>>(this.apiUrl, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: [res.data, ...s.items],
            selected: res.data,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // WORKFLOW ACTIONS
  // =========================================================================

  approve(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Endorsement>>(`${this.apiUrl}/${id}/approve`, {}).pipe(
      tap({
        next: (res) => this.updateEndorsementInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  reject(id: string, reason: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Endorsement>>(`${this.apiUrl}/${id}/reject`, { reason }).pipe(
      tap({
        next: (res) => this.updateEndorsementInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  process(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Endorsement>>(`${this.apiUrl}/${id}/process`, {}).pipe(
      tap({
        next: (res) => this.updateEndorsementInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  cancel(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Endorsement>>(`${this.apiUrl}/${id}/cancel`, { reason }).pipe(
      tap({
        next: (res) => this.updateEndorsementInState(id, res.data),
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

  clearItems() {
    this.state.update((s) => ({ ...s, items: [], pagination: null }));
  }

  private updateEndorsementInState(id: string, data: Endorsement) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
