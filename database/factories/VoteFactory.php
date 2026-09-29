<?php

namespace Database\Factories;

use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'member_id' => Member::factory(),
            'party_id' => null,
            'vote' => VoteValue::Aye,
            'is_teller' => false,
        ];
    }

    /**
     * Cast by the holder of this seat, under the party they held it for.
     */
    public function by(Membership $membership): static
    {
        return $this->state(['member_id' => $membership->member_id, 'party_id' => $membership->party_id]);
    }

    public function aye(): static
    {
        return $this->state(['vote' => VoteValue::Aye]);
    }

    public function no(): static
    {
        return $this->state(['vote' => VoteValue::No]);
    }
}
