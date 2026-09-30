<?php

namespace Database\Factories;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic' => ContactTopic::General,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'message' => fake()->paragraph(),
        ];
    }

    /**
     * A message the owner has already dealt with.
     */
    public function handled(): static
    {
        return $this->state(['handled_at' => now()]);
    }
}
