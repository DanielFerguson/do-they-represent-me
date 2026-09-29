<?php

namespace Database\Factories;

use App\Models\House;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<House>
 */
class HouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'hansard_code' => fake()->unique()->numberBetween(100, 9999),
            'papers_code' => fake()->unique()->numberBetween(100, 9999),
            'seats' => 10,
        ];
    }
}
