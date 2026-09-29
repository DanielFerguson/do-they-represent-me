<?php

namespace Database\Factories;

use App\Models\PolicyImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyImport>
 */
class PolicyImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sha256' => $sha256 = hash('sha256', fake()->uuid()),
            'path' => "policy-research/workbooks/{$sha256}.xlsx",
            'user_id' => null,
            'summary' => ['policies_by_status' => ['review' => 1], 'links' => 1, 'removed' => 0],
        ];
    }
}
