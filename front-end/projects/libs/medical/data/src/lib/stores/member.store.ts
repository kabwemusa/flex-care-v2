// libs/medical/data/src/lib/stores/member.store.ts
// Member Store - Signal-based state management

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap, catchError, of, finalize, Observable } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import {
  Member,
  MemberLoading,
  MemberExclusion,
  MemberDocument,
  MemberStats,
  CreateMemberPayload,
  AddLoadingPayload,
  AddExclusionPayload,
} from '../models/medical-interfaces';

interface MemberFilters {
  status?: string;
  member_type?: string;
  policy_id?: string;
  search?: string;
  has_loadings?: boolean;
  has_exclusions?: boolean;
}

interface MemberState {
  items: Member[];
  selected: Member | null;
  stats: MemberStats | null;
  filters: MemberFilters;
  loading: boolean;
  saving: boolean;
  error: string | null;
}

@Injectable({ providedIn: 'root' })
export class MemberStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/members';

  private readonly state = signal<MemberState>({
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

  readonly members = computed(() => this.state().items);
  readonly selectedMember = computed(() => this.state().selected);
  readonly stats = computed(() => this.state().stats);
  readonly filters = computed(() => this.state().filters);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly error = computed(() => this.state().error);

  // Computed helpers
  readonly activeMembers = computed(() => this.members().filter((m) => m.status === 'active'));

  readonly principalMembers = computed(() =>
    this.members().filter((m) => m.member_type === 'principal')
  );

  readonly dependents = computed(() => this.members().filter((m) => m.member_type !== 'principal'));

  readonly membersWithLoadings = computed(() =>
    this.members().filter((m) => (m.loadings_count || 0) > 0)
  );

  readonly membersWithExclusions = computed(() =>
    this.members().filter((m) => (m.exclusions_count || 0) > 0)
  );

  // =========================================================================
  // ACTIONS
  // =========================================================================

  loadAll(filters?: MemberFilters): void {
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
      .get<ApiResponse<Member[]>>(this.apiUrl, { params })
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
            error: err.error?.message || 'Failed to load members',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  loadOne(id: string): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, loading: true, error: null }));

    return this.http.get<ApiResponse<Member>>(`${this.apiUrl}/${id}`).pipe(
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
          error: err.error?.message || 'Failed to load member',
        }));
        return of(null);
      })
    );
  }

  loadStats(): void {
    this.http.get<ApiResponse<MemberStats>>(`${this.apiUrl}/stats`).subscribe({
      next: (res) => {
        this.state.update((s) => ({ ...s, stats: res.data }));
      },
    });
  }

  loadByPolicy(policyId: string): void {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http
      .get<ApiResponse<Member[]>>(`/api/v1/medical/policies/${policyId}/members`)
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
            error: err.error?.message || 'Failed to load policy members',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  search(query: string): void {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http
      .get<ApiResponse<Member[]>>(`${this.apiUrl}/search`, {
        params: new HttpParams().set('q', query),
      })
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
            error: err.error?.message || 'Search failed',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  create(payload: CreateMemberPayload): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.post<ApiResponse<Member>>(this.apiUrl, payload).pipe(
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
          error: err.error?.message || 'Failed to create member',
        }));
        return of(null);
      })
    );
  }

  update(
    id: string,
    payload: Partial<CreateMemberPayload>
  ): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.put<ApiResponse<Member>>(`${this.apiUrl}/${id}`, payload).pipe(
      tap((res) => {
        if (res.data) {
          this.state.update((s) => ({
            ...s,
            items: s.items.map((m) => (m.id === id ? res.data! : m)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          }));
        }
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to update member',
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
          items: s.items.filter((m) => m.id !== id),
          selected: s.selected?.id === id ? null : s.selected,
          saving: false,
        }));
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to delete member',
        }));
        return of(null);
      })
    );
  }

  // =========================================================================
  // STATUS ACTIONS
  // =========================================================================

  activate(id: string): Observable<ApiResponse<Member> | null> {
    return this.updateStatus(id, 'activate');
  }

  suspend(id: string, reason?: string): Observable<ApiResponse<Member> | null> {
    return this.updateStatus(id, 'suspend', { reason });
  }

  terminate(
    id: string,
    reason: string,
    terminationDate?: string
  ): Observable<ApiResponse<Member> | null> {
    return this.updateStatus(id, 'terminate', {
      termination_reason: reason,
      termination_date: terminationDate,
    });
  }

  markDeceased(id: string, deceasedDate: string): Observable<ApiResponse<Member> | null> {
    return this.updateStatus(id, 'deceased', { deceased_date: deceasedDate });
  }

  private updateStatus(
    id: string,
    action: string,
    payload?: object
  ): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Member>>(`${this.apiUrl}/${id}/${action}`, payload || {})
      .pipe(
        tap((res) => {
          if (res.data) {
            this.state.update((s) => ({
              ...s,
              items: s.items.map((m) => (m.id === id ? res.data! : m)),
              selected: s.selected?.id === id ? res.data : s.selected,
              saving: false,
            }));
          }
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            saving: false,
            error: err.error?.message || `Failed to ${action} member`,
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // CARD MANAGEMENT
  // =========================================================================

  issueCard(id: string): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/issue-card`, {}).pipe(
      tap((res) => this.updateMemberInState(id, res.data)),
      finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          error: err.error?.message || 'Failed to issue card',
        }));
        return of(null);
      })
    );
  }

  activateCard(id: string): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/activate-card`, {}).pipe(
      tap((res) => this.updateMemberInState(id, res.data)),
      finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          error: err.error?.message || 'Failed to activate card',
        }));
        return of(null);
      })
    );
  }

  blockCard(id: string, reason?: string): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Member>>(`${this.apiUrl}/${id}/block-card`, { reason }).pipe(
      tap((res) => this.updateMemberInState(id, res.data)),
      finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          error: err.error?.message || 'Failed to block card',
        }));
        return of(null);
      })
    );
  }

  // =========================================================================
  // LOADINGS
  // =========================================================================

  loadLoadings(memberId: string): Observable<ApiResponse<MemberLoading[]>> {
    return this.http.get<ApiResponse<MemberLoading[]>>(`${this.apiUrl}/${memberId}/loadings`);
  }

  addLoading(
    memberId: string,
    payload: AddLoadingPayload
  ): Observable<ApiResponse<MemberLoading> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<MemberLoading>>(`${this.apiUrl}/${memberId}/loadings`, payload)
      .pipe(
        tap(() => {
          // Reload member to get updated premium
          this.loadOne(memberId).subscribe();
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to add loading',
          }));
          return of(null);
        })
      );
  }

  removeLoading(memberId: string, loadingId: string): Observable<ApiResponse<null> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .delete<ApiResponse<null>>(`${this.apiUrl}/${memberId}/loadings/${loadingId}`)
      .pipe(
        tap(() => {
          // Reload member to get updated premium
          this.loadOne(memberId).subscribe();
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to remove loading',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // EXCLUSIONS
  // =========================================================================

  loadExclusions(memberId: string): Observable<ApiResponse<MemberExclusion[]>> {
    return this.http.get<ApiResponse<MemberExclusion[]>>(`${this.apiUrl}/${memberId}/exclusions`);
  }

  addExclusion(
    memberId: string,
    payload: AddExclusionPayload
  ): Observable<ApiResponse<MemberExclusion> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<MemberExclusion>>(`${this.apiUrl}/${memberId}/exclusions`, payload)
      .pipe(
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to add exclusion',
          }));
          return of(null);
        })
      );
  }

  removeExclusion(memberId: string, exclusionId: string): Observable<ApiResponse<null> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .delete<ApiResponse<null>>(`${this.apiUrl}/${memberId}/exclusions/${exclusionId}`)
      .pipe(
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to remove exclusion',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // DOCUMENTS
  // =========================================================================

  loadDocuments(memberId: string): Observable<ApiResponse<MemberDocument[]>> {
    return this.http.get<ApiResponse<MemberDocument[]>>(`${this.apiUrl}/${memberId}/documents`);
  }

  uploadDocument(
    memberId: string,
    file: File,
    documentType: string
  ): Observable<ApiResponse<MemberDocument> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', documentType);

    return this.http
      .post<ApiResponse<MemberDocument>>(`${this.apiUrl}/${memberId}/documents`, formData)
      .pipe(
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to upload document',
          }));
          return of(null);
        })
      );
  }

  verifyDocument(
    memberId: string,
    documentId: string
  ): Observable<ApiResponse<MemberDocument> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<MemberDocument>>(
        `${this.apiUrl}/${memberId}/documents/${documentId}/verify`,
        {}
      )
      .pipe(
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to verify document',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // ELIGIBILITY
  // =========================================================================

  checkEligibility(
    memberId: string
  ): Observable<ApiResponse<{ eligible: boolean; reasons?: string[] }>> {
    return this.http.get<ApiResponse<{ eligible: boolean; reasons?: string[] }>>(
      `${this.apiUrl}/${memberId}/eligibility`
    );
  }

  // =========================================================================
  // DEPENDENTS
  // =========================================================================

  loadDependents(memberId: string): Observable<ApiResponse<Member[]>> {
    return this.http.get<ApiResponse<Member[]>>(`${this.apiUrl}/${memberId}/dependents`);
  }

  addDependent(
    memberId: string,
    payload: CreateMemberPayload
  ): Observable<ApiResponse<Member> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Member>>(`${this.apiUrl}/${memberId}/dependents`, payload)
      .pipe(
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
            error: err.error?.message || 'Failed to add dependent',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  private updateMemberInState(id: string, member: Member | undefined | null): void {
    if (member) {
      this.state.update((s) => ({
        ...s,
        items: s.items.map((m) => (m.id === id ? member : m)),
        selected: s.selected?.id === id ? member : s.selected,
      }));
    }
  }

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
