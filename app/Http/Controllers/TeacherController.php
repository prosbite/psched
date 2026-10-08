<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Teacher;
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
            'teachers' => Teacher::with(['subjects', 'user'])->orderBy('lastname')->get(),
            'subjects' => Subject::with('gradeLevel')->orderBy('subject_name')->get(),
        ]);
    }

    /**
     * Store a newly created teacher.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTeacher($request);
        $subjects = $validated['subjects'] ?? [];
        unset($validated['subjects']);

        $teacher = Teacher::create($validated);
        $teacher->subjects()->sync($subjects);

        return redirect()->route('teachers.index')->with('success', 'Teacher created.');
    }

    /**
     * Update the given teacher.
     */
    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $validated = $this->validateTeacher($request);
        $subjects = $validated['subjects'] ?? [];
        unset($validated['subjects']);

        $teacher->update($validated);
        $teacher->subjects()->sync($subjects);

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
            'subjects' => ['array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')],
        ]);
    }
}
