<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'display_name' => null,
            'description' => null,
            'banner_path' => null,
            'user_id' => null,
            'expire_at' => null,
            'source_key' => null,
            'source_event_id' => null,
        ];
    }

    public function fromSourceEvent(string $sourceEventId, string $sourceKey = 'stub'): static
    {
        return $this->state(fn (array $attributes) => [
            'source_key' => $sourceKey,
            'source_event_id' => $sourceEventId,
        ]);
    }

    public function withOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function withBanner(): static
    {
        return $this->state(fn (array $attributes) => [
            'banner_path' => 'banners/'.fake()->uuid().'.jpg',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expire_at' => now()->subDay(),
        ]);
    }

    public function expiresAt(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expire_at' => $date,
        ]);
    }
}
