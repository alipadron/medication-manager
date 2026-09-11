import { Component, inject, input } from '@angular/core';

import { NudgeService } from '../../services/nudge.service';

/**
 * A tiny, accessible affordance to ping the OTHER household member on their
 * enrolled devices (a "nudge"). Just plumbing: POSTs to the backend nudge
 * endpoint for the target `personId` and shows the outcome. Consistent with
 * the existing push-status affordance (Tailwind-only, native control flow).
 */
@Component({
  selector: 'app-nudge',
  imports: [],
  template: `
    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
      <button
        type="button"
        class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-1.5 font-medium text-indigo-700 transition-colors hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
        [disabled]="isDisabled()"
        (click)="send()"
      >
        @if (state() === 'sending') {
          Sending nudge…
        } @else {
          Nudge {{ personName() }} to take their meds
        }
      </button>

      @switch (state()) {
        @case ('sent') {
          @if (notified() > 0) {
            <p role="status" class="text-emerald-700">
              Nudge delivered to {{ notified() }}
              device{{ notified() === 1 ? '' : 's' }}.
            </p>
          } @else {
            <p role="status" class="text-gray-600">
              Nudge sent — {{ personName() }} has no enrolled device yet.
            </p>
          }
        }
        @case ('error') {
          <p role="alert" class="text-red-700">{{ error() }}</p>
        }
      }
    </div>
  `,
})
export class NudgeComponent {
  readonly personId = input<number | undefined>();
  readonly personName = input<string>('household member');

  readonly #nudge = inject(NudgeService);

  readonly state = this.#nudge.state;
  readonly error = this.#nudge.error;
  readonly notified = this.#nudge.notified;

  protected isDisabled(): boolean {
    return this.state() === 'sending' || this.personId() === undefined;
  }

  protected send(): void {
    const id = this.personId();
    if (id !== undefined) {
      void this.#nudge.send(id);
    }
  }
}