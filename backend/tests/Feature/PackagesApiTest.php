<?php

use App\Models\Medication;
use App\Models\Package;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a package tracking remaining doses', function () {
    $medication = Medication::factory()->create();

    $response = $this->postJson('/api/v1/packages', [
        'medication_id' => $medication->id,
        'remaining_doses' => 30,
    ])
        ->assertCreated()
        ->assertJsonPath('data.medication_id', $medication->id)
        ->assertJsonPath('data.remaining_doses', 30);

    $this->assertDatabaseHas('packages', [
        'id' => $response->json('data.id'),
        'medication_id' => $medication->id,
        'remaining_doses' => 30,
    ]);
});

it('lists packages with their remaining doses', function () {
    $medication = Medication::factory()->create();
    $package = Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => 12,
    ]);

    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $package->id)
        ->assertJsonPath('data.0.medication_id', $medication->id)
        ->assertJsonPath('data.0.remaining_doses', 12);
});

it('filters packages by medication', function () {
    $first = Medication::factory()->create();
    $second = Medication::factory()->create();

    Package::factory()->create(['medication_id' => $first->id]);
    Package::factory()->create(['medication_id' => $second->id]);

    $this->getJson('/api/v1/packages?medication_id='.$first->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.medication_id', $first->id);
});

it('exposes a refill forecast computed from the schedule consumption', function () {
    $medication = Medication::factory()->create();
    Schedule::factory()->interval(8)->create([
        'medication_id' => $medication->id,
    ]);

    Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => 4,
    ]);

    // 8-hourly dosing consumes 3 doses/day; 4 doses left on Wednesday
    // empty Thursday — so the forecast must warn and say so.
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-09-09 12:00:00'),
    );

    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonPath('data.0.forecast.status', 'refill_soon')
        ->assertJsonPath('data.0.forecast.doses_remaining', 4)
        ->assertJsonPath('data.0.forecast.doses_per_day', 3)
        ->assertJsonPath('data.0.forecast.days_of_supply_left', 1.3)
        ->assertJsonPath('data.0.forecast.refill_by', '2026-09-10')
        ->assertJsonPath('data.0.forecast.refill_by_day', 'Thursday')
        ->assertJsonPath('data.0.forecast.message', '4 doses left, refill by Thursday');

    CarbonImmutable::setTestNow();
});

it('records consumption toward the forecast via an update', function () {
    $medication = Medication::factory()->create();
    $schedule = Schedule::factory()->create(['medication_id' => $medication->id]);
    $schedule->intakes()->createMany([
        ['time' => '08:00', 'sort_order' => 0],
        ['time' => '20:00', 'sort_order' => 1],
    ]);

    $package = Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => 20,
    ]);

    // Two doses/day: 20 doses last 10 days (through Friday, 2026-09-18).
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-09-09 12:00:00'),
    );

    $this->patchJson('/api/v1/packages/'.$package->id, [
        'remaining_doses' => 4,
    ])
        ->assertOk()
        ->assertJsonPath('data.remaining_doses', 4)
        ->assertJsonPath('data.forecast.status', 'refill_soon')
        ->assertJsonPath('data.forecast.doses_per_day', 2)
        ->assertJsonPath('data.forecast.refill_by', '2026-09-10')
        ->assertJsonPath('data.forecast.refill_by_day', 'Thursday')
        ->assertJsonPath('data.forecast.message', '4 doses left, refill by Thursday');

    $this->assertDatabaseHas('packages', [
        'id' => $package->id,
        'remaining_doses' => 4,
    ]);

    CarbonImmutable::setTestNow();
});

it('keeps an ample package out of the refill warning', function () {
    $medication = Medication::factory()->create();
    $schedule = Schedule::factory()->create(['medication_id' => $medication->id]);
    $schedule->intakes()->createMany([
        ['time' => '08:00', 'sort_order' => 0],
        ['time' => '20:00', 'sort_order' => 1],
    ]);

    Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => 20,
    ]);

    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-09-09 12:00:00'),
    );

    // 10 days of supply is beyond the 3-day default warning lead.
    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonPath('data.0.forecast.status', 'ok')
        ->assertJsonPath('data.0.forecast.refill_by', '2026-09-18');

    CarbonImmutable::setTestNow();
});

it('flags an empty package for an immediate refill', function () {
    $medication = Medication::factory()->create();
    Schedule::factory()->interval(8)->create([
        'medication_id' => $medication->id,
    ]);

    Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => 0,
    ]);

    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonPath('data.0.forecast.status', 'empty')
        ->assertJsonPath('data.0.forecast.doses_remaining', 0)
        ->assertJsonPath('data.0.forecast.message', 'Out of doses, refill now');
});

it('returns a null forecast when the medication has no schedule', function () {
    Package::factory()->create([
        'medication_id' => Medication::factory()->create()->id,
        'remaining_doses' => 10,
    ]);

    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonPath('data.0.forecast', null);
});

it('requires an existing medication for a package', function () {
    $this->postJson('/api/v1/packages', [
        'medication_id' => 9999,
        'remaining_doses' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('medication_id');
});

it('requires remaining doses when creating a package', function () {
    $this->postJson('/api/v1/packages', [
        'medication_id' => Medication::factory()->create()->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('remaining_doses');
});

it('rejects a negative remaining dose count', function () {
    $this->postJson('/api/v1/packages', [
        'medication_id' => Medication::factory()->create()->id,
        'remaining_doses' => -1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('remaining_doses');
});

it('requires remaining doses when updating a package', function () {
    $package = Package::factory()->create();

    $this->patchJson('/api/v1/packages/'.$package->id, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('remaining_doses');
});
