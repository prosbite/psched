<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Http\Requests\ScheduleGroupRequest;
use App\Models\ScheduleGroup;
use App\Models\Section;
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
            'groups' => ScheduleGroup::with('timePreset')
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
            'sections.gradeLevel',
            'sections.room',
            'subjects.gradeLevel',
            'schedules.section.gradeLevel',
            'schedules.section.room',
            'schedules.subject',
            'schedules.subjectTeacher',
            'schedules.proctor',
        ]);

        return Inertia::render('ScheduleGroups/Show', [
            'group' => $scheduleGroup,
            'allSections' => Section::with(['gradeLevel', 'room'])->orderBy('section_name')->get(),
            'allSubjects' => Subject::with('gradeLevel')->orderBy('subject_name')->get(),
            'allPresets' => TimePreset::withCount('slots')->orderBy('name')->get(),
            'teachers' => Teacher::orderBy('lastname')->get(),
            'proctorLoads' => $scheduleGroup->schedules
                ->whereNotNull('proctor_id')
                ->countBy('proctor_id'),
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
            'sections' => ['array'],
            'sections.*' => ['integer', Rule::exists('sections', 'id')],
            'subjects' => ['array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')],
        ]);

        $scheduleGroup->update(['time_preset_id' => $validated['time_preset_id'] ?? null]);
        $scheduleGroup->sections()->sync($validated['sections'] ?? []);
        $scheduleGroup->subjects()->sync($validated['subjects'] ?? []);

        return redirect()
            ->route('schedule-groups.show', $scheduleGroup)
            ->with('success', 'Schedule group setup saved.');
    }

    /**
     * Generate the schedule grid for a draft group.
     */
    public function generate(ScheduleGroup $scheduleGroup, ScheduleGenerator $generator): RedirectResponse
    {
        if ($scheduleGroup->status !== ScheduleGroupStatus::Draft) {
            return back()->with('error', 'Only draft schedule groups can be generated.');
        }

        $report = $generator->generate($scheduleGroup);

        DB::transaction(function () use ($scheduleGroup, $report): void {
            $scheduleGroup->schedules()->delete();

            foreach ($report['placed'] as $entry) {
                $scheduleGroup->schedules()->create(
                    Arr::except($entry, ['slot_id', 'schedule_group_id']),
                );
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
