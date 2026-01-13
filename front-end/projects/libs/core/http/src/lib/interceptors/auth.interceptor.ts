import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from 'core-auth';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

/**
 * HTTP Interceptor to attach JWT token to requests
 * and handle 401 Unauthorized responses
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Get token from auth service
  const token = authService.getToken();

  // Clone request and add Authorization header if token exists
  const authReq = token
    ? req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`,
        },
      })
    : req;

  // Handle the request and catch errors
  return next(authReq).pipe(
    catchError((error) => {
      if (error.status === 401) {
        const errorCode = error.error?.error_code;

        // Check if it's a session expiration error
        if (errorCode === 'SESSION_EXPIRED' || errorCode === 'SESSION_INACTIVE') {
          // Clear local auth state and redirect to login
          localStorage.clear();
          router.navigate(['/login'], {
            queryParams: {
              expired: true,
              reason: errorCode === 'SESSION_INACTIVE' ? 'inactivity' : 'timeout',
            },
          });
        } else {
          // General 401 - token expired or invalid
          authService.logout().subscribe({
            next: () => router.navigate(['/login']),
            error: () => router.navigate(['/login']),
          });
        }
      }
      return throwError(() => error);
    })
  );
};
