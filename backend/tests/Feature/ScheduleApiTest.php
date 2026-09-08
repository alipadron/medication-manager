<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_schedules_with_intakes(): void
    {
        $medication = Medication::factory()->create();

        Schedule::factory()->create(['medication_id' => $medication->id])
            ->intakes()->createMany([
                ['time' => '08:00', 'sort_order' => 0],
                ['time' => '20:00', 'sort_order' => 1],
            ]);

        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.medication_id', $medication->id)
            ->assertJsonCount(2, 'data.0.intakes')
            ->assertJsonPath('data.0.intakes.0.time', '08:00');
    }

    public function test_creates_a_schedule_with_intakes(): void
    {
        $medication = Medication::factory()->create();

        $response = $this->postJson('/api/v1/schedules', [
            'medication_id' => $medication->id,
            'intakes' => [
                ['time' => '08:00'],
                ['time' => '20:00'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.intakes')
            ->assertJsonPath('data.medication_id', $medication->id);

        $scheduleId = $response->json('data.id');
        $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '08:00']);
        $this->assertDatabaseHas('intakes', ['schedule_id' => $scheduleId, 'time' => '20:00']);
    }

    public function test_requires_an_existing_medication(): void
    {
        $this->postJson('/api/v1/schedules', [
            'medication_id' => 9999,
            'intakes' => [['time' => '08:00']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('medication_id');
    }

    public function test_requires_at_least_one_intake(): void
    {
        $medication = Medication::factory()->create();

        $this->postJson('/api/v1/schedules', [
            'medication_id' => $medication->id,
            'intakes' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('intakes');
    }
}
