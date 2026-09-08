<?php

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
        ->assertJsonCount(2, 'data.0.intakes')
        ->assertJsonPath('data.0.intakes.0.time', '08:00');
});

it('creates a schedule with intakes', function () {
    $medication = Medication::factory()->create();

    $response = $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'intakes' => [
            ['time' => '08:00'],
            ['time' => '20:00'],
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.intakes')
        ->assertJsonPath('data.medication_id', $medication->id);

    $scheduleId = $response->json('data.id');
    $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '08:00']);
    $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '20:00']);
});

it('requires an existing medication', function () {
    $this->postJson('/api/v1/schedules', [
        'medication_id' => 9999,
        'intakes' => [['time' => '08:00']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('medication_id');
});

it('requires at least one intake', function () {
    $medication = Medication::factory()->create();

    $this->postJson('/api/v1/schedules', [
        'medication_id' => $medication->id,
        'intakes' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('intakes');
});
