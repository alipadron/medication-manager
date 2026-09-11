import { HttpClient } from '@angular/common/http';
import { Service, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { ApiEnvelope } from '../models/schedule';

/** What last happened with a user-initiated nudge. */
export type NudgeState = 'idle' | 'sending' | 'sent' | 'error';

interface NudgeEnvelope {
  person_id: number;
  /** How many enrolled devices of the target person received the nudge. */
  notified: number;
}

/**
 * Thin write to the backend's nudge endpoint: ping the OTHER household
 * member (the target `person_id`) on their enrolled devices. User-initiated
 * on purpose — nudges are not deduped; each call is a fresh intentional
 * reminder.
 */
@Service()
export class NudgeService {
  readonly #http = inject(HttpClient);

  readonly #base = '/api/v1';

  readonly state = signal<NudgeState>('idle');
  readonly error = signal<string | null>(null);
  /** Devices that received the last nudge (0 = target has none enrolled). */
  readonly notified = signal(0);

  /**
   * POST the nudge for a person and track the outcome in signals. Never
   * throws: failures surface as `state === 'error'` with a message.
   */
  async send(personId: number): Promise<NudgeState> {
    this.error.set(null);
    this.notified.set(0);
    this.state.set('sending');

    try {
      const envelope = await firstValueFrom(
        this.#http.post<ApiEnvelope<NudgeEnvelope>>(`${this.#base}/push/nudge`, {
          person_id: personId,
        }),
      );
      this.notified.set(envelope.data.notified);
      this.state.set('sent');
    } catch (failure) {
      this.state.set('error');
      this.error.set(
        failure instanceof Error ? failure.message : 'Could not send the nudge.',
      );
    }

    return this.state();
  }
}