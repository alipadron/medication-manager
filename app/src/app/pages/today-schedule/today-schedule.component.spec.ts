import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import {
  DoseEvent,
  FixedTimesPlan,
  IntervalPlan,
  PersonTodayView,
  SchedulePlan,
  StoreDoseEventPayload,
} from '../../models/schedule';
import { TodayScheduleService } from '../../services/today-schedule.service';
import { TodayScheduleComponent } from './today-schedule.component';

const initialIntervalPlan: IntervalPlan = {
  id: 1,
  medication_id: 1,
  dosing_pattern: 'interval',
  interval_hours: 8,
  nominal_start_at: null,
  last_actual_dose_at: null,
  due_at: '2026-09-09T14:00:00Z',
  due_status: 'upcoming',
};

const initialFixedPlan: FixedTimesPlan = {
  id: 2,
  medication_id: 2,
  dosing_pattern: 'fixed_times',
  safe_interval_minutes: 240,
  date: '2026-09-09',
  intakes: [
    { id: 1, time: '08:00', planned_time: '08:00', status: 'scheduled' },
    { id: 2, time: '20:00', planned_time: '20:30', status: 'late' },
  ],
};

const todayView: PersonTodayView = {
  person: { id: 1, name: 'Alex', created_at: '' },
  medications: [
    {
      medicationId: 1,
      medicationName: 'Ibuprofen',
      scheduleId: 1,
      dosingPattern: 'interval',
      plan: initialIntervalPlan,
    },
    {
      medicationId: 2,
      medicationName: 'Antibiotic',
      scheduleId: 2,
      dosingPattern: 'fixed_times',
      plan: initialFixedPlan,
    },
  ],
};

const recordedEvent: DoseEvent = {
  id: 1,
  schedule_id: 1,
  intake_id: null,
  outcome: 'on_time',
  taken_at: null,
  created_at: '',
};

class FakeTodayScheduleService {
  /** Dose-event payloads the component posted, in order. */
  readonly recorded: StoreDoseEventPayload[] = [];
  /** Schedule ids whose plan was re-read. */
  readonly planCalls: number[] = [];

  intervalPlan: SchedulePlan = initialIntervalPlan;
  fixedPlan: SchedulePlan = initialFixedPlan;

  people = () => of([{ id: 1, name: 'Alex', created_at: '' }]);
  personToday = () => of(todayView);
  recordDoseEvent = (payload: StoreDoseEventPayload) => {
    this.recorded.push(payload);
    return of(recordedEvent);
  };
  plan = (scheduleId: number) => {
    this.planCalls.push(scheduleId);
    return of(scheduleId === 2 ? this.fixedPlan : this.intervalPlan);
  };
}

describe('TodayScheduleComponent', () => {
  let fixture: ComponentFixture<TodayScheduleComponent>;
  let service: FakeTodayScheduleService;

  const text = (): string => fixture.nativeElement.textContent as string;

  /** The Nth medication card (`article`) in the today view. */
  const card = (index: number): HTMLElement =>
    fixture.nativeElement.querySelectorAll('article')[index] as HTMLElement;

  /** A button by its visible label, scoped to a card. */
  const button = (root: HTMLElement, label: string): HTMLButtonElement => {
    const match = Array.from(root.querySelectorAll('button')).find(
      (candidate) => candidate.textContent?.trim() === label,
    );
    if (match === undefined) {
      throw new Error(`No button labelled "${label}"`);
    }
    return match as HTMLButtonElement;
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TodayScheduleComponent],
      providers: [
        provideRouter([]),
        {
          provide: TodayScheduleService,
          useClass: FakeTodayScheduleService,
        },
      ],
    }).compileComponents();

    service = TestBed.inject(
      TodayScheduleService,
    ) as unknown as FakeTodayScheduleService;

    fixture = TestBed.createComponent(TodayScheduleComponent);
    fixture.componentRef.setInput('personId', '1');
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the person name heading', () => {
    expect(text()).toContain("Today's schedule for Alex");
  });

  it('renders an interval med with its server due time', () => {
    expect(text()).toContain('Ibuprofen');
    expect(text()).toContain('Next dose due');
    expect(text()).toContain('Every 8 hours');
    expect(text()).toContain('upcoming');
  });

  it('renders a fixed-times med with its day grid and statuses', () => {
    expect(text()).toContain('Antibiotic');
    expect(text()).toContain("Today's intakes");
    expect(text()).toContain('20:30');
    expect(text()).toContain('Taken late');
  });

  it('records an interval on-time dose against the schedule and re-reads the plan', () => {
    service.intervalPlan = {
      ...initialIntervalPlan,
      last_actual_dose_at: '2026-09-09T16:00:00Z',
      due_at: '2026-09-10T00:00:00Z',
      due_status: 'overdue',
    };

    button(card(0), 'Taken on time').click();
    fixture.detectChanges();

    expect(service.recorded).toHaveLength(1);
    expect(service.recorded[0].outcome).toBe('on_time');
    // Interval meds have no intake grid: the dose resolves to the schedule.
    expect(service.recorded[0].schedule_id).toBe(1);
    expect(service.recorded[0].intake_id).toBeUndefined();
    expect(typeof service.recorded[0].taken_at).toBe('string');
    expect(service.planCalls).toEqual([1]);
    // The view now renders the server-re-anchored due status.
    expect(text()).toContain('overdue');
  });

  it('records an interval skip without a taken time', () => {
    button(card(0), 'Skipped').click();
    fixture.detectChanges();

    expect(service.recorded).toEqual([{ outcome: 'skipped', schedule_id: 1 }]);
    expect(service.planCalls).toEqual([1]);
  });

  it('records a fixed-times dose against its intake slot', () => {
    // Only the still-scheduled intake (08:00, id 1) offers actions.
    button(card(1), 'Taken on time').click();
    fixture.detectChanges();

    expect(service.recorded).toEqual([{ outcome: 'on_time', intake_id: 1 }]);
    expect(service.planCalls).toEqual([2]);
  });

  it('records a fixed-times late dose with the chosen time and shows the shifted grid', () => {
    service.fixedPlan = {
      ...initialFixedPlan,
      intakes: [
        { id: 1, time: '08:00', planned_time: '10:00', status: 'late' },
        { id: 2, time: '20:00', planned_time: '22:00', status: 'scheduled' },
      ],
    };

    const fixed = card(1);
    button(fixed, 'Taken late').click();
    fixture.detectChanges();

    const input = fixed.querySelector<HTMLInputElement>(
      'input[type="datetime-local"]',
    );
    expect(input).not.toBeNull();
    input!.value = '2026-09-09T10:15';
    input!.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    button(fixed, 'Save late dose').click();
    fixture.detectChanges();

    expect(service.recorded).toHaveLength(1);
    expect(service.recorded[0].outcome).toBe('late');
    expect(service.recorded[0].intake_id).toBe(1);
    expect(service.recorded[0].taken_at).toBe(
      new Date('2026-09-09T10:15').toISOString(),
    );
    expect(service.planCalls).toEqual([2]);
    // The grid now shows the server's shifted planned time.
    expect(text()).toContain('22:00');
  });
});
