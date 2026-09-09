/**
 * Server-authoritative schedule types returned by the Laravel API.
 *
 * The client is thin: it never computes a schedule. These interfaces merely
 * describe the JSON the backend serves. See CONTEXT.md for the domain terms
 * (interval vs fixed-times, due time, shift, intake).
 */

export type DosingPattern = 'interval' | 'fixed_times';

export interface Person {
  id: number;
  name: string;
  created_at: string;
}

export interface Medication {
  id: number;
  person_id: number;
  name: string;
  created_at: string;
}

/** Fixed-times schedule intake stored on the backend. */
export interface Intake {
  id: number;
  time: string;
  sort_order: number;
}

export interface ScheduleSummary {
  id: number;
  medication_id: number;
  dosing_pattern: DosingPattern;
  interval_hours: number | null;
  nominal_start_at: string | null;
  safe_interval_minutes: number | null;
  intakes?: Intake[];
  created_at: string;
}

/**
 * Plan for an interval schedule: a single rolling "next dose is due at".
 * `due_status` is overdue | upcoming | unknown (no cadence anchor yet).
 */
export interface IntervalPlan {
  id: number;
  medication_id: number;
  dosing_pattern: 'interval';
  interval_hours: number | null;
  nominal_start_at: string | null;
  last_actual_dose_at: string | null;
  due_at: string | null;
  due_status: 'overdue' | 'upcoming' | 'unknown';
}

/**
 * One intake of a fixed-times day.
 * `status` is scheduled | skipped | on_time | late | deferred.
 */
export interface FixedIntakePlan {
  id: number;
  time: string;
  planned_time: string | null;
  status: 'scheduled' | 'skipped' | 'on_time' | 'late' | 'deferred';
}

/** Plan for a fixed-times schedule: that day's (possibly shifted) grid. */
export interface FixedTimesPlan {
  id: number;
  medication_id: number;
  dosing_pattern: 'fixed_times';
  safe_interval_minutes: number | null;
  date: string;
  intakes: FixedIntakePlan[];
}

export type SchedulePlan = IntervalPlan | FixedTimesPlan;

/** A medication on a person's today view, joined with its schedule plan. */
export interface MedicationPlanView {
  medicationId: number;
  medicationName: string;
  scheduleId: number;
  dosingPattern: DosingPattern;
  plan: SchedulePlan;
}

/** Everything the today view renders for one person, served by the API. */
export interface PersonTodayView {
  person: Person;
  medications: MedicationPlanView[];
}