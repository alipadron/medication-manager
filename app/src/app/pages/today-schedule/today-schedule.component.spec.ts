import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { PersonTodayView } from '../../models/schedule';
import { TodayScheduleService } from '../../services/today-schedule.service';
import { TodayScheduleComponent } from './today-schedule.component';

const todayView: PersonTodayView = {
  person: { id: 1, name: 'Alex', created_at: '' },
  medications: [
    {
      medicationId: 1,
      medicationName: 'Ibuprofen',
      scheduleId: 1,
      dosingPattern: 'interval',
      plan: {
        id: 1,
        medication_id: 1,
        dosing_pattern: 'interval',
        interval_hours: 8,
        nominal_start_at: null,
        last_actual_dose_at: null,
        due_at: '2026-09-09T14:00:00Z',
        due_status: 'upcoming',
      },
    },
    {
      medicationId: 2,
      medicationName: 'Antibiotic',
      scheduleId: 2,
      dosingPattern: 'fixed_times',
      plan: {
        id: 2,
        medication_id: 2,
        dosing_pattern: 'fixed_times',
        safe_interval_minutes: 240,
        date: '2026-09-09',
        intakes: [
          { id: 1, time: '08:00', planned_time: '08:00', status: 'scheduled' },
          { id: 2, time: '20:00', planned_time: '20:30', status: 'late' },
        ],
      },
    },
  ],
};

class FakeTodayScheduleService {
  people = () => of([{ id: 1, name: 'Alex', created_at: '' }]);
  personToday = () => of(todayView);
}

describe('TodayScheduleComponent', () => {
  let fixture: ComponentFixture<TodayScheduleComponent>;

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

    fixture = TestBed.createComponent(TodayScheduleComponent);
    fixture.componentRef.setInput('personId', '1');
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the person name heading', () => {
    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain("Today's schedule for Alex");
  });

  it('renders an interval med with its server due time', () => {
    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Ibuprofen');
    expect(text).toContain('Next dose due');
    expect(text).toContain('Every 8 hours');
    expect(text).toContain('upcoming');
  });

  it('renders a fixed-times med with its day grid and statuses', () => {
    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Antibiotic');
    expect(text).toContain('Today\'s intakes');
    expect(text).toContain('20:30');
    expect(text).toContain('Taken late');
  });
});