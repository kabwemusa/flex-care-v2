// libs/medical/data/src/lib/stores/claim.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  Claim,
  ClaimFilters,
  ClaimStats,
  CreateClaimPayload,
  AdjudicateLinePayload,
  RecordPaymentPayload,
  CheckBenefitEligibilityPayload,
  BenefitEligibilityResult,
} from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse } from '../models/api-reponse';

export interface ClaimPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

interface ClaimState {
  items: Claim[];
  selected: Claim | null;
  loading: boolean;
  saving: boolean;
  pagination: ClaimPaginationMeta | null;
  filters: ClaimFilters;
  stats: ClaimStats | null;
}

@Injectable({ providedIn: 'root' })
export class ClaimStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/claims';

  private readonly state = signal<ClaimState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
    pagination: null,
    filters: {},
    stats: null,
  });

  // Selectors
  readonly claims = computed(() => this.state().items);
  readonly selectedClaim = computed(() => this.state().selected);
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

  // Status-based computed selectors
  readonly pendingClaims = computed(() =>
    this.claims().filter((c) =>
      ['submitted', 'pending_documents', 'in_review', 'pending_approval'].includes(c.status)
    )
  );
  readonly approvedClaims = computed(() =>
    this.claims().filter((c) => ['approved', 'partially_approved'].includes(c.status))
  );
  readonly rejectedClaims = computed(() => this.claims().filter((c) => c.status === 'rejected'));
  readonly paidClaims = computed(() => this.claims().filter((c) => c.status === 'paid'));
  readonly flaggedClaims = computed(() => this.claims().filter((c) => c.is_flagged));

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: ClaimFilters) {
    this.state.update((s) => ({ ...s, loading: true, filters: filters ?? {} }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.claim_type) params = params.set('claim_type', filters.claim_type);
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.member_id) params = params.set('member_id', filters.member_id);
    if (filters?.assigned_to) params = params.set('assigned_to', filters.assigned_to);
    if (filters?.unassigned) params = params.set('unassigned', 'true');
    if (filters?.flagged) params = params.set('flagged', 'true');
    if (filters?.high_priority) params = params.set('high_priority', 'true');
    if (filters?.service_date_from)
      params = params.set('service_date_from', filters.service_date_from);
    if (filters?.service_date_to) params = params.set('service_date_to', filters.service_date_to);
    if (filters?.created_from) params = params.set('created_from', filters.created_from);
    if (filters?.created_to) params = params.set('created_to', filters.created_to);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters?.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters?.sort_order) params = params.set('sort_order', filters.sort_order);

    this.http.get<PaginatedResponse<Claim>>(this.apiUrl, { params }).subscribe({
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

  loadForPolicy(policyId: string, filters?: Partial<ClaimFilters>) {
    this.state.update((s) => ({
      ...s,
      loading: true,
      filters: { ...filters, policy_id: policyId },
    }));

    let params = new HttpParams();
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.claim_type) params = params.set('claim_type', filters.claim_type);
    if (filters?.service_date_from)
      params = params.set('service_date_from', filters.service_date_from);
    if (filters?.service_date_to) params = params.set('service_date_to', filters.service_date_to);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http
      .get<PaginatedResponse<Claim>>(`/api/v1/medical/policies/${policyId}/claims`, { params })
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

  loadForMember(memberId: string, filters?: Partial<ClaimFilters>) {
    this.state.update((s) => ({
      ...s,
      loading: true,
      filters: { ...filters, member_id: memberId },
    }));

    let params = new HttpParams();
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.claim_type) params = params.set('claim_type', filters.claim_type);
    if (filters?.page) params = params.set('page', filters.page.toString());
    if (filters?.per_page) params = params.set('per_page', filters.per_page.toString());

    this.http
      .get<PaginatedResponse<Claim>>(`/api/v1/medical/members/${memberId}/claims`, { params })
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

  loadPage(page: number) {
    if (page < 1 || page > this.totalPages() || this.isLoading()) return;
    const currentFilters = this.filters();
    this.loadAll({ ...currentFilters, page });
  }

  setPageSize(perPage: number) {
    const currentFilters = this.filters();
    this.loadAll({ ...currentFilters, per_page: perPage, page: 1 });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Claim>>(`${this.apiUrl}/${id}`).pipe(
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

  loadStats(filters?: { policy_id?: string; from_date?: string; to_date?: string }) {
    let params = new HttpParams();
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.from_date) params = params.set('from_date', filters.from_date);
    if (filters?.to_date) params = params.set('to_date', filters.to_date);

    this.http.get<ApiResponse<ClaimStats>>(`${this.apiUrl}/stats`, { params }).subscribe({
      next: (res) => this.state.update((s) => ({ ...s, stats: res.data })),
    });
  }

  // =========================================================================
  // CREATE / UPDATE OPERATIONS
  // =========================================================================

  create(data: CreateClaimPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Claim>>(this.apiUrl, data).pipe(
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

  update(id: string, data: Partial<CreateClaimPayload>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<Claim>>(`${this.apiUrl}/${id}`, data).pipe(
      tap({
        next: (res) => this.updateClaimInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // WORKFLOW ACTIONS
  // =========================================================================

  startReview(id: string, reviewerId?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/start-review`, { reviewer_id: reviewerId })
      .pipe(
        tap({
          next: (res) => this.updateClaimInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  requestDocuments(id: string, requiredDocuments: string[], notes?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/request-documents`, {
        required_documents: requiredDocuments,
        notes,
      })
      .pipe(
        tap({
          next: (res) => this.updateClaimInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  adjudicate(id: string, lineDecisions: AdjudicateLinePayload[]) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/adjudicate`, {
        line_decisions: lineDecisions,
      })
      .pipe(
        tap({
          next: (res) => this.updateClaimInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  approve(id: string, notes?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/approve`, { notes }).pipe(
      tap({
        next: (res) => this.updateClaimInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  reject(id: string, reason: string, notes?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/reject`, { reason, notes })
      .pipe(
        tap({
          next: (res) => this.updateClaimInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  recordPayment(id: string, payment: RecordPaymentPayload) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/pay`, payment).pipe(
      tap({
        next: (res) => this.updateClaimInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  close(id: string, notes?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/close`, { notes }).pipe(
      tap({
        next: (res) => this.updateClaimInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  assign(id: string, assignedTo: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Claim>>(`${this.apiUrl}/${id}/assign`, { assigned_to: assignedTo })
      .pipe(
        tap({
          next: (res) => this.updateClaimInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // CLAIM LINES
  // =========================================================================

  addLine(
    claimId: string,
    line: {
      service_description: string;
      claimed_amount: number;
      service_date?: string;
      service_code?: string;
      benefit_id?: string;
      quantity?: number;
      unit?: string;
      unit_price?: number;
    }
  ) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Claim>>(`${this.apiUrl}/${claimId}/lines`, line).pipe(
      tap({
        next: (res) => this.updateClaimInState(claimId, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // NOTES
  // =========================================================================

  addNote(claimId: string, content: string, isInternal: boolean = true) {
    return this.http
      .post<ApiResponse<unknown>>(`${this.apiUrl}/${claimId}/notes`, {
        content,
        is_internal: isInternal,
      })
      .pipe(
        tap({
          next: () => {
            // Reload claim to get updated notes
            this.loadOne(claimId).subscribe();
          },
        })
      );
  }

  // =========================================================================
  // BENEFIT ELIGIBILITY
  // =========================================================================

  /**
   * Check if a member has available benefit for a claim.
   * Call this before submitting a claim to validate benefit availability.
   */
  checkBenefitEligibility(payload: CheckBenefitEligibilityPayload) {
    return this.http.post<ApiResponse<BenefitEligibilityResult>>(
      `${this.apiUrl}/check-benefit-eligibility`,
      payload
    );
  }

  /**
   * Check eligibility for multiple benefits at once (for claim lines)
   */
  checkMultipleBenefitEligibility(
    memberId: string,
    lines: Array<{ benefit_id: string; amount: number }>,
    serviceDate?: string
  ) {
    return this.http.post<ApiResponse<Record<string, BenefitEligibilityResult>>>(
      `${this.apiUrl}/check-multiple-benefit-eligibility`,
      { member_id: memberId, lines, service_date: serviceDate }
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

  private updateClaimInState(id: string, data: Claim) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
