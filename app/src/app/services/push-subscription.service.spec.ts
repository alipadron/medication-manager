import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { SwPush } from '@angular/service-worker';
import { vi } from 'vitest';

import { PushSubscriptionService } from './push-subscription.service';

/**
 * Sets up the browser globals push requires, then tears them down so the
 * "unsupported" case can be tested separately.
 */
function installPushGlobals(): void {
  Object.defineProperty(globalThis, 'isSecureContext', {
    value: true,
    configurable: true,
  });
  Object.defineProperty(globalThis, 'Notification', {
    value: { permission: 'granted', requestPermission: vi.fn() },
    configurable: true,
  });
}

function removePushGlobals(): void {
  delete (globalThis as { isSecureContext?: boolean }).isSecureContext;
  delete (globalThis as { Notification?: unknown }).Notification;
}

describe('PushSubscriptionService', () => {
  let service: PushSubscriptionService;
  let httpMock: HttpTestingController;
  let requestSubscription: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    // Angular does not reset the TestBed across Vitest test files here and a
    // prior file may have left it instantiated — clear it before configuring.
    TestBed.resetTestingModule();

    requestSubscription = vi.fn(() =>
      Promise.resolve({
        toJSON: () => ({
          endpoint: 'https://fcm.googleapis.com/fcm/send/token123',
          keys: { p256dh: 'P256-DH-KEY', auth: 'AUTH-SECRET' },
        }),
      }),
    );

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: SwPush, useValue: { requestSubscription } },
      ],
    });

    service = TestBed.inject(PushSubscriptionService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    TestBed.resetTestingModule();
    removePushGlobals();
  });

  it('fetches the VAPID key, subscribes, and registers with the backend', async () => {
    installPushGlobals();

    const result = service.enable(3);

    // VAPID public key read from the backend first.
    httpMock.expectOne('/api/v1/push/vapid-public-key').flush({
      data: { public_key: 'VAPID-PUBLIC-KEY' },
    });

    // Let the async enable() flow past the VAPID read onto the POST.
    await new Promise((resolve) => setTimeout(resolve, 0));

    // The subscription is then POSTed with the person + web-push JSON.
    const postRequest = httpMock.expectOne('/api/v1/push-subscriptions');
    expect(postRequest.request.method).toBe('POST');
    expect(postRequest.request.body).toEqual({
      person_id: 3,
      endpoint: 'https://fcm.googleapis.com/fcm/send/token123',
      p256dh: 'P256-DH-KEY',
      auth: 'AUTH-SECRET',
    });
    postRequest.flush({ data: { id: 1 } });

    await expect(result).resolves.toBe('subscribed');
    expect(requestSubscription).toHaveBeenCalledWith({
      serverPublicKey: 'VAPID-PUBLIC-KEY',
    });
    expect(service.status()).toBe('subscribed');
  });

  it('degrades gracefully to unsupported when not in a supported context', async () => {
    // Simulate a browser where push simply cannot work (insecure context,
    // no notifications API): the service must not call out to the backend.
    delete (globalThis as { Notification?: unknown }).Notification;
    Object.defineProperty(globalThis, 'isSecureContext', {
      value: false,
      configurable: true,
    });

    const result = service.enable(1);

    httpMock.expectNone('/api/v1/push/vapid-public-key');
    httpMock.expectNone('/api/v1/push-subscriptions');

    await expect(result).resolves.toBe('unsupported');
    expect(service.status()).toBe('unsupported');
    expect(requestSubscription).not.toHaveBeenCalled();
  });

  it('surfaces a denied status when the browser blocks permission', async () => {
    installPushGlobals();
    // The prompt is refused.
    (globalThis.Notification as { permission: string }).permission = 'denied';
    requestSubscription.mockRejectedValueOnce(new Error('User denied.'));

    const result = service.enable(4);

    httpMock.expectOne('/api/v1/push/vapid-public-key').flush({
      data: { public_key: 'VAPID-PUBLIC-KEY' },
    });

    await expect(result).resolves.toBe('denied');
    expect(service.status()).toBe('denied');
  });
});
