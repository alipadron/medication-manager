import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { StockView } from '../../models/stock';
import { StockService } from '../../services/stock.service';
import { StockComponent } from './stock.component';

const stockView: StockView = {
  packages: [
    {
      id: 1,
      medicationName: 'Ibuprofen',
      remainingDoses: 0,
      forecast: {
        status: 'empty',
        doses_remaining: 0,
        doses_per_day: 3,
        days_of_supply_left: 0,
        refill_by: '2026-09-11',
        refill_by_day: 'Friday',
        message: 'Out of doses, refill now',
      },
    },
    {
      id: 2,
      medicationName: 'Antibiotic',
      remainingDoses: 4,
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
    {
      id: 3,
      medicationName: 'Vitamin C',
      remainingDoses: 60,
      forecast: {
        status: 'ok',
        doses_remaining: 60,
        doses_per_day: 1,
        days_of_supply_left: 60,
        refill_by: '2026-11-01',
        refill_by_day: 'Sunday',
        message: '60 doses left, refill by Sunday',
      },
    },
    {
      id: 4,
      medicationName: 'Old cream',
      remainingDoses: 10,
      forecast: null,
    },
  ],
  inventory: [
    { id: 1, name: 'Old antibiotic', expiry_date: '2000-01-01', created_at: '' },
    { id: 2, name: 'Painkiller gel', expiry_date: '2099-12-31', created_at: '' },
    { id: 3, name: 'Multivitamin', expiry_date: null, created_at: '' },
  ],
};

class FakeStockService {
  stock = () => of(stockView);
}

describe('StockComponent', () => {
  let fixture: ComponentFixture<StockComponent>;

  const text = (): string => fixture.nativeElement.textContent as string;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StockComponent],
      providers: [{ provide: StockService, useClass: FakeStockService }],
    }).compileComponents();

    fixture = TestBed.createComponent(StockComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the section headings', () => {
    expect(text()).toContain('Stock & inventory');
    expect(text()).toContain('Refill forecast');
    expect(text()).toContain('Home inventory');
  });

  it('surfaces a running-low package with the server forecast message', () => {
    expect(text()).toContain('Antibiotic');
    expect(text()).toContain('Running low — refill soon');
    expect(text()).toContain('4 doses left, refill by Saturday');
    expect(text()).toContain('2'); // doses per day figure rounds out the shown metrics
  });

  it('highlights an empty package with the "empty" status', () => {
    expect(text()).toContain('Ibuprofen');
    expect(text()).toContain('Empty — refill now');
    expect(text()).toContain('Out of doses, refill now');
  });

  it('shows an "ok" package and a no-schedule package', () => {
    expect(text()).toContain('Vitamin C');
    expect(text()).toContain('60 doses left, refill by Sunday');
    expect(text()).toContain('Old cream');
    expect(text()).toContain('No schedule');
  });

  it('lists inventory entries with their expiry dates', () => {
    expect(text()).toContain('Old antibiotic');
    expect(text()).toContain('Expires 2000-01-01');
    expect(text()).toContain('past its date');
    // A future expiry is shown as a plain date.
    const gelRow = Array.from(
      fixture.nativeElement.querySelectorAll('li') as NodeListOf<HTMLLIElement>,
    ).find((node) => node.textContent?.includes('Painkiller gel'));
    expect(gelRow?.textContent).toContain('Expires 2099-12-31');
    expect(gelRow?.textContent).not.toContain('past its date');
    // An entry without a recorded expiry signals that clearly.
    expect(text()).toContain('No expiry date');
  });

  it('orders packages by attention: empty before running-low before ok before no-schedule', () => {
    const articles = Array.from(
      fixture.nativeElement.querySelectorAll('article'),
    ).map((node) => (node as HTMLElement).querySelector('h3')?.textContent?.trim());

    expect(articles).toEqual([
      'Ibuprofen',
      'Antibiotic',
      'Vitamin C',
      'Old cream',
    ]);
  });
});