<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePresetSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the printable/exportable view of a schedule group's grid.
 *
 * This mirrors the data shown by the ScheduleGroups/Show grid: subjects are
 * chunked per grade by `exams_per_day`, only enrolled sections get a row, and
 * unplaced exams are flagged in their section row. Subject groups expose a
 * `uniform_teacher` so the exports can reproduce the grid's rowspans.
 */
class ScheduleGrid
{
    /**
     * @return array{title: string, description: string|null, exam_title: string, school_year: string|null, date_of_assessment: string|null, days: array<int, array{day: int, grades: array<int, array{grade: string, subjects: array<int, array<string, mixed>>}>}>}
     */
    public function forGroup(ScheduleGroup $group): array
    {
        $group->loadMissing([
            'timePreset.slots',
            'schoolYear',
            'quarter',
            'sections.gradeLevel',
            'sections.room',
            'subjects.gradeLevel',
            'schedules.section.gradeLevel',
            'schedules.section.room',
            'schedules.subject',
            'schedules.subjectTeacher',
            'schedules.proctor',
            'unplaced',
        ]);

        $days = max(1, (int) ($group->days ?? 1));
        $examsPerDay = max(1, (int) ($group->exams_per_day ?? 1));

        $sections = $group->sections->sortBy('id')->values();
        $subjects = $group->subjects
            ->sortBy(fn (Subject $subject): int => ((int) ($subject->pivot->sort_order ?? 0)) * 1000000 + (int) $subject->id)
            ->values();

        $sectionsByGrade = $sections->groupBy(fn (Section $section): int => (int) $section->grade_level_id);
        $subjectsByGrade = $subjects->groupBy(fn (Subject $subject): int => (int) $subject->grade_level_id);

        $schedules = [];
        foreach ($group->schedules as $schedule) {
            $schedules[$this->key((int) ($schedule->day ?? 1), (int) $schedule->section_id, (int) $schedule->subject_id)] = $schedule;
        }

        $unplaced = [];
        foreach ($group->unplaced as $item) {
            $unplaced[$this->key((int) ($item->day ?? 1), (int) $item->section_id, (int) $item->subject_id)] = $item;
        }

        $enrolled = $this->enrolledPairs($sections, $subjects);

        $slots = $group->timePreset?->slots ?? collect();
        $defaultSlots = $slots->whereNull('grade_level_id')->sortBy('sort_order')->values();
        $slotsByGrade = $slots->whereNotNull('grade_level_id')
            ->groupBy(fn (TimePresetSlot $slot): int => (int) $slot->grade_level_id);

        $gradeIds = $sectionsByGrade->keys()->map(fn ($id): int => (int) $id)->sort()->values();

        $daysData = [];

        for ($day = 1; $day <= $days; $day++) {
            $gradesData = [];

            foreach ($gradeIds as $gradeId) {
                $gradeSections = $sectionsByGrade->get($gradeId, collect())->values();
                $gradeSubjects = $subjectsByGrade->get($gradeId, collect())->values();

                $gradeSlots = $slotsByGrade->get($gradeId, collect())->sortBy('sort_order')->values();
                $effectiveSlots = $gradeSlots->isNotEmpty() ? $gradeSlots : $defaultSlots;

                $subjectGroups = [];

                for ($period = 0; $period < $examsPerDay; $period++) {
                    $subject = $gradeSubjects->get((($day - 1) * $examsPerDay) + $period);

                    if (! $subject instanceof Subject) {
                        continue;
                    }

                    $slot = $effectiveSlots->get($period);
                    $time = $slot instanceof TimePresetSlot
                        ? $this->timeLabel($slot->start_time, $slot->end_time)
                        : '';

                    $rows = [];

                    foreach ($gradeSections as $section) {
                        $schedule = $schedules[$this->key($day, (int) $section->id, (int) $subject->id)] ?? null;
                        $pair = ((int) $section->id).'-'.((int) $subject->id);

                        if (! isset($enrolled[$pair]) && ! $schedule instanceof Schedule) {
                            continue;
                        }

                        $unplacedItem = $unplaced[$this->key($day, (int) $section->id, (int) $subject->id)] ?? null;

                        $rows[] = [
                            'section' => $section->section_name,
                            'subject_teacher' => $this->teacherName($schedule?->subjectTeacher),
                            'subject_teacher_id' => $schedule?->subject_teacher_id === null ? null : (int) $schedule->subject_teacher_id,
                            'proctor' => $this->teacherName($schedule?->proctor),
                            'room' => $section->room?->room_name ?? '',
                            'scheduled' => $schedule instanceof Schedule,
                            'unplaced' => $unplacedItem !== null,
                        ];
                    }

                    if ($rows === []) {
                        if (! $subject->is_elective) {
                            continue;
                        }

                        $subjectGroups[] = [
                            'time' => $time,
                            'subject' => $subject->subject_name,
                            'elective' => true,
                            'placeholder' => true,
                            'uniform_teacher' => null,
                            'rows' => [[
                                'section' => 'NA',
                                'subject_teacher' => 'Subject Teachers',
                                'subject_teacher_id' => null,
                                'proctor' => 'Subject Teachers',
                                'room' => 'Classroom Assignment',
                                'scheduled' => false,
                                'unplaced' => false,
                            ]],
                        ];

                        continue;
                    }

                    $subjectGroups[] = [
                        'time' => $time,
                        'subject' => $subject->subject_name,
                        'elective' => (bool) $subject->is_elective,
                        'placeholder' => false,
                        'uniform_teacher' => $this->uniformTeacher($rows),
                        'rows' => $rows,
                    ];
                }

                if ($subjectGroups === []) {
                    continue;
                }

                $gradesData[] = [
                    'grade' => (string) ($gradeSections->first()?->gradeLevel?->grade_level ?? $gradeId),
                    'subjects' => $subjectGroups,
                ];
            }

            $daysData[] = ['day' => $day, 'grades' => $gradesData];
        }

        return [
            'title' => $group->title,
            'description' => $group->description,
            'exam_title' => $this->examTitle($group),
            'school_year' => $group->schoolYear?->name,
            'date_of_assessment' => $this->assessmentDate($group),
            'days' => $daysData,
        ];
    }

