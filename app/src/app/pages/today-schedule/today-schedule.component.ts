import {
  Component,
  DestroyRef,
  ElementRef,
  computed,
  effect,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { rxResource, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap } from 'rxjs';

import {
  DoseEventOutcome,
  FixedIntakePlan,
  FixedTimesPlan,
  IntervalPlan,
  MedicationPlanView,
  PersonTodayView,
  SchedulePlan,
  StoreDoseEventPayload,
} from '../../models/schedule';
import { TodayScheduleService } from '../../services/today-schedule.service';

/** The schedule a dose resolves to, plus the intake grid slot for fixed-times. */
interface DoseRef {
  scheduleId: number;
  /** Null for interval meds, which have no intake grid. */
  intakeId: number | null;
}

/** A dose the user can act on, carrying the name for feedback copy. */
interface DoseTarget extends DoseRef {
  medicationName: string;
}

/**
 * Today's schedule for one person, fetched live from the backend.
 *
 * Everything shown comes from `schedules/{id}/plan`: interval meds render the
 * server-computed rolling due time, fixed-times meds render the server's day
 * grid. Dose actions are thin writes — the client posts a dose event and then
 * re-reads the authoritative plan, so an interval cadence re-anchors and a
 * fixed-times grid shifts without any client-side schedule computation.
 */
@Component({
  selector: 'app-today-schedule',
  imports: [RouterLink],
  templateUrl: './today-schedule.component.html',
})
export class TodayScheduleComponent {
  readonly #service = inject(TodayScheduleService);
  readonly #destroyRef = inject(DestroyRef);

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

  readonly isLoading = this.#todayResource.isLoading;
  readonly error = this.#todayResource.error;

  /** Plans re-read after a dose action, keyed by schedule id. */
  readonly #planOverrides = signal<ReadonlyMap<number, SchedulePlan>>(new Map());

  /** The today view with any post-action plan overrides applied. */
  readonly today = computed<PersonTodayView | undefined>(() => {
    const base = this.#todayResource.value();
    if (base === undefined) {
      return undefined;
    }

    const overrides = this.#planOverrides();
    if (overrides.size === 0) {
      return base;
    }

    return {
      person: base.person,
      medications: base.medications.map((medication) => {
        const plan = overrides.get(medication.scheduleId);
        return plan === undefined ? medication : { ...medication, plan };
      }),
    };
  });

  /** The dose action currently in flight (keyed), or null. */
  readonly submittingKey = signal<string | null>(null);
  /** The late-time form currently open (keyed), or null. */
  readonly lateTargetKey = signal<string | null>(null);
  /** The chosen actual time in the open late form (`datetime-local`). */
  readonly lateTakenAt = signal('');
  /** The last dose-action failure, shown to the user as an alert. */
  readonly actionError = signal<string | null>(null);
  /** The last successful dose-action confirmation. */
  readonly actionNotice = signal<string | null>(null);

  protected readonly lateInput =
    viewChild<ElementRef<HTMLInputElement>>('lateInput');

  constructor() {
    // Move focus into the late form when it opens (WCAG focus management).
    effect(() => {
      const isOpen = this.lateTargetKey() !== null;
      const input = this.lateInput();
      if (isOpen && input !== undefined) {
        input.nativeElement.focus();
      }
    });
  }

  /** Narrow a plan to the interval variant (or null) for template branching. */
  protected asInterval(plan: SchedulePlan): IntervalPlan | null {
    return plan.dosing_pattern === 'interval' ? plan : null;
  }

  /** Narrow a plan to the fixed-times variant (or null) for template branching. */
  protected asFixed(plan: SchedulePlan): FixedTimesPlan | null {
    return plan.dosing_pattern === 'fixed_times' ? plan : null;
  }

  /** The dose reference for an interval med: it resolves to its schedule. */
  protected scheduleTarget(medication: MedicationPlanView): DoseTarget {
    return {
      scheduleId: medication.scheduleId,
      intakeId: null,
      medicationName: medication.medicationName,
    };
  }

  /** The dose reference for one fixed-times intake row. */
  protected intakeTarget(
    medication: MedicationPlanView,
    intake: FixedIntakePlan,
  ): DoseTarget {
    return {
      scheduleId: medication.scheduleId,
      intakeId: intake.id,
      medicationName: medication.medicationName,
    };
  }

  /** Stable identity for a dose, used to key in-flight and open-form state. */
  protected doseKey(target: DoseRef): string {
    return `${target.scheduleId}:${target.intakeId ?? 'schedule'}`;
  }

  protected isSubmitting(target: DoseRef): boolean {
    return this.submittingKey() === this.doseKey(target);
  }

  protected isLateOpen(target: DoseRef): boolean {
    return this.lateTargetKey() === this.doseKey(target);
  }

  protected lateInputId(target: DoseRef): string {
    return `late-taken-at-${this.doseKey(target)}`;
  }

  /** Record a dose taken at its scheduled moment. */
  protected recordOnTime(target: DoseTarget): void {
    // An interval dose has no intake slot, so the server needs the actual
    // moment to re-anchor the rolling cadence. A fixed-times dose resolves
    // to its grid slot, whose time the server already owns.
    const takenAt =
      target.intakeId === null ? new Date().toISOString() : undefined;

    this.#record(target, 'on_time', takenAt);
  }

  /** Record a dose as skipped: the server flags it and moves nothing. */
  protected recordSkipped(target: DoseTarget): void {
    this.#record(target, 'skipped');
  }

  /** Open the inline late-time form for a dose, defaulting to now. */
  protected openLateForm(target: DoseTarget): void {
    this.actionError.set(null);
    this.actionNotice.set(null);
    this.lateTakenAt.set(this.#localDateTime(new Date()));
    this.lateTargetKey.set(this.doseKey(target));
  }

  protected cancelLateForm(): void {
    this.lateTargetKey.set(null);
    this.lateTakenAt.set('');
  }

  protected onLateInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.lateTakenAt.set(input.value);
  }

  protected submitLate(target: DoseTarget, event: Event): void {
    event.preventDefault();

    const value = this.lateTakenAt();
    if (value === '') {
      this.actionError.set('Choose the time the dose was actually taken.');
      return;
    }

    this.#record(target, 'late', new Date(value).toISOString());
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

  /**
   * Post one dose event, then re-read that schedule's authoritative plan so
   * the view reflects the server's re-anchored due time / shifted grid.
   */
  #record(
    target: DoseTarget,
    outcome: DoseEventOutcome,
    takenAt?: string,
  ): void {
    const key = this.doseKey(target);
    this.submittingKey.set(key);
    this.actionError.set(null);
    this.actionNotice.set(null);

    const payload: StoreDoseEventPayload = {
      outcome,
      // Interval meds have no intake grid: the dose resolves to the schedule.
      ...(target.intakeId === null
        ? { schedule_id: target.scheduleId }
        : { intake_id: target.intakeId }),
      ...(takenAt === undefined ? {} : { taken_at: takenAt }),
    };

    this.#service
      .recordDoseEvent(payload)
      .pipe(
        switchMap(() => this.#service.plan(target.scheduleId)),
        takeUntilDestroyed(this.#destroyRef),
      )
      .subscribe({
        next: (plan) => {
          this.#planOverrides.update((current) => {
            const next = new Map(current);
            next.set(target.scheduleId, plan);
            return next;
          });
          this.lateTargetKey.set(null);
          this.lateTakenAt.set('');
          this.submittingKey.set(null);
          this.actionNotice.set(
            `${this.#outcomeLabel(outcome)} for ${target.medicationName}; schedule updated from the server.`,
          );
        },
        error: (response: unknown) => {
          this.submittingKey.set(null);
          this.actionError.set(
            `Couldn't record the dose: ${
              response instanceof Error ? response.message : 'unknown error'
            }`,
          );
        },
      });
  }

  #outcomeLabel(outcome: DoseEventOutcome): string {
    switch (outcome) {
      case 'on_time':
        return 'Dose taken on time';
      case 'late':
        return 'Late dose';
      case 'skipped':
        return 'Skipped dose';
    }
  }

  /** Format a Date as the local value a `datetime-local` input expects. */
  #localDateTime(date: Date): string {
    const pad = (part: number): string => part.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
      date.getDate(),
    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
}
