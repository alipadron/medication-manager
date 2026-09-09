<?php

use App\Enums\DosingPattern;
use App\Models\Medication;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists schedules with intakes', function () {
    $medication = Medication::factory()->create();

    Schedule::factory()->create(['medication_id' => $medication->id])
        ->intakes()->createMany([
            ['time' => '08:00', 'sort_order' => 0],
            ['time' => '20:00', 'sort_order' => 1],
        ]);

    $this->getJson('/api/v1/schedules')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.medication_id', $medication->id)
        ->assertJsonPath('data.0.dosing_pattern', 'fixed_times')
        ->assertJsonCount(2, 'data.0.intakes')
        ->assertJsonPath('data.0.intakes.0.time', '08:00');
});

it('creates a fixed-times schedule with intakes', function () {
    $medication = Medication::factory()->create();

    $response = $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'fixed_times',
        'intakes' => [
            ['time' => '08:00'],
            ['time' => '20:00'],
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.intakes')
        ->assertJsonPath('data.medication_id', $medication->id)
        ->assertJsonPath('data.dosing_pattern', 'fixed_times')
        ->assertJsonPath('data.interval_hours', null)
        ->assertJsonPath('data.safe_interval_minutes', 240);

    $scheduleId = $response->json('data.id');
    $this->assertDatabaseHas('schedules', [
        'id' => $scheduleId,
        'dosing_pattern' => 'fixed_times',
        'interval_hours' => null,
    ]);
    $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '08:00']);
    $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '20:00']);
});

it('creates an interval schedule with interval hours and a nominal start', function () {
    $medication = Medication::factory()->create();

    $response = $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'interval',
        'interval_hours' => 8,
        'nominal_start_at' => '2026-09-09 08:00:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.dosing_pattern', 'interval')
        ->assertJsonPath('data.interval_hours', 8)
        ->assertJsonCount(0, 'data.intakes');

    $scheduleId = $response->json('data.id');
    $this->assertDatabaseHas('schedules', [
        'id' => $scheduleId,
        'dosing_pattern' => 'interval',
        'interval_hours' => 8,
    ]);
});

it('requires interval hours for an interval schedule', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'interval',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('interval_hours');
});

it('rejects intakes and a safe interval on an interval schedule', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'interval',
        'interval_hours' => 8,
        'safe_interval_minutes' => 240,
        'intakes' => [
            ['time' => '08:00'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['intakes', 'safe_interval_minutes']);
});

it('rejects interval hours on a fixed-times schedule', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'fixed_times',
        'interval_hours' => 8,
        'intakes' => [
            ['time' => '08:00'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('interval_hours');
});

it('requires an existing medication', function () {
    $this->postJson('/api/v1/schedules', [
        'medication_id' => 9999,
        'dosing_pattern' => 'fixed_times',
        'intakes' => [['time' => '08:00']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('medication_id');
});

it('requires at least one intake for a fixed-times schedule', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'fixed_times',
        'intakes' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('intakes');
});

it('requires a valid dosing pattern', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'dosing_pattern' => 'daily-grid',
        'intakes' => [['time' => '08:00']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dosing_pattern');
});

it('defaults factories to the fixed-times pattern', function () {
    $schedule = Schedule::factory()->create();

    expect($schedule->dosing_pattern)->toBe(DosingPattern::FixedTimes)
        ->and($schedule->safe_interval_minutes)->toBe(240);
});
