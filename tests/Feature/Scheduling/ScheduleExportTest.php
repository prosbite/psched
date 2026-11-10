<?php

use App\Enums\ScheduleGroupStatus;
use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Models\User;

function scheduleExportContext(): ScheduleGroup
{
    $grade = GradeLevel::factory()->create(['grade_level' => '7']);

    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create(['room_name' => 'Room 101'])->id,
        'section_name' => 'Section A',
    ]);

    $subject = Subject::factory()->create([
        'grade_level_id' => $grade->id,
        'subject_name' => 'Mathematics',
    ]);

    $teacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    $preset = TimePreset::factory()->create();
    $preset->slots()->create([
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'sort_order' => 0,
    ]);

    $group = ScheduleGroup::factory()->create([
        'title' => 'Midterm Schedule',
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 1,
    ]);

    $group->sections()->attach($section);
    $group->subjects()->attach([$subject->id => ['sort_order' => 0]]);

    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $proctor->id,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    return $group;
}

test('admins can download a schedule group grid as pdf', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $group = scheduleExportContext();

    $response = $this->actingAs($admin)->get(route('schedule-groups.export.pdf', $group));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('midterm-schedule.pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('admins can download a schedule group grid as excel', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $group = scheduleExportContext();

    $response = $this->actingAs($admin)->get(route('schedule-groups.export.excel', $group));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('midterm-schedule.xlsx');
    expect($response->streamedContent())->toStartWith('PK');
});

test('the public schedule export is limited to active groups', function () {
    $group = scheduleExportContext();

    $this->get(route('public.schedules.export.pdf', $group))->assertNotFound();

    $group->update(['status' => ScheduleGroupStatus::Active]);

    $this->get(route('public.schedules.export.pdf', $group))->assertOk();
    $this->get(route('public.schedules.export.excel', $group))->assertOk();
});

test('guests cannot download admin schedule exports', function () {
    $group = scheduleExportContext();

    $this->get(route('schedule-groups.export.pdf', $group))->assertRedirect(route('login'));
    $this->get(route('schedule-groups.export.excel', $group))->assertRedirect(route('login'));
});
