<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Models\ScheduleGroup;
use App\Models\SectionSubjectTeacher;
use Inertia\Inertia;
use Inertia\Response;

class PublicScheduleController extends Controller
{
    /**
     * Display every active schedule group for authenticated-free viewing.
     */
    public function index(): Response
    {
        $groups = ScheduleGroup::query()
            ->where('status', ScheduleGroupStatus::Active->value)
            ->with([
                'timePreset.slots',
                'dayPresets.timePreset.slots',
                'sections.gradeLevel',
                'sections.room',
                'subjects.gradeLevel',
                'schedules.section.gradeLevel',
                'schedules.section.room',
                'schedules.subject',
                'schedules.subjectTeacher',
                'schedules.proctor',
            ])
            ->orderBy('title')
            ->get();

        $sectionIds = $groups->flatMap(fn (ScheduleGroup $group) => $group->sections->pluck('id'))->unique()->values();
        $subjectIds = $groups->flatMap(fn (ScheduleGroup $group) => $group->subjects->pluck('id'))->unique()->values();

        $enrollments = SectionSubjectTeacher::query()
            ->whereIn('section_id', $sectionIds)
            ->whereIn('subject_id', $subjectIds)
            ->get(['section_id', 'subject_id']);

        $groups->each(function (ScheduleGroup $group) use ($enrollments): void {
            $groupSectionIds = $group->sections->pluck('id')->map(fn ($id): int => (int) $id);
            $groupSubjectIds = $group->subjects->pluck('id')->map(fn ($id): int => (int) $id);

            $group->setAttribute(
                'enrollments',
                $enrollments
                    ->filter(fn (SectionSubjectTeacher $enrollment): bool => $groupSectionIds->contains((int) $enrollment->section_id)
                        && $groupSubjectIds->contains((int) $enrollment->subject_id))
                    ->map(fn (SectionSubjectTeacher $enrollment): array => [
                        'section_id' => (int) $enrollment->section_id,
                        'subject_id' => (int) $enrollment->subject_id,
                    ])
                    ->values(),
            );
        });

        return Inertia::render('Public/Schedules', [
            'groups' => $groups,
        ]);
    }
}
