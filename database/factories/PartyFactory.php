<?php

namespace Database\Factories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'short_name' => fake()->unique()->lexify('???'),
            'slug' => fake()->unique()->slug(2),
            'colour' => null,
            'is_whipless' => false,
        ];
    }

    /**
     * Members who sit without a party whip, such as independents.
     */
    public function whipless(): static
    {
        return $this->state(['is_whipless' => true]);
    }
}
