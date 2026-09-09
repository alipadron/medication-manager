<?php

namespace Database\Factories;

use App\Models\HomeInventoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeInventoryEntry>
 */
class HomeInventoryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+3 years')->format('Y-m-d'),
        ];
    }
}
