// Approval Store - For handling approval actions

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApprovalRequest,
  PendingApproval,
  ApprovalHistoryItem,
  ApproveRequest,
  RejectRequest,
  ReturnRequest,
} from '../models/approval.models';

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

interface ApprovalState {
  pendingApprovals: PendingApproval[];
  approvalHistory: ApprovalHistoryItem[];
  selectedRequest: ApprovalRequest | null;
  loading: boolean;
  actioning: boolean;
}

@Injectable({ providedIn: 'root' })
export class ApprovalStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/approvals';

  private readonly state = signal<ApprovalState>({
    pendingApprovals: [],
    approvalHistory: [],
    selectedRequest: null,
    loading: false,
    actioning: false,
  });

  // Selectors
  readonly pendingApprovals = computed(() => this.state().pendingApprovals);
  readonly approvalHistory = computed(() => this.state().approvalHistory);
  readonly selectedRequest = computed(() => this.state().selectedRequest);
  readonly isLoading = computed(() => this.state().loading);
  readonly isActioning = computed(() => this.state().actioning);

  // Computed
  readonly pendingCount = computed(() => this.pendingApprovals().length);
  readonly hasPendingApprovals = computed(() => this.pendingCount() > 0);

  /**
   * Load pending approvals for the current user
   */
  loadPending(entityType?: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (entityType) params = params.set('entity_type', entityType);

    return this.http.get<ApiResponse<PendingApproval[]>>(`${this.apiUrl}/pending`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            pendingApprovals: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load approval history for the current user
   */
  loadHistory(limit?: number) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (limit) params = params.set('limit', limit.toString());

    return this.http.get<ApiResponse<ApprovalHistoryItem[]>>(`${this.apiUrl}/history`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            approvalHistory: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load a single approval request
   */
  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<ApprovalRequest>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selectedRequest: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Approve a request
   */
  approve(id: string, data?: ApproveRequest) {
    this.state.update((s) => ({ ...s, actioning: true }));

    return this.http.post<ApiResponse<{ id: string; status: string; message: string }>>(`${this.apiUrl}/${id}/approve`, data || {}).pipe(
      tap({
        next: (res) => {
          // Remove from pending list
          this.state.update((s) => ({
            ...s,
            pendingApprovals: s.pendingApprovals.filter((a) => a.id !== id),
            actioning: false,
          }));
        },
        error: () => this.state.update((s) => ({ ...s, actioning: false })),
      })
    );
  }

  /**
   * Reject a request
   */
  reject(id: string, data: RejectRequest) {
    this.state.update((s) => ({ ...s, actioning: true }));

    return this.http.post<ApiResponse<{ id: string; status: string }>>(`${this.apiUrl}/${id}/reject`, data).pipe(
      tap({
        next: (res) => {
          // Remove from pending list
          this.state.update((s) => ({
            ...s,
            pendingApprovals: s.pendingApprovals.filter((a) => a.id !== id),
            actioning: false,
          }));
        },
        error: () => this.state.update((s) => ({ ...s, actioning: false })),
      })
    );
  }

  /**
   * Return a request for amendment
   */
  returnForAmendment(id: string, data: ReturnRequest) {
    this.state.update((s) => ({ ...s, actioning: true }));

    return this.http.post<ApiResponse<{ id: string; status: string }>>(`${this.apiUrl}/${id}/return`, data).pipe(
      tap({
        next: (res) => {
          // Remove from pending list
          this.state.update((s) => ({
            ...s,
            pendingApprovals: s.pendingApprovals.filter((a) => a.id !== id),
            actioning: false,
          }));
        },
        error: () => this.state.update((s) => ({ ...s, actioning: false })),
      })
    );
  }

  /**
   * Clear selected request
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selectedRequest: null }));
  }
}
