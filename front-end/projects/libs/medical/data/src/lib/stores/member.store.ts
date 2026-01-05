// libs/medical/data/src/lib/stores/member.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApiResponse,
  Member,
  MemberLoading,
  MemberExclusion,
  CreateMemberPayload,
} from '../models/medical-interfaces';

interface MemberState {
  items: Member[];
  selected: Member | null;
  loading: boolean;
  saving: boolean;
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
  });

  // Selectors
  readonly members = computed(() => this.state().items);
  readonly selectedMember = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed selectors
  readonly activeMembers = computed(() => this.members().filter((m) => m.status === 'active'));
  readonly principalMembers = computed(() => this.members().filter((m) => m.is_principal));
  readonly dependentMembers = computed(() => this.members().filter((m) => m.is_dependent));

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: {
    search?: string;
    status?: string;
    policy_id?: string;
    member_type?: string;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.policy_id) params = params.set('policy_id', filters.policy_id);
    if (filters?.member_type) params = params.set('member_type', filters.member_type);

    this.http.get<ApiResponse<Member[]>>(this.apiUrl, { params }).subscribe({
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
