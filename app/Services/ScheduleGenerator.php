<?php

namespace App\Services;

use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePresetSlot;
use Illuminate\Support\Collection;

/**
 * Builds a fixed-timetable, multi-day exam grid for a schedule group.
 *
 * The timetable is derived from the group's ordered subject selection per
 * grade: subjects are chunked into "exams_per_day" periods, so position
 * `(day - 1) * exams_per_day + period` maps to one subject. Every section
 * enrolled in a subject of the same grade therefore sits it at the same
 * day/period, mirroring the school's assessment grid. Enrollment is the
 * `section_subject_teacher` mapping: a section without a row for a subject is
 * not sitting it and is skipped. Proctors are chosen greedily from the
 * least-loaded candidates (lowest overall busy-slot count, then lowest id),
 * respecting the per-day proctor cap and same-day overlap rules. Teachers who
 * handle two or more distinct subjects in the grid are exempt from proctoring.
 *
 * Each day may use its own time preset; a day without an override falls back
 * to the group's default preset. Grade-specific slots still override the
 * preset's default slots for that grade.
 *
 * The subject teacher for each (section, subject) pair is fixed by the
 * section_subject_teacher mapping; only the proctor is chosen by the solver.
 */
class ScheduleGenerator
{
    public function __construct(private readonly ConflictChecker $conflicts) {}

