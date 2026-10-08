<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Http\Requests\ScheduleRequest;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class ScheduleController extends Controller
{
    /**
     * Store a manually overridden schedule in a draft group.
     */
    public function store(ScheduleRequest $request, ScheduleGroup $scheduleGroup): RedirectResponse
    {
        $scheduleGroup->schedules()->create($this->payload($request));

        return back()->with('success', 'Schedule added.');
    }

    /**
     * Update a manually overridden schedule.
     */
    public function update(ScheduleRequest $request, ScheduleGroup $scheduleGroup, Schedule $schedule): RedirectResponse
    {
        abort_unless((int) $schedule->schedule_group_id === (int) $scheduleGroup->id, 404);

        $schedule->update($this->payload($request));

        return back()->with('success', 'Schedule updated.');
    }

    /**
     * Remove a manually overridden schedule.
     */
    public function destroy(ScheduleGroup $scheduleGroup, Schedule $schedule): RedirectResponse
    {
        abort_unless((int) $schedule->schedule_group_id === (int) $scheduleGroup->id, 404);

        if ($scheduleGroup->status !== ScheduleGroupStatus::Draft) {
            return back()->with('error', 'Only schedules in a draft group can be removed.');
        }

        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    /**
     * Build the schedule attributes from a validated request.
     *
     * @return array<string, mixed>
     */
    private function payload(ScheduleRequest $request): array
    {
        return [
            'section_id' => (int) $request->input('section_id'),
            'subject_id' => (int) $request->input('subject_id'),
            'subject_teacher_id' => (int) $request->input('subject_teacher_id'),
            'proctor_id' => (int) $request->input('proctor_id'),
            'start_time' => Carbon::createFromFormat('H:i', (string) $request->input('start_time'))->format('H:i:s'),
            'end_time' => Carbon::createFromFormat('H:i', (string) $request->input('end_time'))->format('H:i:s'),
        ];
    }
}
