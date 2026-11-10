<?php

use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Services\ScheduleGrid;

test('the grid flags a subject group whose rows share one teacher for rowspan rendering', function () {
    $grade = GradeLevel::factory()->create(['grade_level' => '7']);

    $sectionA = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create(['room_name' => 'Room A'])->id,
        'section_name' => 'Section A',
    ]);
    $sectionB = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create(['room_name' => 'Room B'])->id,
        'section_name' => 'Section B',
    ]);

    $shared = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Shared']);
    $split = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Split']);

    $sharedTeacher = Teacher::factory()->create(['firstname' => 'Ana', 'middlename' => null, 'lastname' => 'One']);
    $splitTeacherA = Teacher::factory()->create(['firstname' => 'Ben', 'middlename' => null, 'lastname' => 'Two']);
    $splitTeacherB = Teacher::factory()->create(['firstname' => 'Cara', 'middlename' => null, 'lastname' => 'Three']);
    $proctor = Teacher::factory()->create();

    foreach ([[$sectionA, $splitTeacherA], [$sectionB, $splitTeacherB]] as [$section, $splitTeacher]) {
        SectionSubjectTeacher::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $shared->id,
            'teacher_id' => $sharedTeacher->id,
        ]);
        SectionSubjectTeacher::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $split->id,
            'teacher_id' => $splitTeacher->id,
        ]);
    }

    $preset = TimePreset::factory()->create();
    $preset->slots()->createMany([
        ['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 0],
        ['start_time' => '09:30:00', 'end_time' => '10:30:00', 'sort_order' => 1],
    ]);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 2,
    ]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([
        $shared->id => ['sort_order' => 0],
        $split->id => ['sort_order' => 1],
    ]);

    foreach ([[$sectionA, $splitTeacherA], [$sectionB, $splitTeacherB]] as [$section, $splitTeacher]) {
        Schedule::factory()->create([
            'schedule_group_id' => $group->id,
            'day' => 1,
            'section_id' => $section->id,
            'subject_id' => $shared->id,
            'subject_teacher_id' => $sharedTeacher->id,
            'proctor_id' => $proctor->id,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
        Schedule::factory()->create([
            'schedule_group_id' => $group->id,
            'day' => 1,
            'section_id' => $section->id,
            'subject_id' => $split->id,
            'subject_teacher_id' => $splitTeacher->id,
            'proctor_id' => $proctor->id,
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
        ]);
    }

    $grid = app(ScheduleGrid::class)->forGroup($group);

    $groups = collect($grid['days'][0]['grades'][0]['subjects'])->keyBy('subject');

    expect($groups['Shared']['uniform_teacher'])->toBe('Ana One');
    expect($groups['Shared']['rows'])->toHaveCount(2);
    expect($groups['Split']['uniform_teacher'])->toBeNull();
    expect($groups['Split']['rows'][0]['subject_teacher'])
        ->not->toBe($groups['Split']['rows'][1]['subject_teacher']);
});
