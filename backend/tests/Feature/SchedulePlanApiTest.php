<?php

use App\Enums\DosingPattern;
use App\Models\DoseEvent;
use App\Models\Medication;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('serves the interval plan as an authoritative due-time read', function () {
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::Interval,
        'interval_hours' => 8,
    ]);

    DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => null,
        'outcome' => 'late',
        'taken_at' => '2026-09-09 08:00:00',
    ]);

    $this->travelTo('2026-09-09 10:00:00');

    $response = $this->getJson("/api/v1/schedules/{$schedule->id}/plan")
        ->assertOk()
        ->assertJsonPath('data.dosing_pattern', 'interval')
        ->assertJsonPath('data.interval_hours', 8)
        ->assertJsonPath('data.due_status', 'upcoming');

    $dueAt = Carbon::parse($response->json('data.due_at'));
    $lastActual = Carbon::parse($response->json('data.last_actual_dose_at'));

    expect($dueAt->equalTo(Carbon::parse('2026-09-09 16:00:00')))->toBeTrue()
        ->and($lastActual->equalTo(Carbon::parse('2026-09-09 08:00:00')))->toBeTrue();
});

it('serves the fixed-times plan as an authoritative shifted-schedule read', function () {
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::FixedTimes,
        'safe_interval_minutes' => 240,
    ]);

    $schedule->intakes()->createMany([
        ['time' => '08:00', 'sort_order' => 0],
        ['time' => '20:00', 'sort_order' => 1],
    ]);

    $intake = $schedule->intakes()->where('sort_order', 0)->firstOrFail();

    DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => $intake->id,
        'outcome' => 'late',
        'taken_at' => '2026-09-09 10:00:00',
    ]);

    $this->travelTo('2026-09-09 09:00:00');

    $this->getJson("/api/v1/schedules/{$schedule->id}/plan")
        ->assertOk()
        ->assertJsonPath('data.dosing_pattern', 'fixed_times')
        ->assertJsonPath('data.safe_interval_minutes', 240)
        ->assertJsonPath('data.date', '2026-09-09')
        ->assertJsonPath('data.intakes.0.status', 'late')
        ->assertJsonPath('data.intakes.0.planned_time', '10:00')
        ->assertJsonPath('data.intakes.1.status', 'scheduled')
        ->assertJsonPath('data.intakes.1.planned_time', '22:00');
});

it('accepts an explicit date for the fixed-times plan', function () {
    $medication = Medication::factory()->create();
    $schedule = Schedule::factory()->create([
        'medication_id' => $medication->id,
        'dosing_pattern' => DosingPattern::FixedTimes,
    ]);

    $schedule->intakes()->createMany([
        ['time' => '08:00', 'sort_order' => 0],
        ['time' => '20:00', 'sort_order' => 1],
    ]);

    $this->getJson("/api/v1/schedules/{$schedule->id}/plan?date=2026-09-15")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-09-15')
        ->assertJsonPath('data.intakes.0.planned_time', '08:00');
});

it('rejects a malformed plan date', function () {
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::FixedTimes,
    ]);

    $this->getJson("/api/v1/schedules/{$schedule->id}/plan?date=not-a-date")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('returns 404 for an unknown schedule plan', function () {
    $this->getJson('/api/v1/schedules/99999/plan')
        ->assertNotFound();
});
