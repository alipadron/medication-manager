<?php

use App\Enums\DosingPattern;
use App\Models\DoseEvent;
use App\Models\Schedule;
use App\Services\SchedulePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeIntervalSchedule(int $hours = 8, ?string $nominalStartAt = null): Schedule
{
    $attributes = [
        'dosing_pattern' => DosingPattern::Interval,
        'interval_hours' => $hours,
    ];

    if ($nominalStartAt !== null) {
        $attributes['nominal_start_at'] = $nominalStartAt;
    }

    return Schedule::factory()->create($attributes);
}

function makeFixedSchedule(array $times, int $safeIntervalMinutes = 240): Schedule
{
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::FixedTimes,
        'safe_interval_minutes' => $safeIntervalMinutes,
    ]);

    $schedule->intakes()->createMany(array_map(
        static fn (string $time, int $sortOrder): array => ['time' => $time, 'sort_order' => $sortOrder],
        $times,
        array_keys($times),
    ));

    return $schedule;
}

function recordIntervalDose(Schedule $schedule, string $outcome, string $takenAt): DoseEvent
{
    return DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => null,
        'outcome' => $outcome,
        'taken_at' => $takenAt,
    ]);
}

function recordIntervalSkip(Schedule $schedule): DoseEvent
{
    return DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => null,
        'outcome' => 'skipped',
    ]);
}

it('computes the interval next due time from the last actual dose', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 08:00:00');

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->lastActualDoseAt?->toDateTimeString())->toBe('2026-09-09 08:00:00')
        ->and($plan->dueAt?->toDateTimeString())->toBe('2026-09-09 16:00:00');
});

it('anchors the cadence on the latest of repeated doses (cascade-proof)', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 08:00:00');
    recordIntervalDose($schedule, 'on_time', '2026-09-09 16:00:00');
    recordIntervalDose($schedule, 'on_time', '2026-09-10 00:00:00');

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->lastActualDoseAt?->toDateTimeString())->toBe('2026-09-10 00:00:00')
        ->and($plan->dueAt?->toDateTimeString())->toBe('2026-09-10 08:00:00');
});

it('re-anchors the interval cadence to the actual time when a dose is taken late', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 08:00:00');
    recordIntervalDose($schedule, 'late', '2026-09-09 17:45:00');

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->lastActualDoseAt?->toDateTimeString())->toBe('2026-09-09 17:45:00')
        ->and($plan->dueAt?->toDateTimeString())->toBe('2026-09-10 01:45:00');
});

it('deletes the cadence slot of a skipped dose without compressing or catching up', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 08:00:00');

    $this->travelTo('2026-09-09 16:30:00');
    recordIntervalSkip($schedule);

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    // The 16:00 slot is deleted: the next due is the *following* slot (00:00),
    // not 16:00 again (no compression) and not anchored to the skip (no catch-up).
    expect($plan->dueAt?->toDateTimeString())->toBe('2026-09-10 00:00:00');
});

it('deletes each skipped cadence slot as the cadence rolls across nights', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-07 08:00:00');

    $this->travelTo('2026-09-07 16:30:00');
    recordIntervalSkip($schedule);

    $this->travelTo('2026-09-08 00:30:00');
    recordIntervalSkip($schedule);

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->dueAt?->toDateTimeString())->toBe('2026-09-08 08:00:00');
});

it('rolls the cadence across midnight with no clamp', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 23:00:00');

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    // The due lands at 07:00 the next morning — the cadence crosses the
    // night boundary untouched (no daily reset, no midnight clamp).
    expect($plan->lastActualDoseAt?->toDateTimeString())->toBe('2026-09-09 23:00:00')
        ->and($plan->dueAt?->toDateTimeString())->toBe('2026-09-10 07:00:00');
});

it('never leaves the due time in the past after a skip recorded several slots late', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-08 08:00:00');

    // The 16:00 and 00:00 slots pass untouched; the user skips at 09:00 the
    // next day. The slot they mean is the 08:00 one just missed, so the
    // next due is 16:00 — never a moment before the skip was recorded.
    $this->travelTo('2026-09-10 09:00:00');
    recordIntervalSkip($schedule);

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->dueAt?->toDateTimeString())->toBe('2026-09-10 16:00:00');
});

it('ignores a skip recorded before its cadence slot is due', function () {
    $schedule = makeIntervalSchedule(8);
    recordIntervalDose($schedule, 'on_time', '2026-09-09 08:00:00');

    $this->travelTo('2026-09-09 15:00:00');
    recordIntervalSkip($schedule);

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->dueAt?->toDateTimeString())->toBe('2026-09-09 16:00:00');
});

it('derives the next due from the nominal start when no dose has been taken yet', function () {
    $schedule = makeIntervalSchedule(8, '2026-09-09 08:00:00');

    $this->travelTo('2026-09-09 10:00:00');

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->lastActualDoseAt)->toBeNull()
        ->and($plan->dueAt?->toDateTimeString())->toBe('2026-09-09 16:00:00');
});

