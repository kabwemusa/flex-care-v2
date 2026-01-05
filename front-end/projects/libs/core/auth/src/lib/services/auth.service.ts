import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, throwError } from 'rxjs';
import {
  User,
  UserContext,
  LoginRequest,
  LoginResponse,
  ModuleCode,
  RolesByGuard,
  PermissionsByGuard,
} from '../models/auth.models';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  // API Base URL - should come from environment
  private apiUrl = '/api/auth';

  // Signals for reactive state management
  private userSignal = signal<User | null>(null);
  private tokenSignal = signal<string | null>(null);
  private modulesSignal = signal<ModuleCode[]>([]);
  private rolesSignal = signal<RolesByGuard>({});
  private permissionsSignal = signal<PermissionsByGuard>({});
  private loadingSignal = signal<boolean>(false);
  private errorSignal = signal<string | null>(null);

  // Computed signals
  user = this.userSignal.asReadonly();
  token = this.tokenSignal.asReadonly();
  modules = this.modulesSignal.asReadonly();
  roles = this.rolesSignal.asReadonly();
  permissions = this.permissionsSignal.asReadonly();
  loading = this.loadingSignal.asReadonly();
  error = this.errorSignal.asReadonly();

  isAuthenticated = computed(() => !!this.tokenSignal() && !!this.userSignal());
  isSystemAdmin = computed(() => this.userSignal()?.is_system_admin ?? false);

  constructor() {
    // Load auth state from localStorage on init
    this.loadAuthState();
  }

  /**
   * Login user
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    this.loadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, credentials).pipe(
      tap((response) => {
        const { user, token, modules, roles, permissions } = response.data;

        // Store token
        this.tokenSignal.set(token);
        localStorage.setItem('auth_token', token);

        // Store user
        this.userSignal.set(user);
        localStorage.setItem('user', JSON.stringify(user));

        // Store modules
        this.modulesSignal.set(modules);
        localStorage.setItem('modules', JSON.stringify(modules));

        // Store roles (already grouped by guard from backend)
        this.rolesSignal.set(roles as RolesByGuard);
        localStorage.setItem('roles', JSON.stringify(roles));

        // Store permissions (already grouped by guard from backend)
        this.permissionsSignal.set(permissions as PermissionsByGuard);
        localStorage.setItem('permissions', JSON.stringify(permissions));

        this.loadingSignal.set(false);
      }),
      catchError((error) => {
        this.errorSignal.set(error.error?.message || 'Login failed');
        this.loadingSignal.set(false);
        return throwError(() => error);
      })
    );
  }

  /**
   * Logout user
   */
  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/logout`, {}).pipe(
      tap(() => {
        this.clearAuthState();
        this.router.navigate(['/login']);
      }),
      catchError((error) => {
        // Clear state even if API call fails
        this.clearAuthState();
        this.router.navigate(['/login']);
        return throwError(() => error);
      })
    );
  }

  /**
   * Get current user context
   */
  getMe(): Observable<{ data: UserContext }> {
    return this.http.get<{ data: UserContext }>(`${this.apiUrl}/me`).pipe(
      tap((response) => {
        const { user, modules, roles, permissions } = response.data;

        this.userSignal.set(user);
        this.modulesSignal.set(modules);
        this.rolesSignal.set(roles);
        this.permissionsSignal.set(permissions);

        // Update localStorage
        localStorage.setItem('user', JSON.stringify(user));
        localStorage.setItem('modules', JSON.stringify(modules));
        localStorage.setItem('roles', JSON.stringify(roles));
        localStorage.setItem('permissions', JSON.stringify(permissions));
      })
    );
  }

  /**
   * Refresh token
   */
  refreshToken(): Observable<{ data: { token: string } }> {
    return this.http.post<{ data: { token: string } }>(`${this.apiUrl}/refresh`, {}).pipe(
      tap((response) => {
        const token = response.data.token;
        this.tokenSignal.set(token);
        localStorage.setItem('auth_token', token);
      })
    );
  }

  /**
   * Check if user has access to a specific module
   */
  hasModuleAccess(moduleCode: ModuleCode): boolean {
    if (this.isSystemAdmin()) return true;
    return this.modulesSignal().includes(moduleCode);
  }

  /**
   * Check if user has a specific permission
   */
  hasPermission(permission: string, guard: keyof PermissionsByGuard = 'web'): boolean {
    const perms = this.permissionsSignal()[guard] || [];
    return perms.includes(permission);
  }

  /**
   * Check if user has any of the specified permissions
   */
  hasAnyPermission(permissions: string[], guard: keyof PermissionsByGuard = 'web'): boolean {
    return permissions.some((perm) => this.hasPermission(perm, guard));
  }

  /**
   * Check if user has all of the specified permissions
   */
  hasAllPermissions(permissions: string[], guard: keyof PermissionsByGuard = 'web'): boolean {
    return permissions.every((perm) => this.hasPermission(perm, guard));
  }

  /**
   * Check if user has a specific role
   */
  hasRole(role: string, guard: keyof RolesByGuard = 'web'): boolean {
    const roles = this.rolesSignal()[guard] || [];
    return roles.includes(role);
  }

  /**
   * Get token for HTTP interceptor
   */
  getToken(): string | null {
    return this.tokenSignal();
  }

  /**
   * Clear authentication state
   */
  private clearAuthState(): void {
    this.userSignal.set(null);
    this.tokenSignal.set(null);
    this.modulesSignal.set([]);
    this.rolesSignal.set({});
    this.permissionsSignal.set({});
    this.errorSignal.set(null);

    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    localStorage.removeItem('modules');
    localStorage.removeItem('roles');
    localStorage.removeItem('permissions');
  }

  /**
   * Load auth state from localStorage
   */
  private loadAuthState(): void {
    const token = localStorage.getItem('auth_token');
    const userJson = localStorage.getItem('user');
    const modulesJson = localStorage.getItem('modules');
    const rolesJson = localStorage.getItem('roles');
    const permissionsJson = localStorage.getItem('permissions');

    if (token && userJson) {
      this.tokenSignal.set(token);
      this.userSignal.set(JSON.parse(userJson));
      this.modulesSignal.set(modulesJson ? JSON.parse(modulesJson) : []);
      this.rolesSignal.set(rolesJson ? JSON.parse(rolesJson) : {});
      this.permissionsSignal.set(permissionsJson ? JSON.parse(permissionsJson) : {});
    }
  }

}
