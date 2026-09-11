/*
 * Custom service worker that layers web-push rendering on top of the
 * generated @angular/service-worker runtime.
 *
 * The generated `ngsw-worker.js` precaches the app and keeps SwPush (the
 * push-subscription plumbing) working, but it does NOT handle 'push' events —
 * that is a deliberate gap in @angular/service-worker. So we wrap it:
 * `importScripts` the generated runtime (precache + SwPush messaging still
 * run) and add the `push` render and `notificationclick` handlers ourselves.
 *
 * The build emits both this file (npm-run: ngsw + push handlers) and the
 * generated `ngsw-worker.js` / `ngsw.json` it depends on. The app registers
 * THIS file as its service worker (see app.config.ts).
 */
importScripts('./ngsw-worker.js');

const DEFAULT_TITLE = 'Medication Manager';
const DEFAULT_BODY = 'Time to check your meds.';

self.addEventListener('push', (event) => {
  let title = DEFAULT_TITLE;
  let body = DEFAULT_BODY;
  let data;

  try {
    const payload = event.data ? event.data.json() : null;
    if (payload) {
      title = payload.title || title;
      body = payload.body || body;
      data = payload;
    }
  } catch {
    // Non-JSON or empty payload; fall back to the defaults above.
  }

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: './icons/icon-192x192.png',
      badge: './icons/icon-192x192.png',
      data,
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const rawUrl =
    event.notification.data && event.notification.data.url
      ? event.notification.data.url
      : './';
  const openUrl = new URL(rawUrl, self.location.origin).href;

  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((windowClients) => {
        const existing = windowClients.find((client) => 'focus' in client);
        if (existing) {
          return existing.focus();
        }
        return self.clients.openWindow(openUrl);
      }),
  );
});