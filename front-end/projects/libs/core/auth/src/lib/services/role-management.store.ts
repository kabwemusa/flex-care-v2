import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse, Permission, PermissionGroup, Role } from '../models/auth.models';

interface RoleManagementState {
  roles: Role[];
  selectedRole: Role | null;
  permissions: PermissionGroup;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class RoleManagementStore {
  private readonly http = inject(HttpClient);
  private readonly rolesUrl = '/api/roles';
  private readonly permissionsUrl = '/api/permissions';

  private readonly state = signal<RoleManagementState>({
    roles: [],
    selectedRole: null,
    permissions: {},
    loading: false,
    saving: false,
  });

  // Selectors
  readonly roles = computed(() => this.state().roles);
  readonly selectedRole = computed(() => this.state().selectedRole);
  readonly permissions = computed(() => this.state().permissions);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly permissionCategories = computed(() => {
    return Object.keys(this.permissions());
  });

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
   * Load a single role by ID
   */
  loadRole(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Role>>(`${this.rolesUrl}/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selectedRole: res.data,
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
   * Create a new role
   */
  createRole(roleData: { name: string; guard_name: string; permissions?: string[] }) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Role>>(this.rolesUrl, roleData).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            roles: [res.data, ...s.roles],
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Update an existing role
   */
  updateRole(id: string, changes: { name?: string; permissions?: string[] }) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<Role>>(`${this.rolesUrl}/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            roles: s.roles.map((r) => (r.id === id ? res.data : r)),
            selectedRole: s.selectedRole?.id === id ? res.data : s.selectedRole,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Delete a role
   */
  deleteRole(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.rolesUrl}/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          roles: s.roles.filter((r) => r.id !== id),
        }))
      )
    );
  }

  /**
   * Clear selected role
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selectedRole: null }));
  }
}
