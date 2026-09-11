<?php

use App\Models\Person;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery\MockInterface;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

/**
 * Bind a mocked push manager into the channel's resolution path and return
 * it. Shared with SendDueDoseNotificationsTest — duplicated under the same
 * function_exists guard so either file can load first.
 *
 * @return MockInterface&WebPush
 */
if (! function_exists('mockPushManager')) {
    function mockPushManager(): MockInterface
    {
        $mock = Mockery::mock(WebPush::class);
        app()->when(WebPushChannel::class)
            ->needs(WebPush::class)
            ->give(fn () => $mock);

        return $mock;
    }
}

if (! function_exists('emptyPushReports')) {
    function emptyPushReports(): Generator
    {
        yield from [];
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

it('nudges only the target person devices, not the caller devices', function () {
    $target = Person::factory()->create();
    $caller = Person::factory()->create();
    $targetEndpoints = enrollDevices($target, 2);
    $callerEndpoints = enrollDevices($caller, 1);

    $push = mockPushManager();
    $push->shouldReceive('queueNotification')
        ->withArgs(fn (Subscription $subscription, ?string $payload): bool => in_array($subscription->getEndpoint(), $targetEndpoints, true)
            && ! in_array($subscription->getEndpoint(), $callerEndpoints, true)
            && str_contains($payload ?? '', 'check your meds, '.$target->name))
        ->twice();
    $push->shouldReceive('flush')->once()->andReturn(emptyPushReports());

    $this->postJson('/api/v1/push/nudge', ['person_id' => $target->id])
        ->assertStatus(202)
        ->assertJsonPath('data.person_id', $target->id)
        ->assertJsonPath('data.notified', 2);
});

it('rejects a nudge for an unknown person', function () {
    $this->postJson('/api/v1/push/nudge', ['person_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('person_id');
});

it('reports zero notified devices and sends nothing when the target has none enrolled', function () {
    $target = Person::factory()->create();

    $push = mockPushManager();
    $push->shouldNotReceive('queueNotification');
    $push->shouldNotReceive('flush');

    $this->postJson('/api/v1/push/nudge', ['person_id' => $target->id])
        ->assertStatus(202)
        ->assertJsonPath('data.notified', 0);
});
