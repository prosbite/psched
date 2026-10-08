<?php

namespace App\Services;

use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePresetSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a full exam grid for a schedule group and reports anything that
 * could not be placed. The generator is deterministic and greedy: tasks are
 * processed in section order, then subject order, and each is placed in the
 * earliest conflict-free slot using the least-loaded available teachers.
 */
class ScheduleGenerator
{
    public function __construct(private readonly ConflictChecker $conflicts) {}

    /**
     * Generate a grid for the given schedule group.
     *
     * @return array{placed: array<int, array<string, mixed>>, unplaced: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function generate(ScheduleGroup $group): array
    {
        $group->loadMissing('timePreset.slots');

        $slots = $group->timePreset?->slots?->values() ?? collect();
        $sections = $group->sections()->with('gradeLevel')->orderBy('sections.id')->get();
        $subjects = $group->subjects()->orderBy('subjects.id')->get();

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

        $tasks = $this->buildTasks($sections, $subjects);

        if ($slots->isEmpty()) {
            foreach ($tasks as $task) {
                $unplaced[] = $this->unplacedEntry($task, 'No time preset slots are configured for this schedule group.');
            }

            return ['placed' => $placed, 'unplaced' => $unplaced, 'warnings' => $warnings];
        }

        $maxProctors = (int) ($group->max_proctors_per_teacher ?? 2);
        $qualified = $this->qualifiedTeachers();
        $teacherIds = Teacher::orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $teachingLoad = [];
        $proctorLoad = [];
        $usedSlots = [];

        foreach ($tasks as $task) {
            $section = $task['section'];
            $subject = $task['subject'];
            $candidates = $qualified[(int) $subject->id] ?? [];

            if ($candidates === []) {
                $unplaced[] = $this->unplacedEntry(
                    $task,
                    sprintf('No teacher is qualified to teach "%s".', $subject->subject_name),
                );

                continue;
            }

            $assignment = $this->assignTask(
                $group,
                $section,
                $subject,
                $slots,
                $candidates,
                $teacherIds,
                $placed,
                $usedSlots,
                $teachingLoad,
                $proctorLoad,
                $maxProctors,
            );

            if ($assignment === null) {
                $sectionIsFull = count($usedSlots[(int) $section->id] ?? []) >= $slots->count();
                $reason = $sectionIsFull
                    ? 'All available time slots are already used for this section.'
                    : 'No conflict-free teacher and proctor assignment is available for any remaining time slot.';
                $unplaced[] = $this->unplacedEntry($task, $reason);

                continue;
            }

            $placed[] = $assignment;
            $usedSlots[(int) $section->id][(int) $assignment['slot_id']] = true;
            $teachingLoad[(int) $assignment['subject_teacher_id']] = ($teachingLoad[(int) $assignment['subject_teacher_id']] ?? 0) + 1;
            $proctorLoad[(int) $assignment['proctor_id']] = ($proctorLoad[(int) $assignment['proctor_id']] ?? 0) + 1;
        }

        return ['placed' => $placed, 'unplaced' => $unplaced, 'warnings' => $warnings];
    }

    /**
     * Attempt to place a single task in the earliest conflict-free slot.
     *
     * @param  Collection<int, TimePresetSlot>  $slots
     * @param  array<int, int>  $candidates
     * @param  array<int, int>  $teacherIds
     * @param  array<int, array<string, mixed>>  $placed
     * @param  array<int, array<int, bool>>  $usedSlots
     * @param  array<int, int>  $teachingLoad
     * @param  array<int, int>  $proctorLoad
     * @return array<string, mixed>|null
     */
    private function assignTask(
        ScheduleGroup $group,
        Section $section,
        Subject $subject,
        Collection $slots,
        array $candidates,
        array $teacherIds,
        array $placed,
        array $usedSlots,
        array $teachingLoad,
        array $proctorLoad,
        int $maxProctors,
    ): ?array {
        $orderedTeachers = $this->orderByLoad($candidates, $teachingLoad);
        $orderedProctors = $this->orderByLoad($teacherIds, $proctorLoad);

        foreach ($slots as $slot) {
            if (isset($usedSlots[(int) $section->id][(int) $slot->id])) {
                continue;
            }

            $base = [
                'schedule_group_id' => (int) $group->id,
                'section_id' => (int) $section->id,
                'subject_id' => (int) $subject->id,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
            ];

            foreach ($orderedTeachers as $subjectTeacherId) {
                foreach ($orderedProctors as $proctorId) {
                    if ($proctorId === $subjectTeacherId) {
                        continue;
                    }

                    $candidate = $base + [
                        'subject_teacher_id' => $subjectTeacherId,
                        'proctor_id' => $proctorId,
                    ];

                    if (! $this->conflicts->isConflictFree($placed, $candidate, $maxProctors)) {
                        continue;
                    }

                    return $candidate + ['slot_id' => (int) $slot->id];
                }
            }
        }

        return null;
    }

    /**
     * Build the list of section/subject pairs that must be scheduled.
     *
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, array{section: Section, subject: Subject}>
     */
    private function buildTasks(Collection $sections, Collection $subjects): array
    {
        $tasks = [];

        foreach ($sections as $section) {
            foreach ($subjects as $subject) {
                if ((int) $subject->grade_level_id === (int) $section->grade_level_id) {
                    $tasks[] = ['section' => $section, 'subject' => $subject];
                }
            }
        }

        return $tasks;
    }

    /**
     * Get the qualified teacher ids for every subject, keyed by subject id.
     *
     * @return array<int, array<int, int>>
     */
    private function qualifiedTeachers(): array
    {
        $map = [];

        $rows = DB::table('teacher_subject')->select('subject_id', 'teacher_id')->get();

        foreach ($rows as $row) {
            $map[(int) $row->subject_id][] = (int) $row->teacher_id;
        }

        foreach ($map as $subjectId => $ids) {
            $ids = array_values(array_unique($ids));
            sort($ids);
            $map[$subjectId] = $ids;
        }

        return $map;
    }

    /**
     * Order teacher ids by current load, then by lowest id.
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
     * Build an unplaced report entry for a task.
     *
     * @param  array{section: Section, subject: Subject}  $task
     * @return array<string, mixed>
     */
    private function unplacedEntry(array $task, string $reason): array
    {
        return [
            'section_id' => (int) $task['section']->id,
            'section_name' => $task['section']->section_name,
            'grade_level_id' => (int) $task['section']->grade_level_id,
            'subject_id' => (int) $task['subject']->id,
            'subject_name' => $task['subject']->subject_name,
            'reason' => $reason,
        ];
    }
}
