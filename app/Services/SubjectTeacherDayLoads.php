<?php

namespace App\Services;

use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use Illuminate\Support\Collection;

/**
 * Counts the distinct subjects each teacher handles on each day.
 *
 * Mirrors the generator's per-day proctoring exemption: two or more distinct
 * subjects on a day exempt a teacher for that day only.
 */
class SubjectTeacherDayLoads
{
    /**
     * @param  array<int, array<int, array<int, array{subject_id: int, period: int|null}>>>  $subjectPlan
     * @param  Collection<int, SectionSubjectTeacher>  $assignments
     * @return array<int, array<int, int>>
     */
    public function compute(array $subjectPlan, ScheduleGroup $scheduleGroup, Collection $assignments): array
    {
        $sectionsByGrade = $scheduleGroup->sections->groupBy(fn (Section $section): int => (int) $section->grade_level_id);
        $teacherByPair = $assignments
            ->whereNotNull('teacher_id')
            ->mapWithKeys(fn (SectionSubjectTeacher $assignment): array => [
                ((int) $assignment->section_id).'-'.((int) $assignment->subject_id) => (int) $assignment->teacher_id,
            ]);

        $subjectsByTeacherByDay = [];

        foreach ($subjectPlan as $day => $grades) {
            foreach ($grades as $gradeId => $entries) {
                $gradeSections = $sectionsByGrade->get((int) $gradeId, collect());

                foreach ($entries as $entry) {
                    if ($entry['period'] === null) {
                        continue;
                    }

                    $subjectId = (int) $entry['subject_id'];

                    foreach ($gradeSections as $section) {
                        $teacherId = $teacherByPair->get(((int) $section->id).'-'.$subjectId);

                        if ($teacherId === null) {
                            continue;
                        }

                        $subjectsByTeacherByDay[(int) $teacherId][(int) $day][$subjectId] = true;
                    }
                }
            }
        }

        $loads = [];

        foreach ($subjectsByTeacherByDay as $teacherId => $days) {
            foreach ($days as $day => $subjectIds) {
                $loads[$teacherId][$day] = count($subjectIds);
            }

            ksort($loads[$teacherId]);
        }

        return $loads;
    }
}
