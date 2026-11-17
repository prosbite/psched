<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\SectionSubjectTeacher;
use App\Models\Teacher;

/**
 * Builds the printable teacher load report for a schedule group.
 *
 * The rows mirror the on-screen Teacher load table: one block per teacher with
 * at least one teaching or proctor row, deduped per day/time/subject/type, and
 * only the teachers that actually appear. The T/P/O counts come from
 * ScheduleLoad, so the report cannot drift from the page's numbers.
 */
class TeacherLoadReport
{
    public function __construct(
        private readonly ScheduleLoad $loads,
        private readonly SubjectTeacherDayLoads $dayLoads,
        private readonly SubjectSlotPlanner $planner,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     school_year: string|null,
     *     quarter: string|null,
     *     days: int,
     *     exams_per_day: int,
     *     max_proctors_per_teacher: int,
     *     summary: array{min: int, max: int, average: float, spread: int},
     *     teachers: array<int, array<string, mixed>>
     * }
     */
    public function forGroup(ScheduleGroup $group): array
    {
        $group->loadMissing([
            'schedules.subject',
            'schedules.subjectTeacher',
            'schedules.proctor',
            'sections',
            'subjects',
            'schoolYear',
            'quarter',
            'timePreset.slots',
            'dayPresets.timePreset.slots',
        ]);

        $days = max(1, (int) ($group->days ?? 1));

        $teachers = Teacher::orderBy('lastname')->get();
        $teacherIds = $teachers->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $loads = $this->loads->fromSchedules($group->schedules, $teacherIds);

        $assignments = SectionSubjectTeacher::query()
            ->whereIn('section_id', $group->sections->pluck('id'))
            ->whereIn('subject_id', $group->subjects->pluck('id'))
            ->with('teacher')
            ->get();

        $dayLoads = $this->dayLoads->compute(
            $this->planner->displayPlanForGroup($group),
            $group,
            $assignments,
        );

        $rowsByTeacher = [];

        foreach ($group->schedules as $schedule) {
            if ($schedule->start_time === null || $schedule->end_time === null) {
                continue;
            }

            $subjectTeacher = $schedule->subjectTeacher;
            $proctor = $schedule->proctor;
            $selfProctored = $schedule->proctor_id !== null
                && $schedule->subject_teacher_id !== null
                && (int) $schedule->proctor_id === (int) $schedule->subject_teacher_id;

            if ($subjectTeacher !== null) {
                $this->addRow(
                    $rowsByTeacher,
                    $subjectTeacher,
                    $schedule,
                    $selfProctored ? 'Subject Teacher & Proctor' : 'Subject Teacher',
                );
            }

            if ($proctor !== null && (! $selfProctored || $subjectTeacher === null)) {
                $this->addRow($rowsByTeacher, $proctor, $schedule, 'Proctor');
            }
        }

        $entries = [];

        foreach ($rowsByTeacher as $teacherId => $bucket) {
            $teacher = $teachers->firstWhere('id', $teacherId);

            if ($teacher === null) {
                continue;
            }

            $exemptDays = [];

            for ($day = 1; $day <= $days; $day++) {
                if (($dayLoads[$teacherId][$day] ?? 0) >= 2) {
                    $exemptDays[] = $day;
                }
            }

            $rows = $bucket['rows'];
            usort($rows, fn (array $a, array $b): int => ($a['day'] <=> $b['day'])
                ?: strnatcasecmp($a['start_time'], $b['start_time'])
                ?: strnatcasecmp($a['subject'], $b['subject']));

            $entries[] = [
                'teacher' => $teacher,
                'rows' => $rows,
                'exempt_days' => $exemptDays,
            ];
        }

        usort($entries, function (array $a, array $b): int {
            return strnatcasecmp((string) $a['teacher']->lastname, (string) $b['teacher']->lastname)
                ?: strnatcasecmp((string) $a['teacher']->firstname, (string) $b['teacher']->firstname)
                ?: strnatcasecmp((string) $a['teacher']->middlename, (string) $b['teacher']->middlename)
                ?: ((int) $a['teacher']->id <=> (int) $b['teacher']->id);
        });

        $reportTeachers = [];

        foreach ($entries as $entry) {
            /** @var Teacher $teacher */
            $teacher = $entry['teacher'];
            $teacherId = (int) $teacher->id;

            $reportTeachers[] = [
                'id' => $teacherId,
                'name' => $this->teacherName($teacher),
                'teaching' => (int) ($loads['teaching'][$teacherId] ?? 0),
                'proctor' => (int) ($loads['proctor'][$teacherId] ?? 0),
                'overall' => (int) ($loads['overall'][$teacherId] ?? 0),
                'teaching_by_day' => $loads['teaching_by_day'][$teacherId] ?? [],
                'proctor_by_day' => $loads['proctor_by_day'][$teacherId] ?? [],
                'overall_by_day' => $loads['overall_by_day'][$teacherId] ?? [],
                'exempt_days' => $entry['exempt_days'],
                'rows' => $entry['rows'],
            ];
        }

        return [
            'title' => (string) $group->title,
            'school_year' => $group->schoolYear?->name,
            'quarter' => $group->quarter?->name,
            'days' => $days,
            'exams_per_day' => max(1, (int) ($group->exams_per_day ?? 1)),
            'max_proctors_per_teacher' => (int) $group->max_proctors_per_teacher,
            'summary' => $this->summary($loads['overall'], $teacherIds),
            'teachers' => $reportTeachers,
        ];
    }

