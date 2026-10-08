<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
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
            'schedule_group_id' => ScheduleGroup::factory(),
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'subject_teacher_id' => Teacher::factory(),
            'proctor_id' => Teacher::factory(),
            'start_time' => sprintf('%02d:00:00', $start),
            'end_time' => sprintf('%02d:00:00', $start + 1),
        ];
    }
}
