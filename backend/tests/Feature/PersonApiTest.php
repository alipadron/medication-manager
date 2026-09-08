<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_people(): void
    {
        $alex = Person::factory()->create(['name' => 'Alex']);
        Person::factory()->create(['name' => 'Wife']);

        $this->getJson('/api/v1/people')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $alex->name);
    }

    public function test_creates_a_person(): void
    {
        $response = $this->postJson('/api/v1/people', ['name' => 'Alex'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alex');

        $this->assertDatabaseHas('people', ['id' => $response->json('data.id'), 'name' => 'Alex']);
    }

    public function test_requires_a_name(): void
    {
        $this->postJson('/api/v1/people', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
