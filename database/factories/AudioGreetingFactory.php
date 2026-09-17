<?php

namespace Database\Factories;

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioGreeting>
 */
class AudioGreetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->withAudioGuestbook(),
            'original_path' => 'parties/1/audio/'.fake()->uuid().'.webm',
            'mp3_path' => null,
            'duration_seconds' => null,
            'client_note' => null,
            'status' => GreetingStatus::Draft,
            'rejection_reason' => null,
            'submitted_at' => null,
            'validated_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GreetingStatus::Pending,
            'mp3_path' => 'parties/1/audio/'.fake()->uuid().'.mp3',
            'duration_seconds' => fake()->numberBetween(20, 90),
            'submitted_at' => now(),
        ]);
    }

    public function validated(): static
    {
        return $this->pending()->state(fn (array $attributes) => [
            'status' => GreetingStatus::Validated,
            'validated_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Le son est saturé.'): static
    {
        return $this->pending()->state(fn (array $attributes) => [
            'status' => GreetingStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
