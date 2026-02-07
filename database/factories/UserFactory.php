<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'provider' => 'zoho',
            'provider_id' => fake()->unique()->uuid(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user with a boothparty.fr email.
     */
    public function boothparty(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->userName().'@boothparty.fr',
        ]);
    }

    /**
     * Create a user with a specific provider.
     */
    public function withProvider(string $provider, ?string $providerId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'provider_id' => $providerId ?? fake()->unique()->uuid(),
        ]);
    }
}
