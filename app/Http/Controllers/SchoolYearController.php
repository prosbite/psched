<?php

namespace App\Http\Controllers;

use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolYearController extends Controller
{
    /**
     * Display a listing of the school years.
     */
    public function index(): Response
    {
        return Inertia::render('SchoolYears/Index', [
            'schoolYears' => SchoolYear::withCount('scheduleGroups')
                ->orderByDesc('name')
                ->get(),
        ]);
    }

    /**
     * Store a newly created school year.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('school_years', 'name')],
        ]);

        SchoolYear::create($validated);

        return redirect()->route('school-years.index')->with('success', 'School year created.');
    }

    /**
     * Update the given school year.
     */
    public function update(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('school_years', 'name')->ignore($schoolYear->id)],
        ]);

        $schoolYear->update($validated);

        return redirect()->route('school-years.index')->with('success', 'School year updated.');
    }

    /**
     * Remove the given school year.
     */
    public function destroy(SchoolYear $schoolYear): RedirectResponse
    {
        if ($schoolYear->scheduleGroups()->exists()) {
            return back()->with('error', 'This school year is used by a schedule group and cannot be deleted.');
        }

        $schoolYear->delete();

        return redirect()->route('school-years.index')->with('success', 'School year deleted.');
    }
}
