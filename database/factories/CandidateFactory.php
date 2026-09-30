<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'election_id' => Election::factory(),
            'electorate_id' => Electorate::factory(),
            'ballot_group' => null,
            'ballot_position' => fake()->unique()->numberBetween(1, 30000),
            'given_names' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'ballot_party' => null,
        ];
    }
}
