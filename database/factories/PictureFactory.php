<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Picture>
 */
class PictureFactory extends Factory
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
            'thumbnail_path' => 'pictures/'.fake()->uuid().'_thumb.jpg',
        ];
    }

    /**
     * A picture whose thumbnail job has not run yet.
     */
    public function withoutThumbnail(): static
    {
        return $this->state(fn (array $attributes) => [
            'thumbnail_path' => null,
        ]);
    }

    public function forParty(Party $party): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $party->id,
        ]);
    }
}
