<?php

use App\Enums\DosingPattern;
use App\Models\DoseEvent;
use App\Models\Medication;
use App\Models\Person;
use App\Models\PushSubscription;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery\MockInterface;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

/**
 * Bind a mocked push manager into the channel's resolution path and return
 * it. The manager is the only live-push seam: queueNotification + flush are
 * asserted here, nothing touches a real push vendor.
 *
 * @return MockInterface&WebPush
 */
if (! function_exists('mockPushManager')) {
    function mockPushManager(): MockInterface
    {
        $mock = Mockery::mock(WebPush::class);
        app()->when(WebPushChannel::class)->needs(WebPush::class)->give(fn () => $mock);

        return $mock;
    }
}

/** An interval schedule for a known person. */
if (! function_exists('intervalScheduleFor')) {
    function intervalScheduleFor(Person $person, int $hours = 8): Schedule
    {
        $medication = Medication::factory()->create(['person_id' => $person->id]);

        return Schedule::factory()->interval($hours)->create(['medication_id' => $medication->id]);
    }
}

/** A fixed-times schedule (given clock times) for a known person. */
if (! function_exists('fixedScheduleFor')) {
    function fixedScheduleFor(Person $person, array $times): Schedule
    {
        $medication = Medication::factory()->create(['person_id' => $person->id]);
        $schedule = Schedule::factory()->create([
            'medication_id' => $medication->id,
            'dosing_pattern' => DosingPattern::FixedTimes,
        ]);

        $schedule->intakes()->createMany(array_map(
            static fn (string $time, int $sortOrder): array => ['time' => $time, 'sort_order' => $sortOrder],
            $times,
            array_keys($times),
        ));

        return $schedule;
    }
}

/** Record an interval dose event against a schedule. */
if (! function_exists('recordDose')) {
    function recordDose(Schedule $schedule, string $outcome, string $takenAt): DoseEvent
    {
        return DoseEvent::factory()->create([
            'schedule_id' => $schedule->id,
            'intake_id' => null,
            'outcome' => $outcome,
            'taken_at' => $takenAt,
        ]);
    }
}

/** Enroll N devices for a person, returning their endpoints. */
if (! function_exists('enrollDevices')) {
    function enrollDevices(Person $person, int $count): array
    {
        return PushSubscription::factory()->count($count)->create(['person_id' => $person->id])
            ->pluck('endpoint')->all();
    }
}

/** An empty flush report generator, as the channel expects from flush(). */
if (! function_exists('emptyPushReports')) {
    function emptyPushReports(): Generator
    {
        yield from [];
    }
}

it('notifies every enrolled device when an interval dose is due', function () {
    $person = Person::factory()->create();
    $schedule = intervalScheduleFor($person, 8);
    recordDose($schedule, 'on_time', '2026-09-11 06:00:00');
    enrollDevices($person, 2);

    $this->travelTo('2026-09-11 14:00:00');

    $push = mockPushManager();
    $push->shouldReceive('queueNotification')
        ->with(Mockery::type(Subscription::class), Mockery::on(fn (string $payload): bool => str_contains($payload, $schedule->medication->name)), [])
        ->twice();
    $push->shouldReceive('flush')->once()->andReturn(emptyPushReports());

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('1 due slots notified (0 already notified)')
        ->assertExitCode(0);

    $expectedSlotKey = 'interval:'.$schedule->id.':'.Carbon::parse('2026-09-11 14:00:00')->toIso8601String();

    $this->assertDatabaseHas('push_notifications', [
        'person_id' => $person->id,
        'schedule_id' => $schedule->id,
        'intake_id' => null,
        'slot_key' => $expectedSlotKey,
    ]);
});

it('does not notify when the interval dose is not due yet', function () {
    $person = Person::factory()->create();
    $schedule = intervalScheduleFor($person, 8);
    recordDose($schedule, 'on_time', '2026-09-11 06:00:00');
    enrollDevices($person, 1);

    $this->travelTo('2026-09-11 10:00:00'); // due at 14:00

    $push = mockPushManager();
    $push->shouldNotReceive('queueNotification');
    $push->shouldNotReceive('flush');

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('0 due slots notified')
        ->assertExitCode(0);

    $this->assertDatabaseCount('push_notifications', 0);
});

