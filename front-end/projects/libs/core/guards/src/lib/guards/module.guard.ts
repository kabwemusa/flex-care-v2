import { inject } from '@angular/core';
import { Router, CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService, ModuleCode } from 'core-auth';

/**
 * Module Guard - Protect routes that require specific module access
 *
 * Usage:
 * {
 *   path: 'medical',
 *   component: MedicalComponent,
 *   canActivate: [authGuard, moduleGuard],
 *   data: { module: 'medical' }
 * }
 */
export const moduleGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Get required module from route data
  const requiredModule = route.data['module'] as ModuleCode;

  if (!requiredModule) {
    console.error('Module guard requires "module" in route data');
    return false;
  }

  // System admins have access to all modules
  if (authService.isSystemAdmin()) {
    return true;
  }

  // Check if user has module access
  if (authService.hasModuleAccess(requiredModule)) {
    return true;
  }

  // Redirect to home page
  console.warn(`Access denied: No access to module "${requiredModule}"`);
  router.navigate(['/']);
  return false;
};