it('has no due time when the cadence has no anchor', function () {
    $schedule = makeIntervalSchedule(8);

    $plan = app(SchedulePlanner::class)->intervalPlan($schedule);

    expect($plan->lastActualDoseAt)->toBeNull()
        ->and($plan->dueAt)->toBeNull();
});

it('shifts the remaining fixed-times intakes of the day by the delay of a late dose', function () {
    $schedule = makeFixedSchedule(['08:00', '20:00']);

    recordIntakeDose($schedule, 0, 'late', '2026-09-09 10:00:00');

    $plan = app(SchedulePlanner::class)->fixedDayPlan($schedule, '2026-09-09');

    expect($plan->intakes)->toHaveCount(2);

    $first = $plan->intakes[0];
    $second = $plan->intakes[1];

    expect($first->status)->toBe('late')
        ->and($first->plannedAt?->format('H:i'))->toBe('10:00')
        ->and($second->status)->toBe('scheduled')
        ->and($second->plannedAt?->format('H:i'))->toBe('22:00');
});

it('never pushes a shifted intake closer than the safe minimum interval', function () {
    $schedule = makeFixedSchedule(['08:00', '10:00', '15:00'], 240);

    recordIntakeDose($schedule, 0, 'late', '2026-09-09 11:00:00');

    $plan = app(SchedulePlanner::class)->fixedDayPlan($schedule, '2026-09-09');

    // 10:00 + 180 min = 13:00 would be only 2h after the 11:00 actual dose,
    // so it is pushed out to 11:00 + 4h = 15:00; the 15:00 intake then lands
    // at 19:00 to keep 4h spacing after the pushed one.
    expect(array_map(
        fn ($intake): ?string => $intake->plannedAt?->format('H:i'),
        $plan->intakes,
    ))->toBe(['11:00', '15:00', '19:00']);

    expect(array_map(fn ($intake): string => $intake->status, $plan->intakes))
        ->toBe(['late', 'scheduled', 'scheduled']);
});

it('flags a skipped intake and leaves the rest of the shifted day fixed', function () {
    $schedule = makeFixedSchedule(['08:00', '12:00', '20:00']);

    recordIntakeDose($schedule, 0, 'late', '2026-09-09 10:00:00');

    $this->travelTo('2026-09-09 12:05:00');
    recordIntakeDose($schedule, 1, 'skipped', null);

    $plan = app(SchedulePlanner::class)->fixedDayPlan($schedule, '2026-09-09');

    expect($plan->intakes[1]->status)->toBe('skipped')
        ->and($plan->intakes[1]->plannedAt?->format('H:i'))->toBe('14:00')
        ->and($plan->intakes[2]->status)->toBe('scheduled')
        ->and($plan->intakes[2]->plannedAt?->format('H:i'))->toBe('22:00');
});

it('flags a skipped intake without shifting the rest of an unshifted day', function () {
    $schedule = makeFixedSchedule(['08:00', '20:00']);

    $this->travelTo('2026-09-09 08:05:00');
    recordIntakeDose($schedule, 0, 'skipped', null);

    $plan = app(SchedulePlanner::class)->fixedDayPlan($schedule, '2026-09-09');

    expect($plan->intakes[0]->status)->toBe('skipped')
        ->and($plan->intakes[1]->status)->toBe('scheduled')
        ->and($plan->intakes[1]->plannedAt?->format('H:i'))->toBe('20:00');
});

it('resets the fixed-times plan to base times the next day (nightly reset)', function () {
    $schedule = makeFixedSchedule(['08:00', '20:00']);

    recordIntakeDose($schedule, 0, 'late', '2026-09-09 10:00:00');

    $planner = app(SchedulePlanner::class);

    $shiftedDay = $planner->fixedDayPlan($schedule, '2026-09-09');
    expect($shiftedDay->intakes[1]->plannedAt?->format('H:i'))->toBe('22:00');

    $nextDay = $planner->fixedDayPlan($schedule, '2026-09-10');
    expect($nextDay->intakes[0]->status)->toBe('scheduled')
        ->and($nextDay->intakes[0]->plannedAt?->format('H:i'))->toBe('08:00')
        ->and($nextDay->intakes[1]->plannedAt?->format('H:i'))->toBe('20:00');
});

it('defers an intake whose shift would cross midnight', function () {
    $schedule = makeFixedSchedule(['08:00', '22:00'], 240);

    recordIntakeDose($schedule, 0, 'late', '2026-09-09 11:00:00');

    $plan = app(SchedulePlanner::class)->fixedDayPlan($schedule, '2026-09-09');

    expect($plan->intakes[1]->status)->toBe('deferred')
        ->and($plan->intakes[1]->plannedAt)->toBeNull();
});

function recordIntakeDose(Schedule $schedule, int $sortOrder, string $outcome, ?string $takenAt): DoseEvent
{
    $intake = $schedule->intakes()->where('sort_order', $sortOrder)->firstOrFail();

    return DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => $intake->id,
        'outcome' => $outcome,
        'taken_at' => $takenAt,
    ]);
}
