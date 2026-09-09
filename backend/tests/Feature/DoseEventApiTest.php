<?php

use App\Enums\DosingPattern;
use App\Models\DoseEvent;
use App\Models\Intake;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('lists dose events', function () {
    $intake = Intake::factory()->create();
    DoseEvent::factory()->create(['intake_id' => $intake->id]);

    $this->getJson('/api/v1/dose-events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.intake_id', $intake->id)
        ->assertJsonPath('data.0.outcome', 'on_time');
});

it('stores a dose event taken on time', function () {
    $intake = Intake::factory()->create();

    $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'on_time',
    ])
        ->assertCreated()
        ->assertJsonPath('data.intake_id', $intake->id)
        ->assertJsonPath('data.outcome', 'on_time')
        ->assertJsonPath('data.taken_at', null);

    $this->assertDatabaseHas('dose_events', [
        'intake_id' => $intake->id,
        'outcome' => 'on_time',
        'taken_at' => null,
    ]);
});

it('stores a dose event taken late with the actual time', function () {
    $intake = Intake::factory()->create();
    $actualTime = Carbon::now()->setTime(14, 30, 0);

    $response = $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'late',
        'taken_at' => $actualTime->format('Y-m-d H:i:s'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.intake_id', $intake->id)
        ->assertJsonPath('data.outcome', 'late');

    $this->assertDatabaseHas('dose_events', [
        'intake_id' => $intake->id,
        'outcome' => 'late',
        'taken_at' => $actualTime->format('Y-m-d H:i:s'),
    ]);

    expect(Carbon::parse($response->json('data.taken_at'))->equalTo($actualTime))->toBeTrue();
});

it('stores a dose event that was skipped', function () {
    $intake = Intake::factory()->create();

    $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'skipped',
    ])
        ->assertCreated()
        ->assertJsonPath('data.intake_id', $intake->id)
        ->assertJsonPath('data.outcome', 'skipped')
        ->assertJsonPath('data.taken_at', null);

    $this->assertDatabaseHas('dose_events', [
        'intake_id' => $intake->id,
        'outcome' => 'skipped',
        'taken_at' => null,
    ]);
});

it('requires an existing intake', function () {
    $this->postJson('/api/v1/dose-events', [
        'intake_id' => 9999,
        'outcome' => 'on_time',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('intake_id');
});

it('rejects an unknown outcome', function () {
    $intake = Intake::factory()->create();

    $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'taken_early',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('outcome');
});

it('requires the actual time for a late dose event', function () {
    $intake = Intake::factory()->create();

    $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'late',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taken_at');
});

it('stores a schedule-scoped dose event (interval med, no intake grid)', function () {
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::Interval,
        'interval_hours' => 8,
    ]);

    $response = $this->postJson('/api/v1/dose-events', [
        'schedule_id' => $schedule->id,
        'outcome' => 'late',
        'taken_at' => '2026-09-09 16:00:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.schedule_id', $schedule->id)
        ->assertJsonPath('data.intake_id', null)
        ->assertJsonPath('data.outcome', 'late');

    $this->assertDatabaseHas('dose_events', [
        'id' => $response->json('data.id'),
        'schedule_id' => $schedule->id,
        'intake_id' => null,
        'outcome' => 'late',
    ]);
});

it('requires the actual time for an on-time event without an intake', function () {
    $schedule = Schedule::factory()->create([
        'dosing_pattern' => DosingPattern::Interval,
        'interval_hours' => 8,
    ]);

    $this->postJson('/api/v1/dose-events', [
        'schedule_id' => $schedule->id,
        'outcome' => 'on_time',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taken_at');
});

it('requires an intake or a schedule for a dose event', function () {
    $this->postJson('/api/v1/dose-events', [
        'outcome' => 'on_time',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['intake_id', 'schedule_id']);
});

it('requires a valid actual time for a late dose event', function () {
    $intake = Intake::factory()->create();

    $this->postJson('/api/v1/dose-events', [
        'intake_id' => $intake->id,
        'outcome' => 'late',
        'taken_at' => 'not-a-timestamp',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taken_at');
});
