<?php

namespace Database\Factories;

use App\Models\Picture;
use App\Models\PrintJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'picture_id' => Picture::factory(),
        ];
    }

    /**
     * A job the booth has already printed.
     */
    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'printed_at' => now(),
        ]);
    }
}
