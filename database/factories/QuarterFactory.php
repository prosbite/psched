<?php

namespace Database\Factories;

use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quarter>
 */
class QuarterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-9 months', '+6 months');
        $end = (clone $start)->modify('+2 months');

        return [
            'name' => 'Quarter '.fake()->unique()->numberBetween(1, 999),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ];
    }
}