    /**
     * Generate a grid for the given schedule group.
     *
     * @param  array<int, int>|null  $proctorIds  Teachers allowed to proctor; null means all teachers.
     * @return array{placed: array<int, array<string, mixed>>, unplaced: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function generate(ScheduleGroup $group, ?array $proctorIds = null): array
    {
        $group->loadMissing(['timePreset.slots', 'dayPresets.timePreset.slots']);

        $days = max(1, (int) ($group->days ?? 3));
        $examsPerDay = max(1, (int) ($group->exams_per_day ?? 3));
        $maxProctors = (int) ($group->max_proctors_per_teacher ?? 2);

        $sections = $group->sections()->with('gradeLevel')->orderBy('sections.id')->get();
        $subjects = $group->subjects()
            ->orderBy('schedule_group_subjects.sort_order')
            ->orderBy('subjects.id')
            ->get();

        $placed = [];
        $unplaced = [];
        $warnings = [];

        foreach ($sections as $section) {
            if ($section->room_id === null) {
                $warnings[] = sprintf(
                    'Section "%s" has no room assigned; the schedule was generated without one.',
                    $section->section_name,
                );
            }
        }

        $assignments = $this->subjectTeacherMap($sections, $subjects);

        // A teacher who handles two or more distinct subjects in this grid is
        // exempt from proctoring, so they are removed from the candidate pool.
        $proctors = array_values(array_diff(
            $this->proctorCandidates($proctorIds),
            $this->subjectTeachingExemptions($assignments, $subjects),
        ));

        $sectionsByGrade = $sections->groupBy(fn (Section $section): int => (int) $section->grade_level_id);
        $subjectsByGrade = $subjects->groupBy(fn (Subject $subject): int => (int) $subject->grade_level_id);

        $gradeIds = $sectionsByGrade->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        $effectiveSlotsByDay = $this->effectiveSlotsByDay($group, $days, $gradeIds);

        // Teaching work is fixed by the timetable and is counted before any
        // proctor is chosen, so proctors are balanced against the full load
        // they will carry across the whole exam duration.
        $loadTotals = $this->teachingBusyTotals(
            $days,
            $examsPerDay,
            $gradeIds,
            $sectionsByGrade,
            $subjectsByGrade,
            $assignments,
            $effectiveSlotsByDay,
        );

        for ($day = 1; $day <= $days; $day++) {
            foreach ($gradeIds as $gradeId) {
                $gradeSections = $sectionsByGrade->get($gradeId)->values();
                $gradeSubjects = $subjectsByGrade->get($gradeId, collect())->values();
                $effectiveSlots = $effectiveSlotsByDay[$day][$gradeId] ?? collect();

                for ($period = 0; $period < $examsPerDay; $period++) {
                    $subject = $gradeSubjects->get((($day - 1) * $examsPerDay) + $period);

                    if (! $subject instanceof Subject) {
                        continue;
                    }

                    // A section sits a subject only when it has an enrollment
                    // (section_subject_teacher) row for it. A missing row means
                    // "not enrolled", so the section is skipped entirely: no
                    // schedule and no unplaced entry. An enrolled row whose
                    // teacher was removed (null teacher_id) is a real
                    // missing_teacher gap and is reported below.
                    $enrolledSections = $gradeSections
                        ->filter(fn (Section $section): bool => array_key_exists($this->taskKey($section, $subject), $assignments))
                        ->values();

                    if ($enrolledSections->isEmpty()) {
                        continue;
                    }

                    $slot = $effectiveSlots->get($period);

                    if (! $slot instanceof TimePresetSlot) {
                        foreach ($enrolledSections as $section) {
                            $unplaced[] = $this->unplacedEntry(
                                $day,
                                $section,
                                $subject,
                                'missing_slot',
                                'No time slot is configured for this period.',
                                null,
                            );
                        }

                        continue;
                    }

                    foreach ($enrolledSections as $section) {
                        $subjectTeacherId = $assignments[$this->taskKey($section, $subject)];

                        if ($subjectTeacherId === null) {
                            $unplaced[] = $this->unplacedEntry(
                                $day,
                                $section,
                                $subject,
                                'missing_teacher',
                                sprintf('No teacher is assigned to "%s" for "%s".', $subject->subject_name, $section->section_name),
                                $slot,
                            );

                            continue;
                        }

                        if ($subject->is_elective) {
                            $entry = $this->placeElective(
                                $group,
                                $section,
                                $subject,
                                $subjectTeacherId,
                                $day,
                                $slot,
                                $placed,
                                $maxProctors,
                            );

                            if ($entry === null) {
                                $unplaced[] = $this->unplacedEntry(
                                    $day,
                                    $section,
                                    $subject,
                                    'no_proctor',
                                    sprintf('The elective "%s" could not be placed for "%s".', $subject->subject_name, $section->section_name),
                                    $slot,
                                );

                                continue;
                            }

                            $placed[] = $entry;

                            // An elective is automatically proctored by its
                            // subject teacher, so it does not use the proctor budget.
                            continue;
                        }

                        $entry = $this->place(
                            $group,
                            $section,
                            $subject,
                            $subjectTeacherId,
                            $day,
                            $slot,
                            $proctors,
                            $placed,
                            $loadTotals,
                            $maxProctors,
                        );

                        if ($entry === null) {
                            $unplaced[] = $this->unplacedEntry(
                                $day,
                                $section,
                                $subject,
                                'no_proctor',
                                sprintf('No conflict-free proctor is available for "%s" in "%s".', $subject->subject_name, $section->section_name),
                                $slot,
                            );

                            continue;
                        }

                        $placed[] = $entry;
                        $loadTotals[(int) $entry['proctor_id']] = ($loadTotals[(int) $entry['proctor_id']] ?? 0) + 1;
                    }
                }
            }
        }

        return ['placed' => $placed, 'unplaced' => $unplaced, 'warnings' => $warnings];
    }

    /**
     * Attempt to place a single exam in its fixed slot using the least-loaded
     * available proctor, returning null when no proctor can take it.
     *
     * @param  array<int, int>  $proctors
     * @param  array<int, array<string, mixed>>  $placed
     * @param  array<int, int>  $loadTotals
     * @return array<string, mixed>|null
     */
    private function place(
        ScheduleGroup $group,
        Section $section,
        Subject $subject,
        int $subjectTeacherId,
        int $day,
        TimePresetSlot $slot,
        array $proctors,
        array $placed,
        array $loadTotals,
        int $maxProctors,
    ): ?array {
        $base = [
            'schedule_group_id' => (int) $group->id,
            'day' => $day,
            'section_id' => (int) $section->id,
            'subject_id' => (int) $subject->id,
            'subject_teacher_id' => $subjectTeacherId,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
        ];

        // The subject teacher is fixed by the timetable and may legitimately
        // repeat across sections of a grade at the same time (the grid merges
        // them), so the proctor check omits that role and only verifies that
        // the chosen proctor is not already engaged as any entry's teacher.
        $check = $base;
        unset($check['subject_teacher_id']);

        foreach ($this->orderByLoad($proctors, $loadTotals) as $proctorId) {
            if ($proctorId === $subjectTeacherId) {
                continue;
            }

            $candidate = $check + ['proctor_id' => $proctorId];

            if (! $this->conflicts->isConflictFree($placed, $candidate, $maxProctors)) {
                continue;
            }

            return $base + ['proctor_id' => $proctorId, 'slot_id' => (int) $slot->id];
        }

        return null;
    }

