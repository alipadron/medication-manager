<?php

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists people', function () {
    $alex = Person::factory()->create(['name' => 'Alex']);
    Person::factory()->create(['name' => 'Wife']);

    $this->getJson('/api/v1/people')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', $alex->name);
});

it('creates a person', function () {
    $response = $this->postJson('/api/v1/people', ['name' => 'Alex'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Alex');

    $this->assertDatabaseHas('people', ['id' => $response->json('data.id'), 'name' => 'Alex']);
});

it('requires a name', function () {
    $this->postJson('/api/v1/people', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
