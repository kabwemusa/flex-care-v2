import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { MemberAuthService } from '../services/member-auth.service';

export const memberAuthGuard: CanActivateFn = () => {
  const authService = inject(MemberAuthService);
  const router = inject(Router);

  if (authService.isAuthenticated()) {
    return true;
  }

  // Redirect to login page
  router.navigate(['/login']);
  return false;
};
