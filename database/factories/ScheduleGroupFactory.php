<?php

namespace Database\Factories;

use App\Enums\ScheduleGroupStatus;
use App\Models\Quarter;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
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
            'days' => 3,
            'exams_per_day' => 3,
            'time_preset_id' => null,
            'school_year_id' => SchoolYear::factory(),
            'quarter_id' => Quarter::factory(),
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
