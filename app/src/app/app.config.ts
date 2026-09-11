import {
  ApplicationConfig,
  isDevMode,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideServiceWorker } from '@angular/service-worker';
import { routes } from './app.routes';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(),
    // Route params (e.g. :personId) bind directly into component inputs.
    provideRouter(routes, withComponentInputBinding()),
    // PWA service worker: registered only in production builds; the dev
    // server and unit tests run without it (no secure context / no SW).
    // `push-worker.js` is a thin wrapper that importScripts the generated
    // `ngsw-worker.js` (precache + SwPush messaging) and adds the push
    // render / notification-click handlers ngsw does not ship.
    provideServiceWorker('push-worker.js', {
      enabled: !isDevMode(),
      registrationStrategy: 'registerWhenStable:30000',
    }),
  ],
};