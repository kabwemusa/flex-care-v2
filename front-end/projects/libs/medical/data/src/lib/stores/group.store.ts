// libs/medical/data/src/lib/stores/group.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse, CorporateGroup, GroupContact } from '../models/medical-interfaces';

interface GroupState {
  items: CorporateGroup[];
  selected: CorporateGroup | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class GroupStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/groups';

  private readonly state = signal<GroupState>({
    items: [],
    selected: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly groups = computed(() => this.state().items);
  readonly selectedGroup = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed selectors
  readonly activeGroups = computed(() => this.groups().filter((g) => g.status === 'active'));
  readonly prospectGroups = computed(() => this.groups().filter((g) => g.status === 'prospect'));

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: {
    search?: string;
    status?: string;
    company_size?: string;
    industry?: string;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.company_size) params = params.set('company_size', filters.company_size);
    if (filters?.industry) params = params.set('industry', filters.industry);

    this.http.get<ApiResponse<CorporateGroup[]>>(this.apiUrl, { params }).subscribe({
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

    return this.http.get<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}`).pipe(
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
    return this.http.get<ApiResponse<CorporateGroup[]>>(`${this.apiUrl}/dropdown`);
  }

  // =========================================================================
  // CRUD OPERATIONS
  // =========================================================================

  create(group: Partial<CorporateGroup>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<CorporateGroup>>(this.apiUrl, group).pipe(
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

  update(id: string, changes: Partial<CorporateGroup>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}`, changes).pipe(
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

    return this.http.post<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
      tap({
        next: (res) => this.updateGroupInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  suspend(id: string, reason?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${id}/suspend`, { reason })
      .pipe(
        tap({
          next: (res) => this.updateGroupInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // CONTACT OPERATIONS
  // =========================================================================

  addContact(groupId: string, contact: Partial<GroupContact>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${groupId}/contacts`, contact)
      .pipe(
        tap({
          next: (res) => this.updateGroupInState(groupId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  updateContact(groupId: string, contactId: string, changes: Partial<GroupContact>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .patch<ApiResponse<CorporateGroup>>(
        `${this.apiUrl}/${groupId}/contacts/${contactId}`,
        changes
      )
      .pipe(
        tap({
          next: (res) => this.updateGroupInState(groupId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeContact(groupId: string, contactId: string) {
    return this.http
      .delete<ApiResponse<CorporateGroup>>(`${this.apiUrl}/${groupId}/contacts/${contactId}`)
      .pipe(
        tap({
          next: (res) => this.updateGroupInState(groupId, res.data),
        })
      );
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  private updateGroupInState(id: string, data: CorporateGroup) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
