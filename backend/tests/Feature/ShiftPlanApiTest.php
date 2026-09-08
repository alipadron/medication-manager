<?php

use App\Models\DoseEvent;
use App\Models\Medication;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeShiftApiSchedule(array $times, int $safeIntervalMinutes = 240): Schedule
{
    $medication = Medication::factory()->create(['safe_interval_minutes' => $safeIntervalMinutes]);

    $schedule = Schedule::factory()->create(['medication_id' => $medication->id]);

    $schedule->intakes()->createMany(
        collect($times)->map(fn (string $time, int $index): array => [
            'time' => $time,
            'sort_order' => $index,
        ])->values()->all(),
    );

    return $schedule;
}

it('returns the unshifted schedule for a date with no dose events', function () {
    $schedule = makeShiftApiSchedule(['08:00', '20:00']);

    $this->getJson("/api/v1/schedules/{$schedule->id}/shifted?date=2026-09-08")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-09-08')
        ->assertJsonPath('data.safe_interval_minutes', 240)
        ->assertJsonCount(2, 'data.intakes')
        ->assertJsonPath('data.intakes.0.time', '08:00')
        ->assertJsonPath('data.intakes.0.status', 'scheduled')
        ->assertJsonPath('data.intakes.0.shifted_time', null);
});

it('returns the shifted schedule after a late dose', function () {
    $this->travelTo('2026-09-08 08:00:00');

    $schedule = makeShiftApiSchedule(['08:00', '12:00', '18:00']);

    $lateIntake = $schedule->intakes()->orderBy('sort_order')->firstOrFail();
    DoseEvent::factory()->late('2026-09-08 09:00:00')->create(['intake_id' => $lateIntake->id]);

    $this->getJson("/api/v1/schedules/{$schedule->id}/shifted?date=2026-09-08")
        ->assertOk()
        ->assertJsonPath('data.intakes.0.status', 'late')
        ->assertJsonPath('data.intakes.0.taken_at', '2026-09-08 09:00:00')
        ->assertJsonPath('data.intakes.1.shifted_time', '13:00')
        ->assertJsonPath('data.intakes.2.shifted_time', '19:00');
});

it('returns the shifted schedule for today by default', function () {
    $this->travelTo('2026-09-08 08:00:00');

    $schedule = makeShiftApiSchedule(['08:00', '12:00']);

    $this->getJson("/api/v1/schedules/{$schedule->id}/shifted")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-09-08');
});

it('rejects a malformed date on the shifted read', function () {
    $schedule = makeShiftApiSchedule(['08:00']);

    $this->getJson("/api/v1/schedules/{$schedule->id}/shifted?date=not-a-date")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('returns 404 for an unknown schedule on the shifted read', function () {
    $this->getJson('/api/v1/schedules/9999/shifted')
        ->assertNotFound();
});
