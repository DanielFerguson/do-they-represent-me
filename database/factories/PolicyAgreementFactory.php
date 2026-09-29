<?php

namespace Database\Factories;

use App\Enums\AgreementCategory;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyAgreement>
 */
class PolicyAgreementFactory extends Factory
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
            'subject_type' => 'party',
            'subject_id' => Party::factory(),
            'votes_same' => 0,
            'votes_same_strong' => 1,
            'votes_differ' => 0,
            'votes_differ_strong' => 0,
            'votes_absent' => 0,
            'votes_absent_strong' => 0,
            'agreement' => 1.0,
            'category' => AgreementCategory::For3->value,
            'computed_at' => now(),
        ];
    }

    /**
     * A score with the given agreement, in the category it falls in.
     */
    public function agreement(float $agreement): static
    {
        return $this->state(['agreement' => $agreement, 'category' => AgreementCategory::forAgreement($agreement)->value]);
    }

    /**
     * A record with no figure, such as too few votes.
     */
    public function unscored(AgreementCategory $category): static
    {
        return $this->state(['agreement' => null, 'category' => $category->value]);
    }
}
