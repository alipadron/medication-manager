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
     * schedule_id is derived from the intake (kept in sync at insert time);
     * schedule-scoped interval events pass an explicit schedule_id and a
     * null intake_id instead.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'intake_id' => Intake::factory(),
            'schedule_id' => function (array $attributes): ?int {
                $intakeId = $attributes['intake_id'] ?? null;

                if ($intakeId instanceof Intake) {
                    return $intakeId->schedule_id;
                }

                return is_numeric($intakeId)
                    ? Intake::find((int) $intakeId)?->schedule_id
                    : null;
            },
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
