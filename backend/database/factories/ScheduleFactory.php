<?php

namespace Database\Factories;

use App\Enums\DosingPattern;
use App\Models\Medication;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medication_id' => Medication::factory(),
            'dosing_pattern' => DosingPattern::FixedTimes,
            'safe_interval_minutes' => 240,
        ];
    }

    /**
     * Make this an interval schedule ("every N hours").
     */
    public function interval(?int $hours = null): static
    {
        return $this->state(fn (): array => [
            'dosing_pattern' => DosingPattern::Interval,
            'interval_hours' => $hours ?? 8,
        ]);
    }
}
