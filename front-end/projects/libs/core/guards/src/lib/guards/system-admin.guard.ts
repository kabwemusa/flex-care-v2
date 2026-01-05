import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { AuthService } from 'core-auth';

/**
 * System Admin Guard - Protect routes that require system admin access
 *
 * Usage:
 * {
 *   path: 'admin/users',
 *   component: UserManagementComponent,
 *   canActivate: [authGuard, systemAdminGuard]
 * }
 */
export const systemAdminGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Check if user is a system admin
  if (authService.isSystemAdmin()) {
    return true;
  }

  // Redirect to home page with message
  console.warn('Access denied: System administrator privileges required');
  router.navigate(['/'], {
    queryParams: { error: 'admin_required' },
  });
  return false;
};
