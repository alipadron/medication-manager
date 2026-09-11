import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';

import { StockService } from './stock.service';

describe('StockService', () => {
  let service: StockService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(StockService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('unwraps the Laravel envelope for packages', () => {
    let result: unknown;
    service.packages().subscribe((value) => (result = value));

    const request = httpMock.expectOne('/api/v1/packages');
    expect(request.request.method).toBe('GET');
    request.flush({
      data: [
        {
          id: 1,
          medication_id: 2,
          remaining_doses: 4,
          created_at: '',
          forecast: {
            status: 'refill_soon',
            doses_remaining: 4,
            doses_per_day: 2,
            days_of_supply_left: 2.0,
            refill_by: '2026-09-12',
            refill_by_day: 'Saturday',
            message: '4 doses left, refill by Saturday',
          },
        },
      ],
    });

    expect(result).toEqual([
      {
        id: 1,
        medication_id: 2,
        remaining_doses: 4,
        created_at: '',
        forecast: {
          status: 'refill_soon',
          doses_remaining: 4,
          doses_per_day: 2,
          days_of_supply_left: 2.0,
          refill_by: '2026-09-12',
          refill_by_day: 'Saturday',
          message: '4 doses left, refill by Saturday',
        },
      },
    ]);
  });

  it('unwraps the Laravel envelope for the home inventory', () => {
    let result: unknown;
    service.homeInventory().subscribe((value) => (result = value));

    const request = httpMock.expectOne('/api/v1/home-inventory');
    expect(request.request.method).toBe('GET');
    request.flush({
      data: [{ id: 1, name: 'Old antibiotic', expiry_date: '2025-01-01', created_at: '' }],
    });

    expect(result).toEqual([
      { id: 1, name: 'Old antibiotic', expiry_date: '2025-01-01', created_at: '' },
    ]);
  });

  it('composes the stock view, resolving package names from medications', () => {
    let result: unknown;
    service.stock().subscribe((value) => (result = value));

    httpMock.expectOne('/api/v1/packages').flush({
      data: [
        {
          id: 1,
          medication_id: 2,
          remaining_doses: 0,
          created_at: '',
          forecast: { status: 'empty' },
        },
        {
          id: 2,
          medication_id: 9,
          remaining_doses: 30,
          created_at: '',
          forecast: null,
        },
      ],
    });
    httpMock.expectOne('/api/v1/medications').flush({
      data: [
        { id: 2, name: 'Ibuprofen', person_id: 1, created_at: '' },
        { id: 9, name: 'Vitamin C', person_id: 1, created_at: '' },
      ],
    });
    httpMock.expectOne('/api/v1/home-inventory').flush({
      data: [{ id: 1, name: 'Old antibiotic', expiry_date: '2025-01-01', created_at: '' }],
    });

    expect(result).toEqual({
      packages: [
        {
          id: 1,
          medicationName: 'Ibuprofen',
          remainingDoses: 0,
          forecast: { status: 'empty' },
        },
        {
          id: 2,
          medicationName: 'Vitamin C',
          remainingDoses: 30,
          forecast: null,
        },
      ],
      inventory: [
        { id: 1, name: 'Old antibiotic', expiry_date: '2025-01-01', created_at: '' },
      ],
    });
  });
});