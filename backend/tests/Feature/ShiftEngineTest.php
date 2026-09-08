<?php

use App\Models\DoseEvent;
use App\Models\Medication;
use App\Models\Schedule;
use App\Services\ShiftEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a medication with a schedule of intakes at the given times of day.
 *
 * @return array{schedule: Schedule, medication: Medication}
 */
function makeShiftSchedule(array $times, int $safeIntervalMinutes = 240): array
{
    $medication = Medication::factory()->create(['safe_interval_minutes' => $safeIntervalMinutes]);

    $schedule = Schedule::factory()->create(['medication_id' => $medication->id]);

    $schedule->intakes()->createMany(
        collect($times)->map(fn (string $time, int $index): array => [
            'time' => $time,
            'sort_order' => $index,
        ])->values()->all(),
    );

    return ['schedule' => $schedule, 'medication' => $medication];
}

/**
 * Shift a schedule for a fixed date and index the intakes by intake id.
 */
function shiftSchedule(Schedule $schedule, string $date = '2026-09-08')
{
    $shifted = app(ShiftEngine::class)->shiftForDate($schedule, CarbonImmutable::parse($date));

    return $shifted->intakes->keyBy(fn ($intake): int => $intake->intakeId);
}

/**
 * Record a late dose event for the given intake at an actual time of day.
 */
function recordLateDose(int $intakeId, string $actualTime): void
{
    DoseEvent::factory()->late("2026-09-08 {$actualTime}")->create(['intake_id' => $intakeId]);
}

it('shifts the remaining intakes of the day by the delay of a late dose', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '09:00'); // 1 hour late

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[0]->id]->status())->toBe('late');
    expect($shifted[$intakes[0]->id]->shiftedTime)->toBeNull();
    expect($shifted[$intakes[1]->id]->shiftedTime)->toBe('13:00');
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBe('19:00');
});

it('keeps the schedule unchanged when every dose is taken on time', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;

    foreach ($intakes as $intake) {
        DoseEvent::factory()->create(['intake_id' => $intake->id]);
    }

    $shifted = shiftSchedule($schedule);

    foreach ($intakes as $intake) {
        expect($shifted[$intake->id]->status())->toBe('on_time');
        expect($shifted[$intake->id]->shiftedTime)->toBeNull();
    }
});

it('cascades the delay when a later dose is taken late against its shifted slot', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '09:00');   // +60 min for the rest of the day
    recordLateDose($intakes[1]->id, '13:30');   // 30 min after its shifted 13:00 slot

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[1]->id]->status())->toBe('late');
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBe('19:30');
});

it('does not add delay when a dose is taken on time against its shifted slot', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '09:00');   // +60 min for the rest of the day
    DoseEvent::factory()->create(['intake_id' => $intakes[1]->id]); // taken at shifted 13:00

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[1]->id]->status())->toBe('on_time');
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBe('19:00');
});

it('never schedules two intakes closer than the safe interval after a shift', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '08:30', '09:00'], 120);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '08:10');

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[1]->id]->shiftedTime)->toBe('10:10');
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBe('12:10');
});

it('flags a skipped dose and leaves the rest of the day fixed', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;
    DoseEvent::factory()->skipped()->create(['intake_id' => $intakes[0]->id]);

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[0]->id]->status())->toBe('skipped');
    expect($shifted[$intakes[1]->id]->shiftedTime)->toBeNull();
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBeNull();
});

it('does not let a skipped dose reset the shift caused by an earlier late dose', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '12:00', '18:00']);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '09:00');   // +60 min for the rest of the day
    DoseEvent::factory()->skipped()->create(['intake_id' => $intakes[1]->id]);

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[1]->id]->status())->toBe('skipped');
    expect($shifted[$intakes[2]->id]->shiftedTime)->toBe('19:00');
});

it('defers a remaining intake whose shift would cross midnight', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '20:00']);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '23:00'); // 15h delay

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[0]->id]->status())->toBe('late');
    expect($shifted[$intakes[1]->id]->status())->toBe('deferred');
    expect($shifted[$intakes[1]->id]->shiftedTime)->toBeNull();
});

it('clamps a shift at the end of the day when the safe interval still fits', function () {
    $this->travelTo('2026-09-08 08:00:00');

    ['schedule' => $schedule] = makeShiftSchedule(['08:00', '20:00'], 240);

    $intakes = $schedule->intakes;
    recordLateDose($intakes[0]->id, '17:30'); // 9.5h delay

    $shifted = shiftSchedule($schedule);

    expect($shifted[$intakes[0]->id]->status())->toBe('late');
    expect($shifted[$intakes[1]->id]->shiftedTime)->toBe('23:59');
    expect($shifted[$intakes[1]->id]->status())->toBe('scheduled');
});
