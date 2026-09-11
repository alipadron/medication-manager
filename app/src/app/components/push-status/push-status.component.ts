import { Component, inject, input } from '@angular/core';

import { PushSubscriptionService } from '../../services/push-subscription.service';

/**
 * A tiny affordance to enable web push on the current device for a person.
 * Just plumbing: requests permission, subscribes, and stores the subscription
 * on the backend. Delivery of actual notifications is a later phase.
 */
@Component({
  selector: 'app-push-status',
  imports: [],
  template: `
    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
      @switch (status()) {
        @case ('unsupported') {
          <p class="text-gray-500">Push notifications are not available in this browser.</p>
        }
        @case ('subscribed') {
          <p class="inline-flex items-center gap-1.5 text-emerald-700">
            <span
              aria-hidden="true"
              class="inline-block h-2 w-2 rounded-full bg-emerald-500"
            ></span>
            Notifications are enabled on this device.
          </p>
        }
        @case ('subscribing') {
          <p role="status" class="text-gray-600">Enabling notifications…</p>
        }
        @default {
          <button
            type="button"
            class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-1.5 font-medium text-indigo-700 transition-colors hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
            [disabled]="status() === 'subscribing' || personId() === undefined"
            (click)="enable()"
          >
            Enable push notifications for {{ personName() }}
          </button>
        }
      }

      @if (error(); as failure) {
        <p role="alert" class="text-red-700">{{ failure }}</p>
      }
    </div>
  `,
})
export class PushStatusComponent {
  readonly personId = input<number | undefined>();
  readonly personName = input<string>('this person');

  readonly #push = inject(PushSubscriptionService);

  readonly status = this.#push.status;
  readonly error = this.#push.error;

  protected enable(): void {
    const id = this.personId();
    if (id !== undefined) {
      void this.#push.enable(id);
    }
  }
}
