import { Service, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { SwPush } from '@angular/service-worker';
import { firstValueFrom } from 'rxjs';
import { map } from 'rxjs/operators';

interface ApiEnvelope<T> {
  data: T;
}

interface VapidPublicKeyEnvelope {
  public_key: string;
}

interface RawSubscription {
  endpoint: string;
  keys?: { p256dh: string; auth: string } | null;
}

/** The per-device registration the backend stores for a person. */
export interface PushSubscriptionPayload {
  person_id: number;
  endpoint: string;
  p256dh: string;
  auth: string;
}

/**
 * What this device's push setup looks like. Drives the small enable/status
 * affordance; `error` carries the last failure message when present.
 */
export type PushStatus = 'unsupported' | 'idle' | 'subscribing' | 'subscribed' | 'denied' | 'error';

/**
 * Thin web-push plumbing: request permission, subscribe through the service
 * worker's push manager (via `SwPush`), and register the resulting
 * subscription with the backend for a person. No delivery logic lives here —
 * that is a later phase.
 */
@Service()
export class PushSubscriptionService {
  readonly #http = inject(HttpClient);
  readonly #swPush = inject(SwPush);

  readonly #base = '/api/v1';

  readonly status = signal<PushStatus>('idle');
  readonly error = signal<string | null>(null);

  /** Whether this browser can subscribe at all (secure context + Notifications). */
  #isSupported(): boolean {
    return (
      typeof globalThis !== 'undefined' &&
      'Notification' in globalThis &&
      globalThis.isSecureContext !== false
    );
  }

  /**
   * The browser's current Notification permission, for display. Falls back
   * to 'unsupported' when the Notification API is not available.
   */
  permission(): string {
    return this.#isSupported() ? Notification.permission : 'unsupported';
  }

  /**
   * Enable push for a person: fetch the backend's VAPID public key, ask the
   * browser to subscribe (this triggers the permission prompt), then register
   * the new subscription with the backend.
   *
   * Returns the resulting status so a caller can react. Never throws for the
   * "push simply isn't available here" case — that degrades to `unsupported`.
   */
  async enable(personId: number): Promise<PushStatus> {
    this.error.set(null);

    if (!this.#isSupported()) {
      this.status.set('unsupported');
      return 'unsupported';
    }

    this.status.set('subscribing');

    try {
      const publicKey = await this.#fetchVapidPublicKey();
      const subscription = await this.#swPush.requestSubscription({
        serverPublicKey: publicKey,
      });

      await this.#register(personId, subscription.toJSON() as RawSubscription);

      this.status.set('subscribed');
    } catch (failure) {
      if (this.#isSupported() && Notification.permission === 'denied') {
        this.status.set('denied');
      } else {
        this.status.set('error');
        this.error.set(
          failure instanceof Error ? failure.message : 'Could not enable push notifications.',
        );
      }
    }

    return this.status();
  }

  /** Read the VAPID public key from the backend (`{ data: { public_key } }`). */
  #fetchVapidPublicKey(): Promise<string> {
    return firstValueFrom(
      this.#http
        .get<ApiEnvelope<VapidPublicKeyEnvelope>>(`${this.#base}/push/vapid-public-key`)
        .pipe(map((response) => response.data.public_key)),
    );
  }

  /**
   * POST the standard web-push subscription JSON, augmented with the person,
   * to the backend's store endpoint.
   */
  #register(personId: number, raw: RawSubscription): Promise<unknown> {
    return firstValueFrom(
      this.#http.post<ApiEnvelope<unknown>>(`${this.#base}/push-subscriptions`, {
        person_id: personId,
        endpoint: raw.endpoint,
        p256dh: raw.keys?.p256dh ?? '',
        auth: raw.keys?.auth ?? '',
      } satisfies PushSubscriptionPayload),
    );
  }
}
