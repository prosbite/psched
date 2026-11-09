<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSubjectTeacher>
 */
class SectionSubjectTeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
        ];
    }
}
