<?php

namespace Database\Factories;

use App\Models\TimePreset;
use App\Models\TimePresetSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimePresetSlot>
 */
class TimePresetSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(7, 15);

        return [
            'time_preset_id' => TimePreset::factory(),
            'start_time' => sprintf('%02d:00:00', $start),
            'end_time' => sprintf('%02d:00:00', $start + 1),
            'sort_order' => 0,
        ];
    }
}
