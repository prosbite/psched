<?php

namespace Database\Factories;

use App\Models\TimePreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimePreset>
 */
class TimePresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Preset '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
