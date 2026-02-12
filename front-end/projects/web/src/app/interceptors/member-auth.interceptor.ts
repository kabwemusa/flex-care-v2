import { HttpInterceptorFn } from '@angular/common/http';

const TOKEN_KEY = 'flex_member_token';

/**
 * HTTP Interceptor to attach member auth token to requests.
 */
export const memberAuthInterceptor: HttpInterceptorFn = (req, next) => {
  // Only attach token to member portal API requests
  if (req.url.includes('/v1/medical/member/')) {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
      const authReq = req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`,
        },
      });
      return next(authReq);
    }
  }

  return next(req);
};
