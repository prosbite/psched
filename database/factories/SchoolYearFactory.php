<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;
        $start = (now()->month >= 6 ? $year : $year - 1) + fake()->unique()->numberBetween(0, 1000);

        return [
            'name' => sprintf('%d-%d', $start, $start + 1),
        ];
    }
}
