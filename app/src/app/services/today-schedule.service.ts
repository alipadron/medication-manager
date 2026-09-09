import { Service, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, combineLatest, forkJoin, map, switchMap, throwError } from 'rxjs';

import {
  ApiEnvelope,
  Medication,
  MedicationPlanView,
  Person,
  PersonTodayView,
  SchedulePlan,
  ScheduleSummary,
} from '../models/schedule';

/**
 * Thin data layer over the Laravel API.
 *
 * The backend is the source of truth: this service only reads what it serves
 * and joins the small household graph (person -> medication -> schedule ->
 * authoritative plan). It never computes a schedule; the `schedules/{id}/plan`
 * read already answers "when is the next interval dose due?" and "what is
 * today's (possibly shifted) fixed-times grid?".
 *
 * Dev connectivity is handled by the ng serve dev-server proxy in
 * angular.json (`proxyConfig`): `/api` is forwarded to http://127.0.0.1:8000.
 */
@Service()
export class TodayScheduleService {
  readonly #http = inject(HttpClient);

  readonly #base = '/api/v1';

  /** Read a collection route, unwrapping Laravel's `{ data: [...] }` envelope. */
  #list<T>(path: string, params?: Record<string, string>): Observable<T[]> {
    return this.#http
      .get<ApiEnvelope<T[]>>(`${this.#base}${path}`, { params })
      .pipe(map((response) => response.data));
  }

  /** Read a single-item route, unwrapping Laravel's `{ data: {...} }` envelope. */
  #get<T>(path: string): Observable<T> {
    return this.#http
      .get<ApiEnvelope<T>>(`${this.#base}${path}`)
      .pipe(map((response) => response.data));
  }

  /** All household members. */
  people(): Observable<Person[]> {
    return this.#list<Person>('/people');
  }

  /** Medications prescribed to a person. */
  medications(personId: number): Observable<Medication[]> {
    return this.#list<Medication>('/medications', {
      person_id: personId.toString(),
    });
  }

  /** All schedules across the household. */
  schedules(): Observable<ScheduleSummary[]> {
    return this.#list<ScheduleSummary>('/schedules');
  }

  /**
   * The authoritative plan for one schedule (no date => server's today).
   */
  plan(scheduleId: number): Observable<SchedulePlan> {
    return this.#get<SchedulePlan>(`/schedules/${scheduleId}/plan`);
  }

  /**
   * Compose a person's whole today view from the server's own reads: the
   * person records, their medications, those medications' schedules, and each
   * schedule's authoritative plan. Plans are read in parallel; a person with
   * no scheduled medication yields an empty list.
   */
  personToday(personId: number): Observable<PersonTodayView> {
    return combineLatest({
      people: this.people(),
      medications: this.medications(personId),
      schedules: this.schedules(),
    }).pipe(
      switchMap(({ people, medications, schedules }) => {
        const person =
          people.find((candidate) => candidate.id === personId) ?? null;

        if (person === null) {
          return throwError(
            () => new Error(`No person exists with id ${personId}`),
          );
        }

        const medicationByName = new Map(
          medications.map((medication) => [medication.id, medication]),
        );

        const scheduledMedications = schedules
          .filter((schedule) =>
            medications.some(
              (medication) => medication.id === schedule.medication_id,
            ),
          )
          .sort((a, b) => a.id - b.id);

        if (scheduledMedications.length === 0) {
          return of({
            person,
            medications: [],
          } satisfies PersonTodayView);
        }

        return forkJoin(
          scheduledMedications.map((schedule) =>
            this.plan(schedule.id).pipe(
              map((plan): MedicationPlanView => {
                const medication = medicationByName.get(schedule.medication_id);

                return {
                  medicationId: schedule.medication_id,
                  medicationName: medication?.name ?? 'Unknown medication',
                  scheduleId: schedule.id,
                  dosingPattern: schedule.dosing_pattern,
                  plan,
                };
              }),
            ),
          ),
        ).pipe(
          map(
            (medications): PersonTodayView => ({
              person,
              medications,
            }),
          ),
        );
      }),
    );
  }
}