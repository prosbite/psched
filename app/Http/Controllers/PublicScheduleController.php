<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Models\ScheduleGroup;
use Inertia\Inertia;
use Inertia\Response;

class PublicScheduleController extends Controller
{
    /**
     * Display every active schedule group for authenticated-free viewing.
     */
    public function index(): Response
    {
        $groups = ScheduleGroup::query()
            ->where('status', ScheduleGroupStatus::Active->value)
            ->with([
                'timePreset.slots',
                'schedules.section.gradeLevel',
                'schedules.section.room',
                'schedules.subject',
                'schedules.subjectTeacher',
                'schedules.proctor',
            ])
            ->orderBy('title')
            ->get();

        return Inertia::render('Public/Schedules', [
            'groups' => $groups,
        ]);
    }
}