    /**
     * @param  array<int, array{keys: array<string, bool>, rows: array<int, array<string, mixed>>}>  $rowsByTeacher
     */
    private function addRow(array &$rowsByTeacher, Teacher $teacher, Schedule $schedule, string $type): void
    {
        $teacherId = (int) $teacher->id;
        $day = (int) ($schedule->day ?? 1);
        $startTime = (string) $schedule->start_time;
        $endTime = (string) $schedule->end_time;
        $subjectId = (int) $schedule->subject_id;
        $key = $day.'-'.$startTime.'-'.$endTime.'-'.$subjectId.'-'.$type;

        $rowsByTeacher[$teacherId] ??= ['keys' => [], 'rows' => []];

        if (isset($rowsByTeacher[$teacherId]['keys'][$key])) {
            return;
        }

        $rowsByTeacher[$teacherId]['keys'][$key] = true;
        $rowsByTeacher[$teacherId]['rows'][] = [
            'day' => $day,
            'time' => 'Day '.$day.' · '.$this->displayTime($startTime).'–'.$this->displayTime($endTime),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'subject' => (string) ($schedule->subject?->subject_name ?? ''),
            'type' => $type,
        ];
    }

    /**
     * Format a persisted time (HH:MM or HH:MM:SS) as "8:00 AM".
     */
    private function displayTime(string $time): string
    {
        $parts = explode(':', trim($time));

        if (count($parts) < 2) {
            return $time;
        }

        [$hours, $minutes] = $parts;
        $hour = (int) $hours;
        $period = $hour >= 12 ? 'PM' : 'AM';
        $displayHour = $hour % 12 === 0 ? 12 : $hour % 12;

        return $displayHour.':'.$minutes.' '.$period;
    }

    /**
     * @param  array<int, int>  $overall
     * @param  array<int, int>  $teacherIds
     * @return array{min: int, max: int, average: float, spread: int}
     */
    private function summary(array $overall, array $teacherIds): array
    {
        if ($teacherIds === []) {
            return ['min' => 0, 'max' => 0, 'average' => 0.0, 'spread' => 0];
        }

        $values = array_map(fn (int $id): int => (int) ($overall[$id] ?? 0), $teacherIds);
        $min = min($values);
        $max = max($values);

        return [
            'min' => $min,
            'max' => $max,
            'average' => (float) (array_sum($values) / count($values)),
            'spread' => $max - $min,
        ];
    }

    /**
     * Format a teacher name like the table's "Lastname, Firstname M." label.
     */
    private function teacherName(Teacher $teacher): string
    {
        $last = (string) ($teacher->lastname ?? '');
        $first = (string) ($teacher->firstname ?? '');
        $middleName = trim((string) ($teacher->middlename ?? ''));
        $middle = $middleName === '' ? '' : strtoupper(mb_substr($middleName, 0, 1));

        if ($last === '') {
            $name = trim(implode(' ', array_filter([$first, $middleName])));

            return $name !== '' ? $name : '—';
        }

        $base = $first !== '' ? $last.', '.$first : $last;

        return $middle !== '' ? $base.' '.$middle.'.' : $base;
    }
}
