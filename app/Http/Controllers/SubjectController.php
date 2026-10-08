<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Subject;
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
            'subjects' => Subject::with('gradeLevel')->withCount('teachers')->orderBy('subject_name')->get(),
            'gradeLevels' => GradeLevel::orderBy('grade_level')->get(),
        ]);
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
