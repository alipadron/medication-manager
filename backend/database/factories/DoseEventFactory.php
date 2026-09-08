<?php

namespace Database\Factories;

use App\Enums\DoseEventOutcome;
use App\Models\DoseEvent;
use App\Models\Intake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoseEvent>
 */
class DoseEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'intake_id' => Intake::factory(),
            'outcome' => DoseEventOutcome::OnTime,
            'taken_at' => null,
        ];
    }

    /**
     * Record the intake as taken late, at a given actual time.
     */
    public function late(?string $takenAt = null): static
    {
        return $this->state(fn (): array => [
            'outcome' => DoseEventOutcome::Late,
            'taken_at' => $takenAt ?? fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Record the intake as skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (): array => [
            'outcome' => DoseEventOutcome::Skipped,
            'taken_at' => null,
        ]);
    }
}
