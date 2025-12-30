// libs/medical/data/src/lib/stores/group.store.ts
// Corporate Groups Store - Signal-based state management

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap, catchError, of, finalize, Observable } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';
import {
  CorporateGroup,
  GroupContact,
  GroupStats,
  CreateGroupPayload,
  CreateContactPayload,
} from '../models/medical-interfaces';

interface GroupState {
  items: CorporateGroup[];
  selected: CorporateGroup | null;
  stats: GroupStats | null;
  loading: boolean;
  saving: boolean;
  error: string | null;
}

@Injectable({ providedIn: 'root' })
export class GroupStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/groups';

  private readonly state = signal<GroupState>({
    items: [],
    selected: null,
    stats: null,
    loading: false,
    saving: false,
    error: null,
  });

  // =========================================================================
  // SELECTORS
  // =========================================================================

  readonly groups = computed(() => this.state().items);
  readonly selectedGroup = computed(() => this.state().selected);
  readonly stats = computed(() => this.state().stats);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly error = computed(() => this.state().error);

  // Computed helpers
  readonly activeGroups = computed(() => this.groups().filter((g) => g.status === 'active'));

  readonly totalPolicies = computed(() =>
    this.groups().reduce((sum, g) => sum + (g.policies_count || 0), 0)
  );

  readonly totalMembers = computed(() =>
    this.groups().reduce((sum, g) => sum + (g.active_members_count || 0), 0)
  );

  // =========================================================================
  // ACTIONS
  // =========================================================================

  loadAll(): void {
    this.state.update((s) => ({ ...s, loading: true, error: null }));

    this.http
      .get<ApiResponse<CorporateGroup[]>>(this.apiUrl)
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
            error: err.error?.message || 'Failed to load groups',
          }));
          return of(null);
        })
      )
      .subscribe();
  }

  loadOne(id: string): Observable<ApiResponse<CorporateGroup> | null> {
    this.state.update((s) => ({ ...s, loading: true, error: null }));

    return this.http.get<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}`).pipe(
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
          error: err.error?.message || 'Failed to load group',
        }));
        return of(null);
      })
    );
  }

  loadDropdown(): Observable<ApiResponse<CorporateGroup[]>> {
    return this.http.get<ApiResponse<CorporateGroup[]>>(`${this.apiUrl}/dropdown`);
  }

  create(payload: CreateGroupPayload): Observable<ApiResponse<CorporateGroup> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.post<ApiResponse<CorporateGroup>>(this.apiUrl, payload).pipe(
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
          error: err.error?.message || 'Failed to create group',
        }));
        return of(null);
      })
    );
  }

  update(
    id: string,
    payload: Partial<CreateGroupPayload>
  ): Observable<ApiResponse<CorporateGroup> | null> {
    this.state.update((s) => ({ ...s, saving: true, error: null }));

    return this.http.put<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}`, payload).pipe(
      tap((res) => {
        if (res.data) {
          this.state.update((s) => ({
            ...s,
            items: s.items.map((g) => (g.id === id ? res.data! : g)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          }));
        }
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to update group',
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
          items: s.items.filter((g) => g.id !== id),
          selected: s.selected?.id === id ? null : s.selected,
          saving: false,
        }));
      }),
      catchError((err) => {
        this.state.update((s) => ({
          ...s,
          saving: false,
          error: err.error?.message || 'Failed to delete group',
        }));
        return of(null);
      })
    );
  }

  // =========================================================================
  // STATUS ACTIONS
  // =========================================================================

  activate(id: string): Observable<ApiResponse<CorporateGroup> | null> {
    return this.updateStatus(id, 'activate');
  }

  suspend(id: string, reason?: string): Observable<ApiResponse<CorporateGroup> | null> {
    return this.updateStatus(id, 'suspend', { reason });
  }

  private updateStatus(
    id: string,
    action: string,
    payload?: object
  ): Observable<ApiResponse<CorporateGroup> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}/${action}`, payload || {})
      .pipe(
        tap((res) => {
          if (res.data) {
            this.state.update((s) => ({
              ...s,
              items: s.items.map((g) => (g.id === id ? res.data! : g)),
              selected: s.selected?.id === id ? res.data : s.selected,
              saving: false,
            }));
          }
        }),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            saving: false,
            error: err.error?.message || `Failed to ${action} group`,
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // CONTACTS
  // =========================================================================

  loadContacts(groupId: string): Observable<ApiResponse<GroupContact[]>> {
    return this.http.get<ApiResponse<GroupContact[]>>(`${this.apiUrl}/${groupId}/contacts`);
  }

  addContact(
    groupId: string,
    payload: CreateContactPayload
  ): Observable<ApiResponse<GroupContact> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<GroupContact>>(`${this.apiUrl}/${groupId}/contacts`, payload)
      .pipe(
        tap((res) => {
          if (res.data && this.state().selected?.id === groupId) {
            const contacts = [...(this.state().selected?.contacts || []), res.data];
            this.state.update((s) => ({
              ...s,
              selected: s.selected ? { ...s.selected, contacts } : null,
              saving: false,
            }));
          }
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to add contact',
          }));
          return of(null);
        })
      );
  }

  updateContact(
    groupId: string,
    contactId: string,
    payload: Partial<CreateContactPayload>
  ): Observable<ApiResponse<GroupContact> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .put<ApiResponse<GroupContact>>(`${this.apiUrl}/${groupId}/contacts/${contactId}`, payload)
      .pipe(
        tap((res) => {
          if (res.data && this.state().selected?.id === groupId) {
            const contacts = this.state().selected?.contacts?.map((c) =>
              c.id === contactId ? res.data! : c
            );
            this.state.update((s) => ({
              ...s,
              selected: s.selected ? { ...s.selected, contacts } : null,
              saving: false,
            }));
          }
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to update contact',
          }));
          return of(null);
        })
      );
  }

  removeContact(groupId: string, contactId: string): Observable<ApiResponse<null> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .delete<ApiResponse<null>>(`${this.apiUrl}/${groupId}/contacts/${contactId}`)
      .pipe(
        tap(() => {
          if (this.state().selected?.id === groupId) {
            const contacts = this.state().selected?.contacts?.filter((c) => c.id !== contactId);
            this.state.update((s) => ({
              ...s,
              selected: s.selected ? { ...s.selected, contacts } : null,
              saving: false,
            }));
          }
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to remove contact',
          }));
          return of(null);
        })
      );
  }

  setPrimaryContact(
    groupId: string,
    contactId: string
  ): Observable<ApiResponse<GroupContact> | null> {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<GroupContact>>(
        `${this.apiUrl}/${groupId}/contacts/${contactId}/primary`,
        {}
      )
      .pipe(
        tap(() => {
          // Reload the group to get updated contacts
          this.loadOne(groupId).subscribe();
        }),
        finalize(() => this.state.update((s) => ({ ...s, saving: false }))),
        catchError((err) => {
          this.state.update((s) => ({
            ...s,
            error: err.error?.message || 'Failed to set primary contact',
          }));
          return of(null);
        })
      );
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  clearSelected(): void {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  clearError(): void {
    this.state.update((s) => ({ ...s, error: null }));
  }
}
