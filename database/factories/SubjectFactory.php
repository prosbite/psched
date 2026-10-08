<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_name' => fake()->unique()->randomElement([
                'Mathematics',
                'English',
                'Science',
                'Filipino',
                'Social Studies',
                'Physical Education',
            ]).' '.fake()->numberBetween(1, 999),
            'grade_level_id' => GradeLevel::factory(),
        ];
    }
}
