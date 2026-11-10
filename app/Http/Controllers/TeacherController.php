<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    /**
     * Display a listing of the teachers.
     */
    public function index(): Response
    {
        return Inertia::render('Teachers/Index', [
            'teachers' => Teacher::with('user')->orderBy('lastname')->get(),
            'subjects' => Subject::with('gradeLevel')->orderBy('subject_name')->get(),
            'sections' => Section::with('gradeLevel')->orderBy('section_name')->get(),
            'gradeLevels' => GradeLevel::orderBy('grade_level')->get(),
            'assignments' => SectionSubjectTeacher::query()
                ->orderBy('section_id')
                ->orderBy('subject_id')
                ->get(['section_id', 'subject_id', 'teacher_id'])
                ->map(fn (SectionSubjectTeacher $assignment): array => [
                    'section_id' => (int) $assignment->section_id,
                    'subject_id' => (int) $assignment->subject_id,
                    'teacher_id' => $assignment->teacher_id === null ? null : (int) $assignment->teacher_id,
                ])
                ->values(),
        ]);
    }

    /**
     * Replace the subjects the given teacher handles, per section.
     */
    public function assignSubjects(Request $request, Teacher $teacher): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['array'],
            'assignments.*.section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'assignments.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
        ]);

        $rows = $validated['assignments'] ?? [];

        $sectionGrades = Section::query()
            ->whereIn('id', collect($rows)->pluck('section_id')->all())
            ->pluck('grade_level_id', 'id');

        $subjectGrades = Subject::query()
            ->whereIn('id', collect($rows)->pluck('subject_id')->all())
            ->pluck('grade_level_id', 'id');

        $pairs = [];

        foreach ($rows as $row) {
            $sectionId = (int) $row['section_id'];
            $subjectId = (int) $row['subject_id'];

            if (! isset($sectionGrades[$sectionId], $subjectGrades[$subjectId])) {
                continue;
            }

            if ((int) $sectionGrades[$sectionId] !== (int) $subjectGrades[$subjectId]) {
                continue;
            }

            $pairs[$sectionId.'-'.$subjectId] = [$sectionId, $subjectId];
        }

        $existing = SectionSubjectTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->get(['id', 'section_id', 'subject_id']);

        foreach ($existing as $assignment) {
            $key = ((int) $assignment->section_id).'-'.((int) $assignment->subject_id);

            if (! array_key_exists($key, $pairs)) {
                $assignment->delete();
            }
        }

        foreach ($pairs as [$sectionId, $subjectId]) {
            SectionSubjectTeacher::updateOrCreate(
                ['section_id' => $sectionId, 'subject_id' => $subjectId],
                ['teacher_id' => $teacher->id],
            );
        }

        return back()->with('success', 'Subject assignments saved.');
    }

    /**
     * Return the subjects and schedules for the given teacher.
     */
    public function details(Teacher $teacher): JsonResponse
    {
        $assignments = $teacher->sectionSubjectTeachers()
            ->with(['section.gradeLevel', 'subject.gradeLevel'])
            ->get();

        $subjects = $assignments
            ->groupBy('subject_id')
            ->map(function ($group): array {
                $subject = $group->first()->subject;

                $sections = $group
                    ->map(fn (SectionSubjectTeacher $assignment): array => [
                        'id' => (int) $assignment->section->id,
                        'section_name' => $assignment->section->section_name,
                        'grade_level' => $assignment->section->gradeLevel?->grade_level,
                    ])
                    ->sortBy('section_name')
                    ->values()
                    ->all();

                return [
                    'subject_id' => (int) $subject->id,
                    'subject_name' => $subject->subject_name,
                    'grade_level' => $subject->gradeLevel?->grade_level,
                    'sections' => $sections,
                ];
            })
            ->sortBy('subject_name')
            ->values()
            ->all();

        $schedules = Schedule::query()
            ->where(fn ($query) => $query
                ->where('subject_teacher_id', $teacher->id)
                ->orWhere('proctor_id', $teacher->id))
            ->with(['scheduleGroup', 'section.gradeLevel', 'section.room', 'subject'])
            ->get()
            ->map(function (Schedule $schedule) use ($teacher): array {
                $status = $schedule->scheduleGroup->status;

                return [
                    'id' => (int) $schedule->id,
                    'role' => $schedule->proctor_id === $teacher->id ? 'proctor' : 'subject_teacher',
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'section' => [
                        'id' => (int) $schedule->section->id,
                        'section_name' => $schedule->section->section_name,
                        'room' => $schedule->section->room?->room_name,
                    ],
                    'subject' => [
                        'id' => (int) $schedule->subject->id,
                        'subject_name' => $schedule->subject->subject_name,
                    ],
                    'group' => [
                        'id' => (int) $schedule->scheduleGroup->id,
                        'title' => $schedule->scheduleGroup->title,
                        'status' => $status->value,
                        'status_label' => $status->label(),
                    ],
                ];
            })
            ->sort(function (array $a, array $b): int {
                return ($b['group']['id'] <=> $a['group']['id'])
                    ?: strcmp((string) $a['start_time'], (string) $b['start_time'])
                    ?: ($a['section']['id'] <=> $b['section']['id']);
            })
            ->values()
            ->all();

        return response()->json([
            'teacher' => [
                'id' => (int) $teacher->id,
                'firstname' => $teacher->firstname,
                'middlename' => $teacher->middlename,
                'lastname' => $teacher->lastname,
                'email' => $teacher->email,
            ],
            'subjects' => $subjects,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Store a newly created teacher.
     */
    public function store(Request $request): RedirectResponse
    {
        Teacher::create($this->validateTeacher($request));

        return redirect()->route('teachers.index')->with('success', 'Teacher created.');
    }

    /**
     * Update the given teacher.
     */
    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($this->validateTeacher($request));

        return redirect()->route('teachers.index')->with('success', 'Teacher updated.');
    }

    /**
     * Remove the given teacher.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return redirect()->route('teachers.index')->with('success', 'Teacher deleted.');
    }

    /**
     * Validate the teacher payload.
     *
     * @return array<string, mixed>
     */
    private function validateTeacher(Request $request): array
    {
        return $request->validate([
            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
