<?php

namespace Database\Factories;

use App\Models\PushNotification;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Person;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Produces a fixed-times slot key (the unique guard) with a stable
     * intake identity and a distinct calendar day. Intake_id is left null so
     * the record reads as an interval-style delivery unless overridden.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'schedule_id' => Schedule::factory(),
            'intake_id' => null,
            'slot_key' => 'fixed:'.fake()->unique()->numberBetween(1, 999999).':'.fake()->date(),
        ];
    }
}