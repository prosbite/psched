<?php

namespace Database\Factories;

use App\Enums\ScheduleGroupStatus;
use App\Models\ScheduleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleGroup>
 */
class ScheduleGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Exam Day '.fake()->unique()->numberBetween(1, 999),
            'description' => fake()->sentence(),
            'status' => ScheduleGroupStatus::Draft,
            'time_preset_id' => null,
            'max_proctors_per_teacher' => 2,
        ];
    }

    /**
     * Indicate that the group is finalized.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScheduleGroupStatus::Active,
        ]);
    }
}
