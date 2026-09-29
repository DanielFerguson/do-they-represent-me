<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $firstName = fake()->firstName(),
            'last_name' => $lastName = fake()->lastName(),
            'display_name' => "{$firstName} {$lastName}",
            'slug' => fake()->unique()->slug(3),
        ];
    }
}
