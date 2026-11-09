<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuarterController extends Controller
{
    /**
     * Display a listing of the quarters.
     */
    public function index(): Response
    {
        return Inertia::render('Quarters/Index', [
            'quarters' => Quarter::withCount('scheduleGroups')
                ->orderBy('start_date')
                ->get(),
        ]);
    }

    /**
     * Store a newly created quarter.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Quarter::create($validated);

        return redirect()->route('quarters.index')->with('success', 'Quarter created.');
    }

    /**
     * Update the given quarter.
     */
    public function update(Request $request, Quarter $quarter): RedirectResponse
    {
        $validated = $request->validate($this->rules($quarter));

        $quarter->update($validated);

        return redirect()->route('quarters.index')->with('success', 'Quarter updated.');
    }

    /**
     * Remove the given quarter.
     */
    public function destroy(Quarter $quarter): RedirectResponse
    {
        if ($quarter->scheduleGroups()->exists()) {
            return back()->with('error', 'This quarter is used by a schedule group and cannot be deleted.');
        }

        $quarter->delete();

        return redirect()->route('quarters.index')->with('success', 'Quarter deleted.');
    }

    /**
     * Validation rules for creating or updating a quarter.
     *
     * @return array<string, mixed>
     */
    private function rules(?Quarter $quarter = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('quarters', 'name')->ignore($quarter?->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
