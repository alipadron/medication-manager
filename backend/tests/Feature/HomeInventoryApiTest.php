<?php

use App\Models\HomeInventoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a home inventory entry with its expiry date', function () {
    $response = $this->postJson('/api/v1/home-inventory', [
        'name' => 'Amoxicillin 500mg',
        'expiry_date' => '2026-12-31',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Amoxicillin 500mg')
        ->assertJsonPath('data.expiry_date', '2026-12-31');

    $this->assertDatabaseHas('home_inventory_entries', [
        'id' => $response->json('data.id'),
        'name' => 'Amoxicillin 500mg',
        'expiry_date' => '2026-12-31',
    ]);
});

it('lists home inventory entries carrying their expiry dates', function () {
    $entry = HomeInventoryEntry::factory()->create([
        'name' => 'Ibuprofen 400mg',
        'expiry_date' => '2026-10-15',
    ]);

    $this->getJson('/api/v1/home-inventory')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entry->id)
        ->assertJsonPath('data.0.name', 'Ibuprofen 400mg')
        ->assertJsonPath('data.0.expiry_date', '2026-10-15');
});

it('requires a name for a home inventory entry', function () {
    $this->postJson('/api/v1/home-inventory', [
        'expiry_date' => '2026-12-31',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('requires an expiry date for a home inventory entry', function () {
    $this->postJson('/api/v1/home-inventory', [
        'name' => 'Amoxicillin 500mg',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expiry_date');
});

it('rejects an invalid expiry date', function () {
    $this->postJson('/api/v1/home-inventory', [
        'name' => 'Amoxicillin 500mg',
        'expiry_date' => 'not-a-date',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expiry_date');
});
