/**
 * Auth Guard - Protect routes that require authentication
 *
 * Usage:
 * {
 *   path: 'dashboard',
 *   component: DashboardComponent,
 *   canActivate: [authGuard]
 * }
 */
import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { AuthService } from 'core-auth';
import { of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // No token at all → immediate redirect
  if (!authService.getToken()) {
    router.navigate(['/login'], { queryParams: { returnUrl: state.url } });
    return false;
  }

  // 🔑 Validate session with backend
  return authService.getMe().pipe(
    map(() => true), // session valid
    catchError(() => {
      authService['clearAuthState']();
      router.navigate(['/login'], {
        queryParams: { expired: true, returnUrl: state.url },
      });
      return of(false);
    })
  );
};
