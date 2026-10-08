<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_name' => fake()->unique()->bothify('Section ##?'),
            'grade_level_id' => GradeLevel::factory(),
            'room_id' => Room::factory(),
        ];
    }
}
