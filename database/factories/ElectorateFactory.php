<?php

namespace Database\Factories;

use App\Enums\ElectorateKind;
use App\Models\Electorate;
use App\Models\House;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Electorate>
 */
class ElectorateFactory extends Factory
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
            'kind' => ElectorateKind::District,
            'name' => fake()->unique()->city(),
            'slug' => fake()->unique()->slug(2),
        ];
    }
}
