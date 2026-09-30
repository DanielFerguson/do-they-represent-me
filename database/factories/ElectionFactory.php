<?php

namespace Database\Factories;

use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Election>
 */
class ElectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2030, 2100);

        return [
            'slug' => (string) $year,
            'name' => "{$year} Victorian state election",
            'held_on' => "{$year}-11-28",
        ];
    }

    /**
     * An election that has already been held.
     */
    public function past(): static
    {
        return $this->state(fn (): array => ['held_on' => today()->subYears(fake()->unique()->numberBetween(1, 40))->toDateString()]);
    }
}
