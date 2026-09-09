import { Component, computed, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { rxResource } from '@angular/core/rxjs-interop';

import { FixedIntakePlan, FixedTimesPlan, IntervalPlan, SchedulePlan } from '../../models/schedule';
import { TodayScheduleService } from '../../services/today-schedule.service';

/**
 * Today's schedule for one person, fetched live from the backend.
 *
 * Everything shown comes from `schedules/{id}/plan`: interval meds render the
 * server-computed rolling due time, fixed-times meds render the server's day
 * grid. The client only displays what the API serves.
 */
@Component({
  selector: 'app-today-schedule',
  imports: [RouterLink],
  templateUrl: './today-schedule.component.html',
})
export class TodayScheduleComponent {
  readonly #service = inject(TodayScheduleService);

  /** Route param (/today/{personId}) bound into the component input. */
  readonly personId = input<string>();

  readonly #peopleResource = rxResource({
    stream: () => this.#service.people(),
  });

  readonly people = this.#peopleResource.value;

  /** Default to the first household member when no route param is given. */
  readonly selectedPersonId = computed(() => {
    const raw = this.personId();
    if (raw !== undefined && raw !== '') {
      return Number(raw);
    }
    return this.#peopleResource.value()?.[0]?.id;
  });

  readonly personName = computed(
    () =>
      this.people()?.find((person) => person.id === this.selectedPersonId())
        ?.name ?? 'household member',
  );

  readonly #todayResource = rxResource({
    params: () => this.selectedPersonId(),
    stream: ({ params }) => this.#service.personToday(params),
  });

  readonly today = this.#todayResource.value;
  readonly isLoading = this.#todayResource.isLoading;
  readonly error = this.#todayResource.error;

  /** Narrow a plan to the interval variant (or null) for template branching. */
  protected asInterval(plan: SchedulePlan): IntervalPlan | null {
    return plan.dosing_pattern === 'interval' ? plan : null;
  }

  /** Narrow a plan to the fixed-times variant (or null) for template branching. */
  protected asFixed(plan: SchedulePlan): FixedTimesPlan | null {
    return plan.dosing_pattern === 'fixed_times' ? plan : null;
  }

  /** Present an ISO instant as a local clock time (display only). */
  protected formatInstant(value: string | null): string {
    if (value === null) {
      return '—';
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? value
      : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  protected readonly intakeStatusLabels: Record<
    FixedIntakePlan['status'],
    string
  > = {
    scheduled: 'Scheduled',
    skipped: 'Skipped',
    on_time: 'Taken on time',
    late: 'Taken late',
    deferred: 'Deferred to next day',
  };
}