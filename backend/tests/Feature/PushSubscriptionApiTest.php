<?php

use App\Models\Person;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A well-formed web-push body, url-safe base64 keys of the correct size. */
function validPushBody(array $overrides = []): array
{
    return array_merge([
        'person_id' => Person::factory()->create()->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abcdef1234567890',
        'p256dh' => rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '='),
        'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
    ], $overrides);
}

it('stores a push subscription for an existing person', function () {
    $body = validPushBody();

    $this->postJson('/api/v1/push-subscriptions', $body)
        ->assertCreated()
        ->assertJsonPath('data.person_id', $body['person_id'])
        ->assertJsonPath('data.endpoint', $body['endpoint']);

    $this->assertDatabaseHas('push_subscriptions', [
        'id' => PushSubscription::latest('id')->first()->id,
        'person_id' => $body['person_id'],
        'endpoint' => $body['endpoint'],
        'p256dh' => $body['p256dh'],
        'auth' => $body['auth'],
    ]);
});

it('does not echo the VAPID encryption keys back in the response', function () {
    $response = $this->postJson('/api/v1/push-subscriptions', validPushBody());

    $response->assertCreated();
    $json = $response->json('data');

    expect($json)->not->toHaveKeys(['p256dh', 'auth']);
});

it('requires an existing person', function () {
    $this->postJson('/api/v1/push-subscriptions', validPushBody(['person_id' => 999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('person_id');
});

it('rejects a non-URL endpoint', function () {
    $this->postJson('/api/v1/push-subscriptions', validPushBody(['endpoint' => 'not-a-url']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});

it('rejects a duplicate endpoint', function () {
    $existing = PushSubscription::factory()->create();

    $this->postJson('/api/v1/push-subscriptions',
        validPushBody(['endpoint' => $existing->endpoint]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});

it('rejects a p256dh key that does not decode to 65 bytes', function () {
    $this->postJson('/api/v1/push-subscriptions',
        validPushBody(['p256dh' => 'aGVsbG8td29ybGQtaW5kZWVk']) // decodes to the wrong length
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('p256dh');
});

it('rejects an auth secret that does not decode to 16 bytes', function () {
    $this->postJson('/api/v1/push-subscriptions',
        validPushBody(['auth' => 'aGVsbG8td29ybGQtdG9vLXNob3J0'])
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('auth');
});

it('deletes a push subscription', function () {
    $subscription = PushSubscription::factory()->create();

    $this->deleteJson("/api/v1/push-subscriptions/{$subscription->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
});

it('returns 404 when deleting an unknown push subscription', function () {
    $this->deleteJson('/api/v1/push-subscriptions/999')
        ->assertNotFound();
});