    /**
     * Place an elective exam, using its subject teacher as the proctor.
     *
     * Subject teachers may repeat across sections of the same grade at the
     * same time (the grid merges those rows), so the teacher/proctor role is
     * excluded from the conflict check; only section overlaps are considered.
     *
     * @param  array<int, array<string, mixed>>  $placed
     * @return array<string, mixed>|null
     */
    private function placeElective(
        ScheduleGroup $group,
        Section $section,
        Subject $subject,
        int $subjectTeacherId,
        int $day,
        TimePresetSlot $slot,
        array $placed,
        int $maxProctors,
    ): ?array {
        $entry = [
            'schedule_group_id' => (int) $group->id,
            'day' => $day,
            'section_id' => (int) $section->id,
            'subject_id' => (int) $subject->id,
            'subject_teacher_id' => $subjectTeacherId,
            'proctor_id' => $subjectTeacherId,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'slot_id' => (int) $slot->id,
        ];

        $check = $entry;
        unset($check['subject_teacher_id'], $check['proctor_id']);

        if (! $this->conflicts->isConflictFree($placed, $check, $maxProctors)) {
            return null;
        }

        return $entry;
    }

    /**
     * Resolve each day's effective slots per grade.
     *
     * A day's override preset (when present and not deleted) replaces the
     * group's default preset. Within a preset, grade-specific slots replace
     * the preset's default slots, matching the existing grid behavior.
     *
     * @param  Collection<int, int>  $gradeIds
     * @return array<int, array<int, Collection<int, TimePresetSlot>>>
     */
    private function effectiveSlotsByDay(ScheduleGroup $group, int $days, Collection $gradeIds): array
    {
        $dayPresets = $group->dayPresets->keyBy('day');
        $result = [];

        for ($day = 1; $day <= $days; $day++) {
            $preset = $dayPresets->get($day)?->timePreset ?? $group->timePreset;
            $slots = $preset?->slots ?? collect();
            $defaultSlots = $slots->whereNull('grade_level_id')->sortBy('sort_order')->values();
            $slotsByGrade = $slots->whereNotNull('grade_level_id')
                ->groupBy(fn (TimePresetSlot $slot): int => (int) $slot->grade_level_id);

            foreach ($gradeIds as $gradeId) {
                $gradeSlots = $slotsByGrade->get($gradeId, collect())->sortBy('sort_order')->values();

                $result[$day][$gradeId] = $gradeSlots->isNotEmpty() ? $gradeSlots : $defaultSlots;
            }
        }

        return $result;
    }

    /**
     * Count each teacher's distinct teaching busy slots before placing anything.
     *
     * This walks the same day/grade/period/subject order as placement so the
     * pre-pass and the placed grid agree. Electives are included, and sections
     * of the same grade sitting the same subject at the same time count once.
     *
     * @param  Collection<int, int>  $gradeIds
     * @param  Collection<int, Collection<int, Section>>  $sectionsByGrade
     * @param  Collection<int, Collection<int, Subject>>  $subjectsByGrade
     * @param  array<string, int|null>  $assignments
     * @param  array<int, array<int, Collection<int, TimePresetSlot>>>  $effectiveSlotsByDay
     * @return array<int, int>
     */
    private function teachingBusyTotals(
        int $days,
        int $examsPerDay,
        Collection $gradeIds,
        Collection $sectionsByGrade,
        Collection $subjectsByGrade,
        array $assignments,
        array $effectiveSlotsByDay,
    ): array {
        $busy = [];
        $seen = [];

        for ($day = 1; $day <= $days; $day++) {
            foreach ($gradeIds as $gradeId) {
                $gradeSections = $sectionsByGrade->get($gradeId)->values();
                $gradeSubjects = $subjectsByGrade->get($gradeId, collect())->values();

                for ($period = 0; $period < $examsPerDay; $period++) {
                    $subject = $gradeSubjects->get((($day - 1) * $examsPerDay) + $period);

                    if (! $subject instanceof Subject) {
                        continue;
                    }

                    $slot = ($effectiveSlotsByDay[$day][$gradeId] ?? collect())->get($period);

                    if (! $slot instanceof TimePresetSlot) {
                        continue;
                    }

                    $slotKey = ScheduleLoad::slotKey($day, (string) $slot->start_time, (string) $slot->end_time);

                    foreach ($gradeSections as $section) {
                        $taskKey = $this->taskKey($section, $subject);

                        if (! array_key_exists($taskKey, $assignments)) {
                            continue;
                        }

                        $teacherId = $assignments[$taskKey];

                        if ($teacherId === null) {
                            continue;
                        }

                        $teacherId = (int) $teacherId;

                        if (isset($seen[$teacherId][$slotKey])) {
                            continue;
                        }

                        $seen[$teacherId][$slotKey] = true;
                        $busy[$teacherId] = ($busy[$teacherId] ?? 0) + 1;
                    }
                }
            }
        }

        return $busy;
    }

