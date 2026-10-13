<?php

namespace Database\Factories;

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\PartyActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyActivity>
 */
class PartyActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'type' => PartyActivityType::PicturesImported,
            'properties' => ['count' => fake()->numberBetween(1, 30)],
        ];
    }
}
