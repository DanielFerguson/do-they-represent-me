<?php

namespace Database\Factories;

use App\Models\Locality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locality>
 */
class LocalityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sal_code' => (string) fake()->unique()->numberBetween(20001, 29999),
            'name' => fake()->unique()->city(),
            'postcodes' => [(string) fake()->numberBetween(3000, 3999)],
        ];
    }
}
