<?php

namespace Database\Factories;

use App\Models\House;
use App\Models\ProceedingsDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProceedingsDocument>
 */
class ProceedingsDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'house_id' => House::factory(),
            'source_key' => fake()->unique()->bothify('house-paper-####'),
            'title' => 'Votes and Proceedings',
            'docx_url' => fake()->url(),
        ];
    }
}
