<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeLevelController extends Controller
{
    /**
     * Display a listing of the grade levels.
     */
    public function index(): Response
    {
        return Inertia::render('GradeLevels/Index', [
            'gradeLevels' => GradeLevel::withCount(['sections', 'subjects'])
                ->orderBy('grade_level')
                ->get(),
        ]);
    }

    /**
     * Store a newly created grade level.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grade_level' => ['required', 'string', 'max:255'],
        ]);

        GradeLevel::create($validated);

        return redirect()->route('grade-levels.index')->with('success', 'Grade level created.');
    }

    /**
     * Update the given grade level.
     */
    public function update(Request $request, GradeLevel $gradeLevel): RedirectResponse
    {
        $validated = $request->validate([
            'grade_level' => ['required', 'string', 'max:255'],
        ]);

        $gradeLevel->update($validated);

        return redirect()->route('grade-levels.index')->with('success', 'Grade level updated.');
    }

    /**
     * Remove the given grade level.
     */
    public function destroy(GradeLevel $gradeLevel): RedirectResponse
    {
        if ($gradeLevel->sections()->exists() || $gradeLevel->subjects()->exists()) {
            return back()->with('error', 'This grade level still has sections or subjects and cannot be deleted.');
        }

        $gradeLevel->delete();

        return redirect()->route('grade-levels.index')->with('success', 'Grade level deleted.');
    }
}
