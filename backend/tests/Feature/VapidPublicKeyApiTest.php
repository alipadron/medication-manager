<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the configured VAPID public key', function () {
    config(['vapid.public_key' => 'BLVTAfbcf57O1Y4vuT4xAKAQbBVHN-jGvV2dyUwUH_i6Cd8HbJPJ1RtiJ03sKUNeLFg4idOE0HSyOCtRRSaHOrY']);

    $this->getJson('/api/v1/push/vapid-public-key')
        ->assertOk()
        ->assertJsonPath('data.public_key', 'BLVTAfbcf57O1Y4vuT4xAKAQbBVHN-jGvV2dyUwUH_i6Cd8HbJPJ1RtiJ03sKUNeLFg4idOE0HSyOCtRRSaHOrY');
});

it('reports a 503 when the VAPID public key is not configured', function () {
    config(['vapid.public_key' => '']);

    $this->getJson('/api/v1/push/vapid-public-key')
        ->assertStatus(503)
        ->assertJsonPath('error', 'VAPID public key is not configured. Run `php artisan push:vapid-keys`.');
});
