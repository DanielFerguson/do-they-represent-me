<?php

namespace Database\Factories;

use App\Enums\PolicyStatus;
use App\Models\Policy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Policy>
 */
class PolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 9999),
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(3),
            'question' => fake()->sentence().'?',
            'status' => PolicyStatus::Review,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => PolicyStatus::Published, 'published_at' => now()]);
    }
}
