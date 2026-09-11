/**
 * Server-authoritative stock types returned by the Laravel API.
 *
 * The client is thin: it never computes a refill forecast or depletion rate.
 * These interfaces merely describe the JSON the backend serves for GET
 * /api/v1/packages and GET /api/v1/home-inventory. The forecast envelope is
 * computed server-side (RefillForecaster) and attached to each package.
 */

/** Refill status the server derives for a package. */
export type ForecastStatus = 'ok' | 'refill_soon' | 'empty';

/**
 * The server-computed refill forecast envelope. `forecast` is `null` on a
 * package when the medication has no (consumable) schedule — the client
 * surfaces that as the "no-schedule" status.
 */
export interface PackageForecast {
  status: ForecastStatus;
  doses_remaining: number;
  doses_per_day: number;
  days_of_supply_left: number;
  refill_by: string; // Y-m-d
  refill_by_day: string; // e.g. 'Monday'
  message: string;
}

/** A supply unit of a medication, with its server-computed forecast. */
export interface Package {
  id: number;
  medication_id: number;
  remaining_doses: number;
  created_at: string;
  forecast: PackageForecast | null;
}

/** One leftover medication in the home inventory box, with its expiry. */
export interface HomeInventoryEntry {
  id: number;
  name: string;
  expiry_date: string | null; // Y-m-d, null when no expiry recorded
  created_at: string;
}

/** A package joined with the display name of the medication it supplies. */
export interface PackageStockView {
  id: number;
  medicationName: string;
  remainingDoses: number;
  forecast: PackageForecast | null;
}

/** Everything the stock view renders, served/joined from the API. */
export interface StockView {
  packages: PackageStockView[];
  inventory: HomeInventoryEntry[];
}