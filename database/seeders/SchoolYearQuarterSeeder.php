<?php

namespace Database\Seeders;

use App\Models\Quarter;
use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolYearQuarterSeeder extends Seeder
{
    /**
     * Seed the current school year and its four quarters.
     */
    public function run(): void
    {
        $year = now()->year;
        $startYear = now()->month >= 6 ? $year : $year - 1;

        SchoolYear::firstOrCreate([
            'name' => sprintf('%d-%d', $startYear, $startYear + 1),
        ]);

        $start = now()->setDate($startYear, 8, 1)->startOfDay();

        $quarters = [
            ['name' => 'Quarter 1', 'start' => $start->copy(), 'end' => $start->copy()->setDate($startYear, 10, 31)],
            ['name' => 'Quarter 2', 'start' => $start->copy()->setDate($startYear, 11, 1), 'end' => $start->copy()->setDate($startYear + 1, 1, 31)],
            ['name' => 'Quarter 3', 'start' => $start->copy()->setDate($startYear + 1, 2, 1), 'end' => $start->copy()->setDate($startYear + 1, 4, 30)],
            ['name' => 'Quarter 4', 'start' => $start->copy()->setDate($startYear + 1, 5, 1), 'end' => $start->copy()->setDate($startYear + 1, 6, 30)],
        ];

        foreach ($quarters as $quarter) {
            Quarter::firstOrCreate(
                ['name' => $quarter['name']],
                [
                    'start_date' => $quarter['start']->toDateString(),
                    'end_date' => $quarter['end']->toDateString(),
                ],
            );
        }
    }
}
