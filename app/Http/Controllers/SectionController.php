<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    /**
     * Display a listing of the sections.
     */
    public function index(): Response
    {
        return Inertia::render('Sections/Index', [
            'sections' => Section::with(['gradeLevel', 'room'])->orderBy('section_name')->get(),
            'gradeLevels' => GradeLevel::orderBy('grade_level')->get(),
            'rooms' => Room::orderBy('room_name')->get(),
            'subjects' => Subject::with('gradeLevel')->orderBy('subject_name')->get(),
            'teachers' => Teacher::orderBy('lastname')->get(),
            'assignments' => SectionSubjectTeacher::query()
                ->whereNotNull('teacher_id')
                ->get(['section_id', 'subject_id', 'teacher_id'])
                ->map(fn (SectionSubjectTeacher $assignment): array => [
                    'section_id' => (int) $assignment->section_id,
                    'subject_id' => (int) $assignment->subject_id,
                    'teacher_id' => (int) $assignment->teacher_id,
                ]),
        ]);
    }

    /**
     * Store a newly created section.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSection($request);

        Section::create($validated);

        return redirect()->route('sections.index')->with('success', 'Section created.');
    }

    /**
     * Update the given section.
     */
    public function update(Request $request, Section $section): RedirectResponse
    {
        $validated = $this->validateSection($request);

        $section->update($validated);

        return redirect()->route('sections.index')->with('success', 'Section updated.');
    }

    /**
     * Remove the given section.
     */
    public function destroy(Section $section): RedirectResponse
    {
        if ($section->schedules()->exists() || $section->scheduleGroups()->exists()) {
            return back()->with('error', 'This section is used by a schedule group or schedule and cannot be deleted.');
        }

        $section->delete();

        return redirect()->route('sections.index')->with('success', 'Section deleted.');
    }

    /**
     * Upsert the teacher assigned to each subject for the given section.
     */
    public function assignTeachers(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['array'],
            'assignments.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'assignments.*.teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ]);

        $validSubjects = Subject::query()
            ->where('grade_level_id', $section->grade_level_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($validated['assignments'] ?? [] as $row) {
            $subjectId = (int) $row['subject_id'];

            if (! in_array($subjectId, $validSubjects, true)) {
                continue;
            }

            $teacherId = $row['teacher_id'] ?? null;

            if ($teacherId === null) {
                SectionSubjectTeacher::query()
                    ->where('section_id', $section->id)
                    ->where('subject_id', $subjectId)
                    ->delete();

                continue;
            }

            SectionSubjectTeacher::updateOrCreate(
                ['section_id' => $section->id, 'subject_id' => $subjectId],
                ['teacher_id' => (int) $teacherId],
            );
        }

        return back()->with('success', 'Teacher assignments saved.');
    }

    /**
     * Validate the section payload.
     *
     * @return array<string, mixed>
     */
    private function validateSection(Request $request): array
    {
        return $request->validate([
            'section_name' => ['required', 'string', 'max:255'],
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
        ]);
    }
}
