// Approval Group Store

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApprovalGroup,
  CreateApprovalGroupRequest,
  UpdateApprovalGroupRequest,
  AddGroupMembersRequest,
  UserRef,
} from '../models/approval.models';

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

interface ApprovalGroupState {
  items: ApprovalGroup[];
  selected: ApprovalGroup | null;
  availableUsers: UserRef[];
  loading: boolean;
  saving: boolean;
}

export interface ApprovalGroupFilters {
  search?: string;
  is_active?: boolean;
}

@Injectable({ providedIn: 'root' })
export class ApprovalGroupStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/settings/approval-groups';

  private readonly state = signal<ApprovalGroupState>({
    items: [],
    selected: null,
    availableUsers: [],
    loading: false,
    saving: false,
  });

  // Selectors
  readonly groups = computed(() => this.state().items);
  readonly selectedGroup = computed(() => this.state().selected);
  readonly availableUsers = computed(() => this.state().availableUsers);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activeGroups = computed(() => this.groups().filter((g) => g.is_active));

  /**
   * Load all approval groups
   */
  loadAll(filters?: ApprovalGroupFilters) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.is_active !== undefined) params = params.set('is_active', filters.is_active.toString());

    return this.http.get<ApiResponse<ApprovalGroup[]>>(this.apiUrl, { params }).pipe(
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

  /**
   * Load a single approval group by ID
   */
  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<ApprovalGroup>>(`${this.apiUrl}/${id}`).pipe(
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

  /**
   * Create a new approval group
   */
  create(data: CreateApprovalGroupRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<ApprovalGroup>>(this.apiUrl, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: [...s.items, res.data],
            selected: res.data,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Update an approval group
   */
  update(id: string, data: UpdateApprovalGroupRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<ApprovalGroup>>(`${this.apiUrl}/${id}`, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((g) => (g.id === id ? res.data : g)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Delete an approval group
   */
  delete(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: () =>
          this.state.update((s) => ({
            ...s,
            items: s.items.filter((g) => g.id !== id),
            selected: s.selected?.id === id ? null : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Add members to a group
   */
  addMembers(groupId: string, data: AddGroupMembersRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<ApprovalGroup>>(`${this.apiUrl}/${groupId}/members`, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((g) => (g.id === groupId ? res.data : g)),
            selected: s.selected?.id === groupId ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Remove a member from a group
   */
  removeMember(groupId: string, userId: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<ApprovalGroup>>(`${this.apiUrl}/${groupId}/members/${userId}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((g) => (g.id === groupId ? res.data : g)),
            selected: s.selected?.id === groupId ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Load available users for a group
   */
  loadAvailableUsers(groupId: string, search?: string) {
    let params = new HttpParams();
    if (search) params = params.set('search', search);

    return this.http.get<ApiResponse<UserRef[]>>(`${this.apiUrl}/${groupId}/available-users`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            availableUsers: res.data,
          })),
      })
    );
  }

  /**
   * Clear selected group
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  /**
   * Set selected group
   */
  setSelected(group: ApprovalGroup | null) {
    this.state.update((s) => ({ ...s, selected: group }));
  }
}
