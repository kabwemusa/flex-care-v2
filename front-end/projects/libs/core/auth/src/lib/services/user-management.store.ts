import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApiResponse,
  AssignRolesRequest,
  CreateUserRequest,
  PaginatedResponse,
  Permission,
  PermissionGroup,
  Role,
  UpdateUserRequest,
  UserDetail,
  UserListItem,
} from '../models/auth.models';

interface UserManagementState {
  users: UserListItem[];
  selectedUser: UserDetail | null;
  roles: Role[];
  permissions: PermissionGroup;
  loading: boolean;
  saving: boolean;
  currentPage: number;
  lastPage: number;
  total: number;
}

export interface UserFilters {
  is_active?: boolean;
  module?: string;
  search?: string;
  per_page?: number;
  page?: number;
}

@Injectable({ providedIn: 'root' })
export class UserManagementStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/users';
  private readonly rolesUrl = '/api/roles';
  private readonly permissionsUrl = '/api/permissions';

  private readonly state = signal<UserManagementState>({
    users: [],
    selectedUser: null,
    roles: [],
    permissions: {},
    loading: false,
    saving: false,
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Selectors
  readonly users = computed(() => this.state().users);
  readonly selectedUser = computed(() => this.state().selectedUser);
  readonly roles = computed(() => this.state().roles);
  readonly permissions = computed(() => this.state().permissions);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly currentPage = computed(() => this.state().currentPage);
  readonly lastPage = computed(() => this.state().lastPage);
  readonly total = computed(() => this.state().total);

  // Computed
  readonly activeUsers = computed(() => this.users().filter((u) => u.is_active));
  readonly systemAdmins = computed(() => this.users().filter((u) => u.is_system_admin));

  /**
   * Load paginated users with optional filters
   */
  loadUsers(filters?: UserFilters) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.is_active !== undefined) {
      params = params.set('is_active', filters.is_active.toString());
    }
    if (filters?.module) {
      params = params.set('module', filters.module);
    }
    if (filters?.search) {
      params = params.set('search', filters.search);
    }
    if (filters?.per_page) {
      params = params.set('per_page', filters.per_page.toString());
    }
    if (filters?.page) {
      params = params.set('page', filters.page.toString());
    }

    return this.http.get<PaginatedResponse<UserListItem>>(this.apiUrl, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: res.data,
            currentPage: res.current_page,
            lastPage: res.last_page,
            total: res.total,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load a single user by ID
   */
  loadUser(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<UserDetail>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selectedUser: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Create a new user
   */
  createUser(userData: CreateUserRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<UserListItem>>(this.apiUrl, userData).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: [res.data, ...s.users],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Update an existing user
   */
  updateUser(id: string, changes: UpdateUserRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<UserListItem>>(`${this.apiUrl}/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: s.users.map((u) => (u.id === id ? res.data : u)),
            selectedUser: s.selectedUser?.id === id ? { ...s.selectedUser, ...res.data } : s.selectedUser,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Delete a user (soft delete)
   */
  deleteUser(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          users: s.users.filter((u) => u.id !== id),
        }))
      )
    );
  }

  /**
   * Activate a user
   */
  activateUser(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<UserListItem>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: s.users.map((u) => (u.id === id ? res.data : u)),
            selectedUser: s.selectedUser?.id === id ? { ...s.selectedUser, ...res.data } : s.selectedUser,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Deactivate a user
   */
  deactivateUser(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<UserListItem>>(`${this.apiUrl}/${id}/deactivate`, {}).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: s.users.map((u) => (u.id === id ? res.data : u)),
            selectedUser: s.selectedUser?.id === id ? { ...s.selectedUser, ...res.data } : s.selectedUser,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Assign roles to a user
   */
  assignRoles(userId: string, request: AssignRolesRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<UserListItem>>(`${this.apiUrl}/${userId}/roles`, request).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            users: s.users.map((u) => (u.id === userId ? res.data : u)),
            selectedUser: s.selectedUser?.id === userId ? { ...s.selectedUser, ...res.data } : s.selectedUser,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Get user permissions
   */
  getUserPermissions(userId: string) {
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/${userId}/permissions`);
  }

  /**
   * Load all roles for a specific guard
   */
  loadRoles(guard: string = 'web') {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Role[]>>(this.rolesUrl, { params: { guard } }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            roles: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load all permissions for a specific guard
   */
  loadPermissions(guard: string = 'web') {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<PermissionGroup>>(this.permissionsUrl, { params: { guard } }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            permissions: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Clear selected user
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selectedUser: null }));
  }
}
