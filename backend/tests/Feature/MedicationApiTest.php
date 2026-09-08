<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_medications(): void
    {
        $alex = Person::factory()->create();
        $medication = Medication::factory()->create(['person_id' => $alex->id, 'name' => 'Lisinopril']);
        Medication::factory()->create(['person_id' => $alex->id]);

        $this->getJson('/api/v1/medications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $medication->name)
            ->assertJsonPath('data.0.person_id', $alex->id);
    }

    public function test_index_filters_medications_by_person(): void
    {
        $alex = Person::factory()->create();
        $wife = Person::factory()->create();

        Medication::factory()->create(['person_id' => $alex->id, 'name' => 'Alex Med']);
        Medication::factory()->create(['person_id' => $wife->id, 'name' => 'Wife Med']);

        $this->getJson('/api/v1/medications?person_id='.$alex->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alex Med');
    }

    public function test_creates_a_medication(): void
    {
        $person = Person::factory()->create();

        $response = $this->postJson('/api/v1/medications', [
            'person_id' => $person->id,
            'name' => 'Lisinopril',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lisinopril')
            ->assertJsonPath('data.person_id', $person->id);

        $this->assertDatabaseHas('medications', ['id' => $response->json('data.id')]);
    }

    public function test_requires_an_existing_person(): void
    {
        $this->postJson('/api/v1/medications', [
            'person_id' => 9999,
            'name' => 'Lisinopril',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_id');
    }
}
