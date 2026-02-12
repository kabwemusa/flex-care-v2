import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';

import { routes } from './app.routes';
import { apiUrlInterceptor } from 'core-http';
import { memberAuthInterceptor } from './interceptors/member-auth.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideHttpClient(
      withInterceptors([
        apiUrlInterceptor, // Prepend API base URL
        memberAuthInterceptor, // Attach member token for portal requests
      ])
    ),
  ],
};
