<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\House;
use App\Models\Membership;
use App\Models\Parliament;
use App\Models\ProceedingsDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parliament_id' => Parliament::factory(),
            'house_id' => House::factory(),
            'proceedings_document_id' => fn (array $attributes) => ProceedingsDocument::factory()->state(['house_id' => $attributes['house_id']]),
            'sitting_number' => fake()->unique()->numberBetween(1, 30000),
            'sitting_date' => '2024-05-01',
            'sequence' => 1,
            'body' => 'House',
            'ayes_count' => 0,
            'noes_count' => 0,
        ];
    }

    /**
     * Held while the given member was in the chair.
     */
    public function presidedBy(Membership $membership): static
    {
        return $this->state(['presiding_member_id' => $membership->member_id]);
    }

    /**
     * A conscience vote, where members vote without a party line.
     */
    public function freeVote(): static
    {
        return $this->state(['is_free_vote' => true]);
    }
}
