<?php

namespace Database\Factories;

use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioMessage>
 */
class AudioMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->withAudioGuestbook(),
            'path' => 'parties/1/audio/'.fake()->uuid().'.mp3',
            'title' => null,
            'duration_seconds' => fake()->numberBetween(10, 180),
            'recorded_at' => now(),
            'position' => fake()->numberBetween(1, 40),
        ];
    }
}