    /**
     * Build the official exam title, e.g. "SECOND QUARTER EXAMINATION".
     */
    private function examTitle(ScheduleGroup $group): string
    {
        $quarter = trim((string) ($group->quarter?->name ?? ''));

        if (! preg_match('/(\d+)/', $quarter, $matches)) {
            return $quarter !== '' ? strtoupper($quarter).' EXAMINATION' : 'QUARTER EXAMINATION';
        }

        $ordinals = [1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD', 4 => 'FOURTH'];
        $ordinal = $ordinals[(int) $matches[1]] ?? strtoupper($quarter);

        return $ordinal.' QUARTER EXAMINATION';
    }

    /**
     * Derive the assessment date from a leading date in the group title.
     */
    private function assessmentDate(ScheduleGroup $group): ?string
    {
        if (! preg_match('/^([A-Za-z]+ \d{1,2}, \d{4})/', trim((string) $group->title), $matches)) {
            return null;
        }

        try {
            return Carbon::parse($matches[1])->format('F j, Y (l)');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Subject>  $subjects
     * @return array<string, bool>
     */
    private function enrolledPairs(Collection $sections, Collection $subjects): array
    {
        $sectionIds = $sections->pluck('id')->all();
        $subjectIds = $subjects->pluck('id')->all();

        if ($sectionIds === [] || $subjectIds === []) {
            return [];
        }

        return SectionSubjectTeacher::query()
            ->whereIn('section_id', $sectionIds)
            ->whereIn('subject_id', $subjectIds)
            ->get(['section_id', 'subject_id'])
            ->mapWithKeys(fn (SectionSubjectTeacher $row): array => [
                ((int) $row->section_id).'-'.((int) $row->subject_id) => true,
            ])
            ->all();
    }

    private function key(int $day, int $sectionId, int $subjectId): string
    {
        return $day.'-'.$sectionId.'-'.$subjectId;
    }

    private function timeLabel(?string $start, ?string $end): string
    {
        if ($start === null || $end === null) {
            return '';
        }

        return Carbon::parse($start)->format('g:i A').' – '.Carbon::parse($end)->format('g:i A');
    }

    private function teacherName(?Teacher $teacher): string
    {
        if ($teacher === null) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $teacher->firstname,
            $teacher->middlename,
            $teacher->lastname,
        ])));
    }

    /**
     * Resolve the teacher shared by every scheduled row of a subject group.
     *
     * Mirrors the grid's uniform-teacher rowspan: when all scheduled rows have
     * the same teacher, that teacher is rendered once across the whole group.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function uniformTeacher(array $rows): ?string
    {
        $scheduled = array_values(array_filter($rows, fn (array $row): bool => $row['scheduled'] === true));

        if ($scheduled === []) {
            return null;
        }

        $teacherIds = array_values(array_unique(array_map(
            fn (array $row): ?int => $row['subject_teacher_id'],
            $scheduled,
        ), SORT_REGULAR));

        if (count($teacherIds) !== 1 || $teacherIds[0] === null) {
            return null;
        }

        return $scheduled[0]['subject_teacher'];
    }
}
