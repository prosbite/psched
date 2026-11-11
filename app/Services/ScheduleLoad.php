<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * Aggregates teaching, proctoring, and overall teacher load from persisted
 * schedules. A "busy slot" is a unique day/start/end combination, so a teacher
 * handling the same subject across several sections at the same time is only
 * counted once.
 */
class ScheduleLoad
{
    /**
     * @param  Collection<int, Schedule>  $schedules
     * @param  array<int, int>  $teacherIds
     * @return array{
     *     teaching: array<int, int>,
     *     proctor: array<int, int>,
     *     overall: array<int, int>,
     *     teaching_by_day: array<int, array<int, int>>,
     *     proctor_by_day: array<int, array<int, int>>,
     *     overall_by_day: array<int, array<int, int>>
     * }
     */
    public function fromSchedules(Collection $schedules, array $teacherIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $teacherIds)));

        $teachingKeys = [];
        $overallKeys = [];
        $proctorCounts = [];
        $proctorByDay = [];

        foreach ($ids as $id) {
            $teachingKeys[$id] = [];
            $overallKeys[$id] = [];
            $proctorCounts[$id] = 0;
            $proctorByDay[$id] = [];
        }

        foreach ($schedules as $schedule) {
            if ($schedule->start_time === null || $schedule->end_time === null) {
                continue;
            }

            $day = (int) ($schedule->day ?? 1);
            $key = self::slotKey($day, (string) $schedule->start_time, (string) $schedule->end_time);

            $subjectTeacher = $schedule->subject_teacher_id === null ? null : (int) $schedule->subject_teacher_id;
            $proctor = $schedule->proctor_id === null ? null : (int) $schedule->proctor_id;

            if ($subjectTeacher !== null) {
                $teachingKeys[$subjectTeacher] ??= [];
                $overallKeys[$subjectTeacher] ??= [];

                $teachingKeys[$subjectTeacher][$key] = true;
                $overallKeys[$subjectTeacher][$key] = true;
            }

            if ($proctor !== null) {
                $overallKeys[$proctor] ??= [];

                $overallKeys[$proctor][$key] = true;
                $proctorCounts[$proctor] = ($proctorCounts[$proctor] ?? 0) + 1;
                $proctorByDay[$proctor][$day] = ($proctorByDay[$proctor][$day] ?? 0) + 1;
            }
        }

        $allIds = array_values(array_unique(array_merge(
            $ids,
            array_keys($teachingKeys),
            array_keys($overallKeys),
            array_keys($proctorCounts),
        )));

        $teaching = [];
        $proctor = [];
        $overall = [];
        $teachingByDay = [];
        $overallByDay = [];

        foreach ($allIds as $id) {
            $teaching[$id] = count($teachingKeys[$id] ?? []);
            $proctor[$id] = (int) ($proctorCounts[$id] ?? 0);
            $overall[$id] = count($overallKeys[$id] ?? []);
            $teachingByDay[$id] = self::countByDay($teachingKeys[$id] ?? []);
            $overallByDay[$id] = self::countByDay($overallKeys[$id] ?? []);

            $byDay = $proctorByDay[$id] ?? [];
            ksort($byDay);
            $proctorByDay[$id] = $byDay;
        }

        return [
            'teaching' => $teaching,
            'proctor' => $proctor,
            'overall' => $overall,
            'teaching_by_day' => $teachingByDay,
            'proctor_by_day' => $proctorByDay,
            'overall_by_day' => $overallByDay,
        ];
    }

    /**
     * Build the shared busy-slot key used for teaching and overall load.
     */
    public static function slotKey(int $day, string $start, string $end): string
    {
        return $day.'|'.self::normalizeTime($start).'|'.self::normalizeTime($end);
    }

    /**
     * Normalize a time value (HH:MM or HH:MM:SS) to HH:MM.
     */
    private static function normalizeTime(string $time): string
    {
        return substr(trim($time), 0, 5);
    }

    /**
     * Count distinct busy-slot keys per day.
     *
     * @param  array<string, bool>  $keys
     * @return array<int, int>
     */
    private static function countByDay(array $keys): array
    {
        $counts = [];

        foreach (array_keys($keys) as $key) {
            $day = (int) explode('|', $key, 2)[0];
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
