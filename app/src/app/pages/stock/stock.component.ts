import { Component, computed, inject } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';

import { PackageForecast } from '../../models/stock';
import { StockService } from '../../services/stock.service';

/** A package's display status: the server status, or no-schedule when the
 *  forecast envelope is absent (the medication has no consumable schedule). */
type DisplayStatus = 'ok' | 'refill_soon' | 'empty' | 'no-schedule';

/**
 * Stock & inventory view, fetched live from the backend.
 *
 * Everything shown comes straight from the API: each package carries its
 * server-computed refill forecast (status, doses left, days of supply,
 * refill-by date + message), and the home inventory lists leftover
 * medications with their expiry dates. The client performs no forecast or
 * consumption math — it renders the server's envelope and highlights the
 * running-low packages.
 */
@Component({
  selector: 'app-stock',
  templateUrl: './stock.component.html',
})
export class StockComponent {
  readonly #service = inject(StockService);

  readonly #stockResource = rxResource({
    stream: () => this.#service.stock(),
  });

  readonly stock = this.#stockResource.value;
  readonly isLoading = this.#stockResource.isLoading;
  readonly error = this.#stockResource.error;

  /** Packages in attention order: empty first, then running low, then the rest. */
  readonly sortedPackages = computed(() =>
    [...(this.stock()?.packages ?? [])].sort(
      (a, b) =>
        this.statusOrder[this.displayStatus(a.forecast)] -
        this.statusOrder[this.displayStatus(b.forecast)],
    ),
  );

  protected readonly statusOrder: Record<DisplayStatus, number> = {
    empty: 0,
    refill_soon: 1,
    ok: 2,
    'no-schedule': 3,
  };

  protected readonly statusLabel: Record<DisplayStatus, string> = {
    empty: 'Empty — refill now',
    refill_soon: 'Running low — refill soon',
    ok: 'OK',
    'no-schedule': 'No schedule',
  };

  protected displayStatus(forecast: PackageForecast | null): DisplayStatus {
    return forecast === null ? 'no-schedule' : forecast.status;
  }

  protected isLow(forecast: PackageForecast | null): boolean {
    return forecast !== null && forecast.status !== 'ok';
  }

  protected isExpired(expiryDate: string | null): boolean {
    if (expiryDate === null) {
      return false;
    }
    // Display concern only: has this leftover medic pass its recorded date?
    return expiryDate < this.#today;
  }

  #today: string;
  constructor() {
    const now = new Date();
    const pad = (part: number): string => part.toString().padStart(2, '0');
    this.#today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
  }
}