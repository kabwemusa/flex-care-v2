import { Injectable, signal, computed, inject, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, throwError, interval, Subscription } from 'rxjs';
import {
  User,
  UserContext,
  LoginRequest,
  LoginResponse,
  ModuleCode,
  RolesByGuard,
  PermissionsByGuard,
  PasswordStatus,
  ChangePasswordRequest,
} from '../models/auth.models';

@Injectable({
  providedIn: 'root',
})
export class AuthService implements OnDestroy {
  private http = inject(HttpClient);
  private router = inject(Router);

  // API Base URL - should come from environment
  private apiUrl = '/api/auth';
  private passwordApiUrl = '/api/password';

  // Signals for reactive state management
  private userSignal = signal<User | null>(null);
  private tokenSignal = signal<string | null>(null);
  private modulesSignal = signal<ModuleCode[]>([]);
  private rolesSignal = signal<RolesByGuard>({});
  private permissionsSignal = signal<PermissionsByGuard>({});
  private passwordStatusSignal = signal<PasswordStatus | null>(null);
  private loadingSignal = signal<boolean>(false);
  private errorSignal = signal<string | null>(null);

  // Inactivity tracking
  private inactivityTimer: any = null;
  private sessionCheckInterval: Subscription | null = null;
  // Inactivity timeout: 14 minutes (slightly less than backend's 15 min to give buffer)
  private readonly INACTIVITY_TIMEOUT = 14 * 60 * 1000; // 14 minutes in milliseconds
  // Session validation: Check every 5 minutes (reduced from 30s to save resources)
  private readonly SESSION_CHECK_INTERVAL = 5 * 60 * 1000; // 5 minutes in milliseconds
  private activityListener: (() => void) | null = null;

  // Computed signals
  user = this.userSignal.asReadonly();
  token = this.tokenSignal.asReadonly();
  modules = this.modulesSignal.asReadonly();
  roles = this.rolesSignal.asReadonly();
  permissions = this.permissionsSignal.asReadonly();
  passwordStatus = this.passwordStatusSignal.asReadonly();
  loading = this.loadingSignal.asReadonly();
  error = this.errorSignal.asReadonly();

  isAuthenticated = computed(() => !!this.tokenSignal() && !!this.userSignal());
  isSystemAdmin = computed(() => this.userSignal()?.is_system_admin ?? false);
  requiresPasswordChange = computed(
    () => this.passwordStatusSignal()?.force_change || this.passwordStatusSignal()?.expired || false
  );

  constructor() {
    // Load auth state from localStorage on init
    this.loadAuthState();

    // Start inactivity tracking and session validation if authenticated
    if (this.isAuthenticated()) {
      this.startInactivityTracking();
      this.startSessionValidation();
    }
  }

  ngOnDestroy(): void {
    this.stopInactivityTracking();
    this.stopSessionValidation();
  }

  /**
   * Login user
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    this.loadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, credentials).pipe(
      tap((response) => {
        const { user, token, modules, roles, permissions, password_status } = response.data;

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

        // Store password status
        this.passwordStatusSignal.set(password_status);
        localStorage.setItem('password_status', JSON.stringify(password_status));

        // Start inactivity tracking and session validation after successful login
        this.startInactivityTracking();
        this.startSessionValidation();

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
        this.stopInactivityTracking();
        this.stopSessionValidation();
        this.router.navigate(['/login']);
      }),
      catchError((error) => {
        // Clear state even if API call fails
        this.clearAuthState();
        this.stopInactivityTracking();
        this.stopSessionValidation();
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
    if (this.isSystemAdmin()) return true;
    const perms = this.permissionsSignal()[guard] || [];
    return perms.includes(permission);
  }

  /**
   * Universal permission checker
   * Automatically detects guard from permission string format (e.g., "medical.schemes.view")
   *
   * @param permission - Permission string in format "{guard}.{resource}.{action}"
   * @returns boolean indicating if user has the permission
   */
  isAllowed(permission: string): boolean {
    if (this.isSystemAdmin()) return true;

    // Extract guard from permission string (e.g., "medical.schemes.view" -> "medical")
    const guardName = permission.split('.')[0] as keyof PermissionsByGuard;

    // Check if guard is valid
    if (!guardName || !['web', 'medical', 'life', 'motor', 'travel'].includes(guardName)) {
      console.warn(
        `Invalid permission format: ${permission}. Expected format: {guard}.{resource}.{action}`
      );
      return false;
    }

    const perms = this.permissionsSignal()[guardName] || [];
    return perms.includes(permission);
  }

