import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', redirectTo: '/today', pathMatch: 'full' },
  {
    path: 'today/:personId?',
    loadComponent: () =>
      import('./pages/today-schedule/today-schedule.component').then(
        (m) => m.TodayScheduleComponent,
      ),
  },
  { path: '**', redirectTo: '/today' },
];