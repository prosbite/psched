<?php

use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Teacher;
use App\Services\ScheduleLoad;

test('load counts distinct teaching and overall slots with per-day breakdowns', function () {
    $group = ScheduleGroup::factory()->create();
    $teacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();
    $idle = Teacher::factory()->create();

    $schedule = [
        'schedule_group_id' => $group->id,
        'subject_teacher_id' => $teacher->id,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ];

    // Two sections sit the same subject at the same time: one teaching slot
    // and one overall slot, but two proctor assignments for the proctor.
    Schedule::factory()->create([...$schedule, 'day' => 1, 'proctor_id' => $proctor->id]);
    Schedule::factory()->create([...$schedule, 'day' => 1, 'proctor_id' => $proctor->id]);

    // A self-proctored elective adds the same slot to teaching and proctor; it
    // must only count once toward the overall total.
    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 2,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $teacher->id,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    $loads = app(ScheduleLoad::class)->fromSchedules(
        $group->schedules()->get(),
        [$teacher->id, $proctor->id, $idle->id],
    );

    expect($loads['teaching'][$teacher->id])->toBe(2);
    expect($loads['proctor'][$teacher->id])->toBe(1);
    expect($loads['overall'][$teacher->id])->toBe(2);

    expect($loads['teaching'][$proctor->id])->toBe(0);
    expect($loads['proctor'][$proctor->id])->toBe(2);
    expect($loads['overall'][$proctor->id])->toBe(1);

    expect($loads['teaching_by_day'][$teacher->id])->toBe([1 => 1, 2 => 1]);
    expect($loads['proctor_by_day'][$teacher->id])->toBe([2 => 1]);
    expect($loads['overall_by_day'][$teacher->id])->toBe([1 => 1, 2 => 1]);

    expect($loads['proctor_by_day'][$proctor->id])->toBe([1 => 2]);
    expect($loads['overall_by_day'][$proctor->id])->toBe([1 => 1]);

    expect($loads['teaching'][$idle->id])->toBe(0);
    expect($loads['proctor'][$idle->id])->toBe(0);
    expect($loads['overall'][$idle->id])->toBe(0);
    expect($loads['teaching_by_day'][$idle->id])->toBe([]);
});

test('the slot key normalizes seconds away', function () {
    expect(ScheduleLoad::slotKey(2, '08:00:00', '09:30:00'))
        ->toBe('2|08:00|09:30');
    expect(ScheduleLoad::slotKey(2, '08:00', '09:30'))
        ->toBe(ScheduleLoad::slotKey(2, '08:00:00', '09:30:00'));
});
