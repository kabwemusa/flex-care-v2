import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { catchError, throwError } from 'rxjs';

// @medical/data/interceptors/api.interceptor.ts
export const apiInterceptor: HttpInterceptorFn = (req, next) => {
  const snackbar = inject(MatSnackBar);

  return next(req).pipe(
    catchError((err: HttpErrorResponse) => {
      // Handle the Laravel error() structure
      const errorMessage = err.error?.message || 'A system error occurred';
      const validationErrors = err.error?.errors;

      snackbar.open(errorMessage, 'Close', { panelClass: 'error-snackbar' });

      return throwError(() => err.error);
    })
  );
};
