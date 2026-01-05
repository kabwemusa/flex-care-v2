import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';

import { routes } from './app.routes';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { apiInterceptor } from 'medical-data';
import { apiUrlInterceptor, authInterceptor } from 'core-http';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideAnimationsAsync(),
    provideHttpClient(
      withInterceptors([
        apiUrlInterceptor, // Prepend API base URL
        authInterceptor,   // Attach JWT token
        apiInterceptor     // Medical module interceptor
      ])
    ),
  ],
};
