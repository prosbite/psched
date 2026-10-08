<?php

namespace App\Services;

use App\Models\Schedule;

/**
 * Evaluates schedule candidates against an in-memory set of existing
 * schedules. It is intentionally free of database queries so the schedule
 * generator can call it repeatedly without N+1 lookups.
 */
class ConflictChecker
{
    /**
     * Check a candidate schedule against existing entries.
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<string, mixed>  $candidate
     * @return array<int, string> The conflict reasons, empty when valid.
     */
    public function conflicts(array $existing, array $candidate, int $maxProctorsPerTeacher): array
    {
        $reasons = [];

        if ($this->proctorEqualsSubjectTeacher($candidate)) {
            $reasons[] = 'The proctor cannot be the same teacher as the subject teacher.';
        }

        foreach ($existing as $entry) {
            if ($this->sameSection($entry, $candidate) && $this->overlaps($entry, $candidate)) {
                $reasons[] = 'This section already has an overlapping schedule at this time.';
            }

            if ($this->sharesTeacher($entry, $candidate) && $this->overlaps($entry, $candidate)) {
                $reasons[] = 'A teacher is already assigned to an overlapping schedule.';
            }
        }

        if ($this->proctorCapExceeded($existing, $candidate, $maxProctorsPerTeacher)) {
            $reasons[] = sprintf(
                'The proctor already proctors the maximum of %d schedule(s) in this group.',
                $maxProctorsPerTeacher,
            );
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Determine whether a candidate is conflict-free.
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<string, mixed>  $candidate
     */
    public function isConflictFree(array $existing, array $candidate, int $maxProctorsPerTeacher): bool
    {
        return $this->conflicts($existing, $candidate, $maxProctorsPerTeacher) === [];
    }

    /**
     * Convert a persisted schedule into an in-memory entry.
     *
     * @return array<string, mixed>
     */
    public function entryFromSchedule(Schedule $schedule): array
    {
        return [
            'section_id' => $schedule->section_id === null ? null : (int) $schedule->section_id,
            'subject_teacher_id' => $schedule->subject_teacher_id === null ? null : (int) $schedule->subject_teacher_id,
            'proctor_id' => $schedule->proctor_id === null ? null : (int) $schedule->proctor_id,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
        ];
    }

    /**
     * Determine whether two candidates share any teacher (either role).
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    public function sharesTeacher(array $first, array $second): bool
    {
        $firstTeachers = $this->teachers($first);
        $secondTeachers = $this->teachers($second);

        return $firstTeachers !== [] && array_intersect($firstTeachers, $secondTeachers) !== [];
    }

    /**
     * Determine whether two entries belong to the same section.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    public function sameSection(array $first, array $second): bool
    {
        return isset($first['section_id'], $second['section_id'])
            && (int) $first['section_id'] === (int) $second['section_id'];
    }

    /**
     * Determine whether two entries overlap in time.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    public function overlaps(array $first, array $second): bool
    {
        $startA = $this->toMinutes($first['start_time'] ?? null);
        $endA = $this->toMinutes($first['end_time'] ?? null);
        $startB = $this->toMinutes($second['start_time'] ?? null);
        $endB = $this->toMinutes($second['end_time'] ?? null);

        if ($startA === null || $endA === null || $startB === null || $endB === null) {
            return false;
        }

        return $startA < $endB && $startB < $endA;
    }

    /**
     * Determine whether the candidate proctor is the subject teacher.
     *
     * @param  array<string, mixed>  $candidate
     */
    public function proctorEqualsSubjectTeacher(array $candidate): bool
    {
        $proctor = $candidate['proctor_id'] ?? null;
        $subjectTeacher = $candidate['subject_teacher_id'] ?? null;

        return $proctor !== null && $subjectTeacher !== null && (int) $proctor === (int) $subjectTeacher;
    }

    /**
     * Determine whether the candidate would exceed the proctor cap.
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<string, mixed>  $candidate
     */
    public function proctorCapExceeded(array $existing, array $candidate, int $maxProctorsPerTeacher): bool
    {
        $proctor = $candidate['proctor_id'] ?? null;

        if ($proctor === null) {
            return false;
        }

        $count = 0;

        foreach ($existing as $entry) {
            if (isset($entry['proctor_id']) && (int) $entry['proctor_id'] === (int) $proctor) {
                $count++;
            }
        }

        return $count >= $maxProctorsPerTeacher;
    }

    /**
     * Get the distinct teacher ids assigned to an entry in any role.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, int>
     */
    private function teachers(array $entry): array
    {
        $teachers = [];

        if (isset($entry['subject_teacher_id'])) {
            $teachers[] = (int) $entry['subject_teacher_id'];
        }

        if (isset($entry['proctor_id'])) {
            $teachers[] = (int) $entry['proctor_id'];
        }

        return array_values(array_unique($teachers));
    }

    /**
     * Convert a time value into minutes since midnight.
     */
    public function toMinutes(?string $time): ?int
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $parts = explode(':', $time);

        if (count($parts) < 2) {
            return null;
        }

        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }
}
