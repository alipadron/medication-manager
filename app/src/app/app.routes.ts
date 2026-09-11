import { Routes } from '@angular/router';

const todaySchedule = () =>
  import('./pages/today-schedule/today-schedule.component').then(
    (m) => m.TodayScheduleComponent,
  );

const stock = () =>
  import('./pages/stock/stock.component').then((m) => m.StockComponent);

export const routes: Routes = [
  { path: '', redirectTo: 'today', pathMatch: 'full' },
  { path: 'today', loadComponent: todaySchedule },
  { path: 'today/:personId', loadComponent: todaySchedule },
  { path: 'stock', loadComponent: stock },
  { path: '**', redirectTo: 'today' },
];