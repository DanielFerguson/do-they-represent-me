<?php

namespace Database\Factories;

use App\Models\Electorate;
use App\Models\House;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'house_id' => House::factory(),
            'electorate_id' => fn (array $attributes) => Electorate::factory()->state(['house_id' => $attributes['house_id']]),
            'party_id' => Party::factory(),
            'starts_on' => '2022-11-26',
            'ends_on' => null,
        ];
    }

    /**
     * A seat held only between the given dates (inclusive).
     */
    public function between(string $startsOn, ?string $endsOn): static
    {
        return $this->state(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }
}
