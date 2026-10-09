import {
  ApplicationConfig,
  provideBrowserGlobalErrorListeners
} from '@angular/core';

import { provideRouter } from '@angular/router';

import {
  provideHttpClient,
  withFetch,
  withInterceptors
} from '@angular/common/http';

import { provideClientHydration } from '@angular/platform-browser';

import { routes } from './app.routes';

import { authInterceptor } from './core/auth.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),

    provideRouter(routes),

    provideClientHydration(),

    provideHttpClient(
      withFetch(),
      withInterceptors([authInterceptor])
    ),
  ],
};