it('notifies an interval due slot once across repeated ticks', function () {
    $person = Person::factory()->create();
    $schedule = intervalScheduleFor($person, 8);
    recordDose($schedule, 'on_time', '2026-09-11 06:00:00');
    enrollDevices($person, 1);

    $this->travelTo('2026-09-11 14:00:00');

    $first = mockPushManager();
    $first->shouldReceive('queueNotification')->once();
    $first->shouldReceive('flush')->once()->andReturn(emptyPushReports());

    $this->artisan('notifications:send-due-doses')->assertExitCode(0);

    // The slot is still overdue untouched, but the per-slot guard holds.
    $second = mockPushManager();
    $second->shouldNotReceive('queueNotification');
    $second->shouldNotReceive('flush');

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('0 due slots notified (1 already notified)')
        ->assertExitCode(0);

    $this->assertDatabaseCount('push_notifications', 1);
});

it('never offers a skipped interval cadence slot', function () {
    $person = Person::factory()->create();
    $schedule = intervalScheduleFor($person, 8);
    recordDose($schedule, 'on_time', '2026-09-11 06:00:00');
    enrollDevices($person, 1);

    $this->travelTo('2026-09-11 14:30:00'); // the 14:00 slot is now being skipped
    recordDose($schedule, 'skipped', '2026-09-11 14:30:00');

    $push = mockPushManager();
    $push->shouldNotReceive('queueNotification');

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('0 due slots notified')
        ->assertExitCode(0);

    $this->assertDatabaseCount('push_notifications', 0);
});

it('notifies every fixed-times intake whose planned time has arrived', function () {
    $person = Person::factory()->create();
    $schedule = fixedScheduleFor($person, ['08:00', '20:00']);
    enrollDevices($person, 2);

    $this->travelTo('2026-09-11 08:30:00');

    $push = mockPushManager();
    $push->shouldReceive('queueNotification')->twice();
    $push->shouldReceive('flush')->once()->andReturn(emptyPushReports());

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('1 due slots notified')
        ->assertExitCode(0);

    $morningIntake = $schedule->intakes()->first();

    $this->assertDatabaseHas('push_notifications', [
        'person_id' => $person->id,
        'schedule_id' => $schedule->id,
        'intake_id' => $morningIntake->id,
        'slot_key' => 'fixed:'.$morningIntake->id.':2026-09-11',
    ]);
});

it('does not notify a fixed-times intake that was already taken today', function () {
    $person = Person::factory()->create();
    $schedule = fixedScheduleFor($person, ['08:00', '20:00']);
    enrollDevices($person, 1);

    $this->travelTo('2026-09-11 08:00:00');

    $morningIntake = $schedule->intakes()->first();
    DoseEvent::factory()->create([
        'schedule_id' => $schedule->id,
        'intake_id' => $morningIntake->id,
        'outcome' => 'on_time',
        'taken_at' => '2026-09-11 08:00:00',
    ]);

    $push = mockPushManager();
    $push->shouldNotReceive('queueNotification');

    $this->artisan('notifications:send-due-doses')
        ->expectsOutputToContain('0 due slots notified')
        ->assertExitCode(0);

    $this->assertDatabaseCount('push_notifications', 0);
});

it('does not mark a slot when the person has no enrolled devices', function () {
    $person = Person::factory()->create();
    $schedule = intervalScheduleFor($person, 8);
    recordDose($schedule, 'on_time', '2026-09-11 06:00:00');

    $this->travelTo('2026-09-11 14:00:00');

    $push = mockPushManager();
    $push->shouldNotReceive('queueNotification');

    $this->artisan('notifications:send-due-doses')->assertExitCode(0);

    $this->assertDatabaseCount('push_notifications', 0);
});
