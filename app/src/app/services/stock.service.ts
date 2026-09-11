import { Service, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, forkJoin, map } from 'rxjs';

import {
  ApiEnvelope,
  Medication,
} from '../models/schedule';
import {
  HomeInventoryEntry,
  Package,
  PackageStockView,
  StockView,
} from '../models/stock';

/**
 * Thin data layer over the Laravel stock & inventory API.
 *
 * The backend is the source of truth: this service reads what it serves
 * (packages with their server-computed refill forecast, and the home
 * inventory box) and joins package records onto their medication names for
 * display. It never computes a forecast, depletion rate, or refill date —
 * the `packages` read already carries each forecast envelope.
 *
 * Dev connectivity is handled by the ng serve dev-server proxy in
 * angular.json (`proxyConfig`): `/api` is forwarded to http://127.0.0.1:8000.
 */
@Service()
export class StockService {
  readonly #http = inject(HttpClient);

  readonly #base = '/api/v1';

  /** Read a collection route, unwrapping Laravel's `{ data: [...] }` envelope. */
  #list<T>(path: string, params?: Record<string, string>): Observable<T[]> {
    return this.#http
      .get<ApiEnvelope<T[]>>(`${this.#base}${path}`, { params })
      .pipe(map((response) => response.data));
  }

  /** All supply units with their server-computed refill forecast. */
  packages(): Observable<Package[]> {
    return this.#list<Package>('/packages');
  }

  /** All household medications, used to resolve package names for display. */
  medications(): Observable<Medication[]> {
    return this.#list<Medication>('/medications');
  }

  /** The leftover home inventory box. */
  homeInventory(): Observable<HomeInventoryEntry[]> {
    return this.#list<HomeInventoryEntry>('/home-inventory');
  }

  /**
   * Compose the whole stock view from the server's own reads: packages,
   * medications (for display names) and the home inventory. A package whose
   * medication cannot be resolved keeps a fallback label rather than
   * dropping the stock record.
   */
  stock(): Observable<StockView> {
    return forkJoin({
      packages: this.packages(),
      medications: this.medications(),
      inventory: this.homeInventory(),
    }).pipe(
      map(({ packages, medications, inventory }) => {
        const nameByMedicationId = new Map(
          medications.map((medication) => [medication.id, medication.name]),
        );

        const stockPackages: PackageStockView[] = packages.map((pkg) => ({
          id: pkg.id,
          medicationName:
            nameByMedicationId.get(pkg.medication_id) ?? 'Unknown medication',
          remainingDoses: pkg.remaining_doses,
          forecast: pkg.forecast,
        }));

        return {
          packages: stockPackages,
          inventory,
        } satisfies StockView;
      }),
    );
  }
}