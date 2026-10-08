<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
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
