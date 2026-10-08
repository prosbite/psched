<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimePresetRequest;
use App\Models\TimePreset;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimePresetController extends Controller
{
    /**
     * Display a listing of the time presets.
     */
    public function index(): Response
    {
        return Inertia::render('TimePresets/Index', [
            'timePresets' => TimePreset::withCount('slots')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a time preset.
     */
    public function create(): Response
    {
        return Inertia::render('TimePresets/Form', [
            'timePreset' => null,
            'slots' => [],
        ]);
    }

    /**
     * Store a newly created time preset.
     */
    public function store(TimePresetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $preset = TimePreset::create(['name' => $request->validated('name')]);

            $this->syncSlots($preset, $request->validated('slots'));
        });

        return redirect()->route('time-presets.index')->with('success', 'Time preset created.');
    }

    /**
     * Show the form for editing the given time preset.
     */
    public function edit(TimePreset $timePreset): Response
    {
        $timePreset->load('slots');

        return Inertia::render('TimePresets/Form', [
            'timePreset' => $timePreset,
            'slots' => $timePreset->slots->map(fn ($slot): array => [
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'sort_order' => $slot->sort_order,
            ])->values(),
        ]);
    }

    /**
     * Update the given time preset.
     */
    public function update(TimePresetRequest $request, TimePreset $timePreset): RedirectResponse
    {
        DB::transaction(function () use ($request, $timePreset): void {
            $timePreset->update(['name' => $request->validated('name')]);
            $timePreset->slots()->delete();

            $this->syncSlots($timePreset, $request->validated('slots'));
        });

        return redirect()->route('time-presets.index')->with('success', 'Time preset updated.');
    }

    /**
     * Remove the given time preset.
     */
    public function destroy(TimePreset $timePreset): RedirectResponse
    {
        $timePreset->delete();

        return redirect()->route('time-presets.index')->with('success', 'Time preset deleted.');
    }

    /**
     * Replace the preset slots with the provided payload.
     *
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function syncSlots(TimePreset $preset, array $slots): void
    {
        foreach (array_values($slots) as $index => $slot) {
            $preset->slots()->create([
                'start_time' => Carbon::createFromFormat('H:i', $slot['start_time'])->format('H:i:s'),
                'end_time' => Carbon::createFromFormat('H:i', $slot['end_time'])->format('H:i:s'),
                'sort_order' => $slot['sort_order'] ?? $index,
            ]);
        }
    }
}
