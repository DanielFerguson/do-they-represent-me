<?php

namespace Database\Factories;

use App\Models\Parliament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parliament>
 */
class ParliamentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(61, 30000),
            'starts_on' => '2022-11-26',
            'ends_on' => null,
        ];
    }
}
