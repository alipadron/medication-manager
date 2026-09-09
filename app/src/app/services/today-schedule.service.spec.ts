import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';

import { TodayScheduleService } from './today-schedule.service';

describe('TodayScheduleService', () => {
  let service: TodayScheduleService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(TodayScheduleService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('unwraps the Laravel data envelope for a collection route', () => {
    let result: unknown;
    service.people().subscribe((value) => (result = value));

    const request = httpMock.expectOne('/api/v1/people');
    expect(request.request.method).toBe('GET');
    request.flush({
      data: [{ id: 1, name: 'Alex', created_at: '' }],
    });

    expect(result).toEqual([{ id: 1, name: 'Alex', created_at: '' }]);
  });

  it('composes a person today view from people, meds, schedules and plans', () => {
    let result: unknown;
    service.personToday(1).subscribe((value) => (result = value));

    httpMock.expectOne('/api/v1/people').flush({
      data: [{ id: 1, name: 'Alex', created_at: '' }],
    });
    httpMock.expectOne('/api/v1/medications?person_id=1').flush({
      data: [{ id: 1, person_id: 1, name: 'Ibuprofen', created_at: '' }],
    });
    httpMock.expectOne('/api/v1/schedules').flush({
      data: [
        {
          id: 5,
          medication_id: 1,
          dosing_pattern: 'interval',
          interval_hours: 8,
          nominal_start_at: null,
          safe_interval_minutes: null,
          created_at: '',
        },
      ],
    });
    httpMock.expectOne('/api/v1/schedules/5/plan').flush({
      data: {
        id: 5,
        medication_id: 1,
        dosing_pattern: 'interval',
        interval_hours: 8,
        nominal_start_at: null,
        last_actual_dose_at: null,
        due_at: null,
        due_status: 'unknown',
      },
    });

    expect(result).toEqual({
      person: { id: 1, name: 'Alex', created_at: '' },
      medications: [
        {
          medicationId: 1,
          medicationName: 'Ibuprofen',
          scheduleId: 5,
          dosingPattern: 'interval',
          plan: {
            id: 5,
            medication_id: 1,
            dosing_pattern: 'interval',
            interval_hours: 8,
            nominal_start_at: null,
            last_actual_dose_at: null,
            due_at: null,
            due_status: 'unknown',
          },
        },
      ],
    });
  });
});