    /**
     * Build a "section-subject" => teacher id map from the section-aware assignments.
     *
     * The map holds a key for every enrolled pair; a pair whose teacher was
     * removed carries a null value, so the generator can tell "not enrolled"
     * (no key) apart from "enrolled but has no teacher" (null value).
     *
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Subject>  $subjects
     * @return array<string, int|null>
     */
    private function subjectTeacherMap(Collection $sections, Collection $subjects): array
    {
        $sectionIds = $sections->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $subjectIds = $subjects->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($sectionIds === [] || $subjectIds === []) {
            return [];
        }

        $map = [];

        $rows = SectionSubjectTeacher::query()
            ->whereIn('section_id', $sectionIds)
            ->whereIn('subject_id', $subjectIds)
            ->get(['section_id', 'subject_id', 'teacher_id']);

        foreach ($rows as $row) {
            $map[((int) $row->section_id).'-'.((int) $row->subject_id)] = $row->teacher_id === null
                ? null
                : (int) $row->teacher_id;
        }

        return $map;
    }

    /**
     * Teacher ids that handle two or more distinct scheduled subjects.
     *
     * Electives count toward this load because they are scheduled too. Subject
     * teachers with this load are removed from the proctor candidate pool.
     *
     * @param  array<string, int|null>  $assignments
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, int>
     */
    private function subjectTeachingExemptions(array $assignments, Collection $subjects): array
    {
        $scheduledSubjectIds = $subjects
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $subjectsByTeacher = [];

        foreach ($assignments as $key => $teacherId) {
            if ($teacherId === null) {
                continue;
            }

            $subjectId = (int) explode('-', $key, 2)[1];

            if (! in_array($subjectId, $scheduledSubjectIds, true)) {
                continue;
            }

            $subjectsByTeacher[$teacherId][$subjectId] = true;
        }

        $exempt = [];

        foreach ($subjectsByTeacher as $teacherId => $subjectIds) {
            if (count($subjectIds) >= 2) {
                $exempt[] = (int) $teacherId;
            }
        }

        return $exempt;
    }

    /**
     * Resolve the proctor candidates, optionally restricted to an allow-list.
     *
     * @param  array<int, int>|null  $proctorIds
     * @return array<int, int>
     */
    private function proctorCandidates(?array $proctorIds): array
    {
        $all = Teacher::orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($proctorIds === null) {
            return $all;
        }

        $allowed = array_map('intval', $proctorIds);

        return array_values(array_intersect($all, $allowed));
    }

    /**
     * Order teacher ids by cumulative overall load, then by lowest id.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $load
     * @return array<int, int>
     */
    private function orderByLoad(array $ids, array $load): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        usort($ids, function (int $a, int $b) use ($load): int {
            return (($load[$a] ?? 0) <=> ($load[$b] ?? 0)) ?: ($a <=> $b);
        });

        return $ids;
    }

    /**
     * Build the lookup key for a section/subject task.
     */
    private function taskKey(Section $section, Subject $subject): string
    {
        return ((int) $section->id).'-'.((int) $subject->id);
    }

    /**
     * Build an unplaced report entry for a task.
     *
     * @return array<string, mixed>
     */
    private function unplacedEntry(
        int $day,
        Section $section,
        Subject $subject,
        string $code,
        string $reason,
        ?TimePresetSlot $slot,
    ): array {
        return [
            'day' => $day,
            'section_id' => (int) $section->id,
            'section_name' => $section->section_name,
            'grade_level_id' => (int) $section->grade_level_id,
            'subject_id' => (int) $subject->id,
            'subject_name' => $subject->subject_name,
            'reason' => $reason,
            'reason_code' => $code,
            'start_time' => $slot?->start_time,
            'end_time' => $slot?->end_time,
        ];
    }
}
