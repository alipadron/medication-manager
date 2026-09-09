import { Routes } from '@angular/router';

const todaySchedule = () =>
  import('./pages/today-schedule/today-schedule.component').then(
    (m) => m.TodayScheduleComponent,
  );

export const routes: Routes = [
  { path: '', redirectTo: 'today', pathMatch: 'full' },
  { path: 'today', loadComponent: todaySchedule },
  { path: 'today/:personId', loadComponent: todaySchedule },
  { path: '**', redirectTo: 'today' },
];