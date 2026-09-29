<?php

namespace Database\Factories;

use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\Policy;
use App\Models\PolicyDivision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyDivision>
 */
class PolicyDivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'policy_id' => Policy::factory(),
            'division_id' => Division::factory(),
            'direction' => VoteValue::Aye,
            'is_strong' => false,
            'rationale' => null,
        ];
    }

    /**
     * The policy's "agree" answer matches this vote in the division.
     */
    public function agreeWhen(VoteValue $vote): static
    {
        return $this->state(['direction' => $vote]);
    }

    /**
     * A second or third reading, weighted as a strong vote.
     */
    public function strong(): static
    {
        return $this->state(['is_strong' => true]);
    }
}
