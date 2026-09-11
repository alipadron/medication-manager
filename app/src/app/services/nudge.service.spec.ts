import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { NudgeService } from './nudge.service';

describe('NudgeService', () => {
  let service: NudgeService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    // Angular does not reset the TestBed across Vitest test files here and a
    // prior file may have left it instantiated — clear it before configuring.
    TestBed.resetTestingModule();

    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(NudgeService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    TestBed.resetTestingModule();
  });

  it('POSTs the nudge for the target person and reports delivery', async () => {
    const result = service.send(2);

    const request = httpMock.expectOne('/api/v1/push/nudge');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ person_id: 2 });

    request.flush({ data: { person_id: 2, notified: 3 } });

    await expect(result).resolves.toBe('sent');
    expect(service.state()).toBe('sent');
    expect(service.notified()).toBe(3);
    expect(service.error()).toBeNull();
  });

  it('falls back to a zero-device report without erroring', async () => {
    const result = service.send(2);

    httpMock.expectOne('/api/v1/push/nudge').flush({ data: { person_id: 2, notified: 0 } });

    await expect(result).resolves.toBe('sent');
    expect(service.notified()).toBe(0);
    expect(service.error()).toBeNull();
  });

  it('surfaces a failure as error state with a message', async () => {
    const result = service.send(2);

    httpMock.expectOne('/api/v1/push/nudge').error(
      new ErrorEvent('Server Error', { message: 'boom' }),
      { status: 422, statusText: 'Unprocessable Content' },
    );

    await expect(result).resolves.toBe('error');
    expect(service.state()).toBe('error');
    expect(service.error()).not.toBeNull();
  });
});