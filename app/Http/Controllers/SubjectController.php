<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of the subjects.
     */
    public function index(): Response
    {
        return Inertia::render('Subjects/Index', [
            'subjects' => Subject::with('gradeLevel')->withCount('sectionSubjectTeachers')->orderBy('subject_name')->get(),
            'gradeLevels' => GradeLevel::orderBy('grade_level')->get(),
            'sections' => Section::with('gradeLevel')->orderBy('section_name')->get(),
            'teachers' => Teacher::orderBy('lastname')->get(),
            'assignments' => SectionSubjectTeacher::query()
                ->whereNotNull('teacher_id')
                ->orderBy('section_id')
                ->get(['section_id', 'subject_id', 'teacher_id'])
                ->map(fn (SectionSubjectTeacher $assignment): array => [
                    'section_id' => (int) $assignment->section_id,
                    'subject_id' => (int) $assignment->subject_id,
                    'teacher_id' => (int) $assignment->teacher_id,
                ])
                ->values(),
        ]);
    }

    /**
     * Assign a teacher for each section sitting the given subject.
     */
    public function assignTeachers(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['array'],
            'assignments.*.section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'assignments.*.teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ]);

        $validSectionIds = Section::query()
            ->where('grade_level_id', $subject->grade_level_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['assignments'] ?? [] as $row) {
            $sectionId = (int) $row['section_id'];

            if (! in_array($sectionId, $validSectionIds, true)) {
                continue;
            }

            $teacherId = $row['teacher_id'] ?? null;

            if ($teacherId === null) {
                SectionSubjectTeacher::query()
                    ->where('section_id', $sectionId)
                    ->where('subject_id', $subject->id)
                    ->delete();

                continue;
            }

            SectionSubjectTeacher::updateOrCreate(
                ['section_id' => $sectionId, 'subject_id' => $subject->id],
                ['teacher_id' => (int) $teacherId],
            );
        }

        return back()->with('success', 'Teacher assignments saved.');
    }

    /**
     * Store a newly created subject.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_name' => ['required', 'string', 'max:255'],
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],
        ]);

        Subject::create($validated);

        return redirect()->route('subjects.index')->with('success', 'Subject created.');
    }

    /**
     * Update the given subject.
     */
    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'subject_name' => ['required', 'string', 'max:255'],
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],
        ]);

        $subject->update($validated);

        return redirect()->route('subjects.index')->with('success', 'Subject updated.');
    }

    /**
     * Remove the given subject.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->schedules()->exists() || $subject->scheduleGroups()->exists()) {
            return back()->with('error', 'This subject is used by a schedule group or schedule and cannot be deleted.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')->with('success', 'Subject deleted.');
    }
}