  /**
   * Check if user is allowed to perform any of the given permissions
   */
  isAllowedAny(permissions: string[]): boolean {
    if (this.isSystemAdmin()) return true;
    return permissions.some((perm) => this.isAllowed(perm));
  }

  /**
   * Check if user is allowed to perform all of the given permissions
   */
  isAllowedAll(permissions: string[]): boolean {
    if (this.isSystemAdmin()) return true;
    return permissions.every((perm) => this.isAllowed(perm));
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
   * Change password
   */
  changePassword(request: ChangePasswordRequest): Observable<any> {
    return this.http.post(`${this.passwordApiUrl}/change`, request).pipe(
      tap((response: any) => {
        // Update password status after successful password change
        this.passwordStatusSignal.set({
          expired: false,
          expiring_soon: false,
          days_until_expiration: null,
          force_change: false,
        });
        localStorage.setItem('password_status', JSON.stringify(this.passwordStatusSignal()));
      })
    );
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
    this.passwordStatusSignal.set(null);
    this.errorSignal.set(null);

    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    localStorage.removeItem('modules');
    localStorage.removeItem('roles');
    localStorage.removeItem('permissions');
    localStorage.removeItem('password_status');
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
    const passwordStatusJson = localStorage.getItem('password_status');

    if (token && userJson) {
      this.tokenSignal.set(token);
      this.userSignal.set(JSON.parse(userJson));
      this.modulesSignal.set(modulesJson ? JSON.parse(modulesJson) : []);
      this.rolesSignal.set(rolesJson ? JSON.parse(rolesJson) : {});
      this.permissionsSignal.set(permissionsJson ? JSON.parse(permissionsJson) : {});
      this.passwordStatusSignal.set(passwordStatusJson ? JSON.parse(passwordStatusJson) : null);
    }
  }

  /**
   * Start tracking user inactivity
   */
  private startInactivityTracking(): void {
    // Clear any existing timer
    this.stopInactivityTracking();

    // Reset timer on user activity
    this.activityListener = () => {
      if (this.inactivityTimer) {
        clearTimeout(this.inactivityTimer);
      }

      this.inactivityTimer = setTimeout(() => {
        // User has been inactive - log them out
        console.warn('Session expired due to inactivity');
        this.handleSessionExpired('inactivity');
      }, this.INACTIVITY_TIMEOUT);
    };

    // Listen for user activity events
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    events.forEach((event) => {
      document.addEventListener(event, this.activityListener!, true);
    });

    // Start the initial timer
    this.activityListener();
  }

  /**
   * Stop tracking user inactivity
   */
  private stopInactivityTracking(): void {
    if (this.inactivityTimer) {
      clearTimeout(this.inactivityTimer);
      this.inactivityTimer = null;
    }

    // Remove event listeners
    if (this.activityListener) {
      const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
      events.forEach((event) => {
        document.removeEventListener(event, this.activityListener!, true);
      });
      this.activityListener = null;
    }
  }

  /**
   * Start periodic session validation with backend
   */
  private startSessionValidation(): void {
    // Stop any existing validation
    this.stopSessionValidation();

    // Check session validity periodically
    this.sessionCheckInterval = interval(this.SESSION_CHECK_INTERVAL).subscribe(() => {
      if (this.isAuthenticated()) {
        this.getMe().subscribe({
          error: (error) => {
            // If we get a 401, session has expired on backend
            if (error.status === 401) {
              const errorCode = error.error?.error_code;
              const reason = errorCode === 'SESSION_INACTIVE' ? 'inactivity' : 'timeout';
              this.handleSessionExpired(reason);
            }
          },
        });
      }
    });
  }

  /**
   * Stop periodic session validation
   */
  private stopSessionValidation(): void {
    if (this.sessionCheckInterval) {
      this.sessionCheckInterval.unsubscribe();
      this.sessionCheckInterval = null;
    }
  }

  /**
   * Handle session expiration
   */
  private handleSessionExpired(reason: 'timeout' | 'inactivity'): void {
    this.clearAuthState();
    this.stopInactivityTracking();
    this.stopSessionValidation();

    this.router.navigate(['/login'], {
      queryParams: { expired: true, reason },
    });
  }
}
