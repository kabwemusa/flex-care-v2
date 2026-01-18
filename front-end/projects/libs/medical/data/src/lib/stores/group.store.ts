// libs/medical/data/src/lib/stores/group.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApplicationMember, CorporateGroup, GroupContact } from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse, PaginationMeta } from 'medical-data';

interface GroupState {
  items: CorporateGroup[];
  pagination: PaginationMeta | null;
  selected: CorporateGroup | null;
  members: ApplicationMember[];
  membersPagination: PaginationMeta | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class GroupStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/groups';

  private readonly state = signal<GroupState>({
    items: [],
    pagination: null,
    selected: null,
    members: [],
    membersPagination: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly groups = computed(() => this.state().items);
  readonly pagination = computed(() => this.state().pagination);
  readonly members = computed(() => this.state().members);
  readonly membersPagination = computed(() => this.state().membersPagination);

  readonly currentPage = computed(() => this.pagination()?.current_page ?? 1);
  readonly totalPages = computed(() => this.pagination()?.last_page ?? 1);
  readonly totalItems = computed(() => this.pagination()?.total ?? 0);
  readonly pageSize = computed(() => this.pagination()?.per_page ?? 20);

  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly selectedGroup = computed(() => this.state().selected);

  readonly activeGroups = computed(() => this.groups().filter((g) => g.status === 'active'));

  readonly prospectGroups = computed(() => this.groups().filter((g) => g.status === 'prospect'));

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadPage(page: number) {
    if (page < 1 || page > this.totalPages() || this.isLoading()) return;

    this.loadAll({ page, per_page: this.pageSize() });
  }

  setPageSize(perPage: number) {
    this.loadAll({ page: 1, per_page: perPage });
  }

  loadAll(filters?: {
    search?: string;
    status?: string;
    company_size?: string;
    industry?: string;
    page?: number;
    per_page?: number;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params = params.set(key, value);
      }
    });

    this.http.get<PaginatedResponse<CorporateGroup>>(this.apiUrl, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          pagination: res.meta,
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

  loadMembers(applicationId: string, page = 1, perPage = 10) {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http
      .get<PaginatedResponse<ApplicationMember>>(
        `/api/v1/medical/applications/${applicationId}/members`,
        {
          params: { page, per_page: perPage },
        }
      )
      .subscribe({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            members: res.data,
            membersPagination: res.meta,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      });
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

  clearMembers() {
    this.state.update((s) => ({ ...s, members: [], membersPagination: null }));
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
