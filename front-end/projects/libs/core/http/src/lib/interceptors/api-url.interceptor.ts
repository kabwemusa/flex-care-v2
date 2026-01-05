import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { ConfigService } from 'core-config';

/**
 * HTTP Interceptor to prepend API base URL to relative URLs
 */
export const apiUrlInterceptor: HttpInterceptorFn = (req, next) => {
  const configService = inject(ConfigService);

  // Only modify relative URLs
  if (!req.url.startsWith('http://') && !req.url.startsWith('https://')) {
    const apiUrl = configService.getApiUrl();
    const apiReq = req.clone({
      url: `${apiUrl}${req.url}`,
    });
    return next(apiReq);
  }

  return next(req);
};
