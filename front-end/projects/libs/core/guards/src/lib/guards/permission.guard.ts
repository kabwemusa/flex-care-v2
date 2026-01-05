import { inject } from '@angular/core';
import { Router, CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService, PermissionsByGuard } from 'core-auth';

/**
 * Permission Guard - Protect routes that require specific permissions
 *
 * Usage:
 * {
 *   path: 'schemes/create',
 *   component: SchemeCreateComponent,
 *   canActivate: [authGuard, moduleGuard, permissionGuard],
 *   data: {
 *     module: 'medical',
 *     permission: 'medical.schemes.create',
 *     guard: 'medical' // optional, defaults to 'web'
 *   }
 * }
 */
export const permissionGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Get required permission and guard from route data
  const requiredPermission = route.data['permission'] as string;
  const guard = (route.data['guard'] as keyof PermissionsByGuard) || 'web';

  if (!requiredPermission) {
    console.error('Permission guard requires "permission" in route data');
    return false;
  }

  // System admins bypass all permission checks
  if (authService.isSystemAdmin()) {
    return true;
  }

  // Check if user has permission
  if (authService.hasPermission(requiredPermission, guard)) {
    return true;
  }

  // Redirect to home page
  console.warn(`Access denied: Missing permission "${requiredPermission}" for guard "${guard}"`);
  router.navigate(['/']);
  return false;
};

/**
 * Multiple Permissions Guard - Check if user has ANY of the specified permissions
 *
 * Usage:
 * {
 *   path: 'reports',
 *   component: ReportsComponent,
 *   canActivate: [authGuard, anyPermissionGuard],
 *   data: {
 *     permissions: ['medical.reports.view', 'medical.reports.export'],
 *     guard: 'medical'
 *   }
 * }
 */
export const anyPermissionGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const requiredPermissions = route.data['permissions'] as string[];
  const guard = (route.data['guard'] as keyof PermissionsByGuard) || 'web';

  if (!requiredPermissions || !Array.isArray(requiredPermissions)) {
    console.error('Any permission guard requires "permissions" array in route data');
    return false;
  }

  // System admins bypass all permission checks
  if (authService.isSystemAdmin()) {
    return true;
  }

  if (authService.hasAnyPermission(requiredPermissions, guard)) {
    return true;
  }

  console.warn(`Access denied: Missing permissions [${requiredPermissions.join(', ')}] for guard "${guard}"`);
  router.navigate(['/']);
  return false;
};

/**
 * All Permissions Guard - Check if user has ALL of the specified permissions
 *
 * Usage:
 * {
 *   path: 'admin/settings',
 *   component: SettingsComponent,
 *   canActivate: [authGuard, allPermissionsGuard],
 *   data: {
 *     permissions: ['settings.view', 'settings.update'],
 *     guard: 'web'
 *   }
 * }
 */
export const allPermissionsGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const requiredPermissions = route.data['permissions'] as string[];
  const guard = (route.data['guard'] as keyof PermissionsByGuard) || 'web';

  if (!requiredPermissions || !Array.isArray(requiredPermissions)) {
    console.error('All permissions guard requires "permissions" array in route data');
    return false;
  }

  // System admins bypass all permission checks
  if (authService.isSystemAdmin()) {
    return true;
  }

  if (authService.hasAllPermissions(requiredPermissions, guard)) {
    return true;
  }

  console.warn(`Access denied: Missing all permissions [${requiredPermissions.join(', ')}] for guard "${guard}"`);
  router.navigate(['/']);
  return false;
};
