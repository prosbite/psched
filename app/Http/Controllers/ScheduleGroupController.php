<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Http\Requests\ScheduleGroupRequest;
use App\Models\Quarter;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Services\ScheduleGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleGroupController extends Controller
{
    /**
     * Display a listing of the schedule groups.
     */
    public function index(): Response
    {
        return Inertia::render('ScheduleGroups/Index', [
            'groups' => ScheduleGroup::with(['timePreset', 'schoolYear', 'quarter'])
                ->withCount(['sections', 'subjects', 'schedules'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a schedule group.
     */
    public function create(): Response
    {
        return Inertia::render('ScheduleGroups/Form', [
            'group' => null,
            'timePresets' => TimePreset::withCount('slots')->orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'quarters' => Quarter::orderBy('start_date')->get(),
        ]);
    }

    /**
     * Store a newly created schedule group.
     */
    public function store(ScheduleGroupRequest $request): RedirectResponse
    {
        $group = ScheduleGroup::create([
            ...$request->validated(),
            'status' => ScheduleGroupStatus::Draft,
        ]);

        return redirect()
            ->route('schedule-groups.show', $group)
            ->with('success', 'Schedule group created. Configure the setup, then generate.');
    }

    /**
     * Display the given schedule group.
     */
    public function show(ScheduleGroup $scheduleGroup): Response
    {
        $scheduleGroup->load([
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
            'unplaced.section',
            'unplaced.subject',
        ]);

        $assignments = SectionSubjectTeacher::query()
            ->whereIn('section_id', $scheduleGroup->sections->pluck('id'))
            ->whereIn('subject_id', $scheduleGroup->subjects->pluck('id'))
            ->with('teacher')
            ->get();

        $scheduledSubjectIds = $scheduleGroup->subjects
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Inertia::render('ScheduleGroups/Show', [
            'group' => $scheduleGroup,
            'allSections' => Section::with(['gradeLevel', 'room'])->orderBy('section_name')->get(),
            'allSubjects' => Subject::with('gradeLevel')->orderBy('subject_name')->get(),
            'allPresets' => TimePreset::withCount('slots')->orderBy('name')->get(),
            'teachers' => Teacher::orderBy('lastname')->get(),
            'proctorLoads' => $scheduleGroup->schedules
                ->whereNotNull('proctor_id')
                ->countBy('proctor_id'),
            'assignments' => $assignments
                ->whereNotNull('teacher_id')
                ->map(fn (SectionSubjectTeacher $assignment): array => [
                    'section_id' => (int) $assignment->section_id,
                    'subject_id' => (int) $assignment->subject_id,
                    'teacher' => $assignment->teacher,
                ])
                ->values(),
            'enrollments' => $assignments
                ->map(fn (SectionSubjectTeacher $assignment): array => [
                    'section_id' => (int) $assignment->section_id,
                    'subject_id' => (int) $assignment->subject_id,
                ])
                ->values(),
            'subjectTeacherLoads' => $assignments
                ->whereNotNull('teacher_id')
                ->filter(fn (SectionSubjectTeacher $assignment): bool => in_array((int) $assignment->subject_id, $scheduledSubjectIds, true))
                ->groupBy('teacher_id')
                ->map(fn ($rows): int => $rows->pluck('subject_id')->unique()->count())
                ->all(),
        ]);
    }

    /**
     * Show the form for editing the given schedule group.
     */
    public function edit(ScheduleGroup $scheduleGroup): Response
    {
        return Inertia::render('ScheduleGroups/Form', [
            'group' => $scheduleGroup,
            'timePresets' => TimePreset::withCount('slots')->orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('name')->get(),
            'quarters' => Quarter::orderBy('start_date')->get(),
        ]);
    }

    /**
     * Update the given schedule group.
     */
    public function update(ScheduleGroupRequest $request, ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $scheduleGroup->update($request->validated());

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group updated.');
    }

    /**
     * Remove the given schedule group.
     */
    public function destroy(ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $scheduleGroup->delete();

        return redirect()->route('schedule-groups.index')->with('success', 'Schedule group deleted.');
    }

    /**
     * Persist the setup (preset, sections, subjects) for a group.
     */
    public function setup(Request $request, ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $validated = $request->validate([
            'time_preset_id' => ['nullable', 'integer', Rule::exists('time_presets', 'id')],
            'days' => ['required', 'integer', 'between:1,3'],
            'exams_per_day' => ['required', 'integer', 'between:1,10'],
            'sections' => ['array'],
            'sections.*' => ['integer', Rule::exists('sections', 'id')],
            'subjects' => ['array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')],
        ]);

        $scheduleGroup->update([
            'time_preset_id' => $validated['time_preset_id'] ?? null,
            'days' => (int) $validated['days'],
            'exams_per_day' => (int) $validated['exams_per_day'],
        ]);

        $scheduleGroup->sections()->sync($validated['sections'] ?? []);

        $subjectIds = array_map('intval', $validated['subjects'] ?? []);
        $gradeBySubject = Subject::query()
            ->whereIn('id', $subjectIds)
            ->pluck('grade_level_id', 'id');

        $orders = [];
        $sync = [];

        foreach ($subjectIds as $subjectId) {
            $gradeId = (int) ($gradeBySubject[$subjectId] ?? 0);
            $order = $orders[$gradeId] ?? 0;

            $sync[$subjectId] = ['sort_order' => $order];
            $orders[$gradeId] = $order + 1;
        }

        $scheduleGroup->subjects()->sync($sync);

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group setup saved.');
    }

    /**
     * Generate the schedule grid for a draft group.
     */
    public function generate(Request $request, ScheduleGroup $scheduleGroup, ScheduleGenerator $generator): RedirectResponse
    {
        if ($scheduleGroup->status !== ScheduleGroupStatus::Draft) {
            return back()->with('error', 'Only draft schedule groups can be generated.');
        }

        $validated = $request->validate([
            'proctors' => ['required', 'array', 'min:1'],
            'proctors.*' => ['integer', Rule::exists('teachers', 'id')],
        ]);

        $report = $generator->generate($scheduleGroup, array_map('intval', $validated['proctors']));

        DB::transaction(function () use ($scheduleGroup, $report): void {
            $scheduleGroup->schedules()->delete();
            $scheduleGroup->unplaced()->delete();

            foreach ($report['placed'] as $entry) {
                $scheduleGroup->schedules()->create(
                    Arr::except($entry, ['slot_id', 'schedule_group_id']),
                );
            }

            foreach ($report['unplaced'] as $entry) {
                $scheduleGroup->unplaced()->create([
                    'day' => $entry['day'],
                    'section_id' => $entry['section_id'],
                    'subject_id' => $entry['subject_id'],
                    'reason' => $entry['reason'],
                    'reason_code' => $entry['reason_code'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                ]);
            }
        });

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', Str::of('Generated :placed schedule(s); :unplaced could not be placed.')
                ->replace(':placed', (string) count($report['placed']))
                ->replace(':unplaced', (string) count($report['unplaced']))
                ->value())
            ->with('generation_report', $report);
    }

    /**
     * Finalize a draft group so it becomes visible on the public page.
     */
    public function finalize(ScheduleGroup $scheduleGroup): RedirectResponse
    {
        if ($scheduleGroup->status !== ScheduleGroupStatus::Draft) {
            return back()->with('error', 'Only draft schedule groups can be finalized.');
        }

        if (! $scheduleGroup->schedules()->exists()) {
            return back()->with('error', 'Generate or add at least one schedule before finalizing.');
        }

        $scheduleGroup->update(['status' => ScheduleGroupStatus::Active]);

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group finalized and published.');
    }

    /**
     * Reopen an active or archived group for editing.
     */
    public function reopen(ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $scheduleGroup->update(['status' => ScheduleGroupStatus::Draft]);

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group reopened as a draft.');
    }

    /**
     * Archive a schedule group, hiding it from the public page.
     */
    public function archive(ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $scheduleGroup->update(['status' => ScheduleGroupStatus::Archived]);

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group archived.');
    }
}
