<?php

use App\Enums\DosingPattern;
use App\Models\Medication;
use App\Models\Package;
use App\Models\Schedule;
use App\Services\RefillForecast;
use App\Services\RefillForecaster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 12:00:00')); // Wednesday
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function refillMedicationWithIntervalSchedule(int $hours): Medication
{
    return Medication::factory()
        ->has(Schedule::factory()->interval($hours), 'schedules')
        ->create();
}

function refillMedicationWithFixedSchedule(array $times): Medication
{
    $medication = Medication::factory()->create();

    Schedule::factory()->create(['medication_id' => $medication->id])
        ->intakes()
        ->createMany(array_map(
            static fn (string $time, int $sortOrder): array => ['time' => $time, 'sort_order' => $sortOrder],
            $times,
            array_keys($times),
        ));

    return $medication;
}

function refillPackage(Medication $medication, int $remainingDoses): Package
{
    return Package::factory()->create([
        'medication_id' => $medication->id,
        'remaining_doses' => $remainingDoses,
    ]);
}

it('derives consumption from an interval schedule (24h / interval)', function () {
    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage(refillMedicationWithIntervalSchedule(8), 4),
    );

    expect($forecast)->toBeInstanceOf(RefillForecast::class)
        ->dosesPerDay->toBe(3.0)
        ->dosesRemaining->toBe(4)
        ->daysOfSupplyLeft->toBe(1.3)
        ->refillBy->toDateString()->toBe('2026-09-10')
        ->status->toBe('refill_soon')
        ->message->toBe('4 doses left, refill by Thursday');
});

it('derives consumption from a fixed-times schedule (intakes per day)', function () {
    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage(refillMedicationWithFixedSchedule(['08:00', '20:00']), 6),
    );

    expect($forecast)->toBeInstanceOf(RefillForecast::class)
        ->dosesPerDay->toBe(2.0)
        ->daysOfSupplyLeft->toBe(3.0)
        ->refillBy->toDateString()->toBe('2026-09-11')
        ->message->toBe('6 doses left, refill by Friday');
});

it('warns only when the empty date is within the threshold lead days', function () {
    $forecaster = app(RefillForecaster::class);
    // 20 doses at 2/day empty on 2026-09-18 — nine days out.
    $package = refillPackage(refillMedicationWithFixedSchedule(['08:00', '20:00']), 20);

    $relaxed = $forecaster->forecastFor($package, 10);
    $boundary = $forecaster->forecastFor($package, 9);
    $tight = $forecaster->forecastFor($package, 8);

    expect($relaxed->status)->toBe('refill_soon')
        ->and($boundary->status)->toBe('refill_soon')
        ->and($tight->status)->toBe('ok');
});

it('reports a single leftover dose as emptying the same day', function () {
    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage(refillMedicationWithIntervalSchedule(8), 1),
    );

    expect($forecast->refillBy->toDateString())->toBe('2026-09-09')
        ->and($forecast->message)->toBe('1 dose left, refill by Wednesday')
        ->and($forecast->status)->toBe('refill_soon');
});

it('flags an empty package without a refill date message', function () {
    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage(refillMedicationWithIntervalSchedule(8), 0),
    );

    expect($forecast)->toBeInstanceOf(RefillForecast::class)
        ->status->toBe('empty')
        ->dosesRemaining->toBe(0)
        ->daysOfSupplyLeft->toBe(0.0)
        ->refillBy->toDateString()->toBe('2026-09-09')
        ->message->toBe('Out of doses, refill now');
});

it('cannot forecast a package whose medication has no schedule', function () {
    $medication = Medication::factory()->create();

    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage($medication, 10),
    );

    expect($forecast)->toBeNull();
});

it('cannot forecast an interval schedule without an interval length', function () {
    $medication = Medication::factory()
        ->has(Schedule::factory()->state([
            'dosing_pattern' => DosingPattern::Interval,
            'interval_hours' => null,
        ]), 'schedules')
        ->create();

    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage($medication, 10),
    );

    expect($forecast)->toBeNull();
});

it('cannot forecast a fixed-times schedule without intakes', function () {
    $medication = Medication::factory()
        ->has(Schedule::factory(), 'schedules')
        ->create();

    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage($medication, 10),
    );

    expect($forecast)->toBeNull();
});

it('uses the latest schedule when a medication has several', function () {
    $medication = Medication::factory()->create();

    Schedule::factory()->interval(8)->create(['medication_id' => $medication->id]);
    Schedule::factory()->interval(12)->create(['medication_id' => $medication->id]);

    $forecast = app(RefillForecaster::class)->forecastFor(
        refillPackage($medication, 4),
    );

    // The 12-hourly cadence (2/day) is the active one.
    expect($forecast->dosesPerDay)->toBe(2.0);
});
