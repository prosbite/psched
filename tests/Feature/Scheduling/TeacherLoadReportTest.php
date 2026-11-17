<?php

use App\Models\GradeLevel;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Services\TeacherLoadReport;

function teacherLoadReportGroup(array $attributes = []): array
{
    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $grade->id]);
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $group = ScheduleGroup::factory()->create([
        'days' => 1,
        'exams_per_day' => 2,
        ...$attributes,
    ]);

    $group->sections()->attach($section);
    $group->subjects()->attach([$subject->id => ['sort_order' => 0]]);

    return [$group, $section, $subject];
}

test('the report dedupes display rows while keeping load counts', function () {
    $group = ScheduleGroup::factory()->create(['days' => 1, 'exams_per_day' => 2]);
    $grade = GradeLevel::factory()->create();
    $sections = Section::factory()->count(2)->create(['grade_level_id' => $grade->id]);
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $group->sections()->attach($sections->pluck('id'));
    $group->subjects()->attach([$subject->id => ['sort_order' => 0]]);

    $teacher = Teacher::factory()->create(['lastname' => 'Abad']);
    $proctor = Teacher::factory()->create(['lastname' => 'Bautista']);

    foreach ($sections as $section) {
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
    }

    $report = app(TeacherLoadReport::class)->forGroup($group);
    $teachers = collect($report['teachers'])->keyBy('id');

    expect($teachers[$teacher->id]['teaching'])->toBe(1)
        ->and($teachers[$teacher->id]['proctor'])->toBe(0)
        ->and($teachers[$teacher->id]['overall'])->toBe(1)
        ->and($teachers[$teacher->id]['rows'])->toHaveCount(1);

    expect($teachers[$proctor->id]['teaching'])->toBe(0)
        ->and($teachers[$proctor->id]['proctor'])->toBe(2)
        ->and($teachers[$proctor->id]['overall'])->toBe(1)
        ->and($teachers[$proctor->id]['rows'])->toHaveCount(1);
});

test('a self-proctored schedule renders one combined row', function () {
    [$group, $section, $subject] = teacherLoadReportGroup();
    $teacher = Teacher::factory()->create(['lastname' => 'Cruz']);

    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $teacher->id,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    $report = app(TeacherLoadReport::class)->forGroup($group);
    $entry = collect($report['teachers'])->firstWhere('id', $teacher->id);

    expect($entry['teaching'])->toBe(1)
        ->and($entry['proctor'])->toBe(1)
        ->and($entry['overall'])->toBe(1)
        ->and($entry['rows'])->toHaveCount(1)
        ->and($entry['rows'][0]['type'])->toBe('Subject Teacher & Proctor')
        ->and($entry['rows'][0]['time'])->toBe('Day 1 · 8:00 AM–9:00 AM');
});

test('the summary counts idle teachers as zero load', function () {
    [$group, $section, $subject] = teacherLoadReportGroup(['days' => 2]);
    $active = Teacher::factory()->create(['lastname' => 'Diaz']);
    Teacher::factory()->create(['lastname' => 'Enriquez']);

    foreach ([1 => '08:00:00', 2 => '10:00:00'] as $day => $start) {
        Schedule::factory()->create([
            'schedule_group_id' => $group->id,
            'day' => $day,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'subject_teacher_id' => $active->id,
            'proctor_id' => null,
            'start_time' => $start,
            'end_time' => $start === '08:00:00' ? '09:00:00' : '11:00:00',
        ]);
    }

    $report = app(TeacherLoadReport::class)->forGroup($group);

    expect($report['summary'])->toBe([
        'min' => 0,
        'max' => 2,
        'average' => 1.0,
        'spread' => 2,
    ]);
});

test('a teacher with two subjects on a day is exempt on that day', function () {
    $group = ScheduleGroup::factory()->create(['days' => 1, 'exams_per_day' => 2]);
    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $grade->id]);

    $preset = TimePreset::factory()->create();
    $preset->slots()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 0]);
    $preset->slots()->create(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 1]);

    $group->update(['time_preset_id' => $preset->id]);

    $first = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Mathematics']);
    $second = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'English']);

    $teacher = Teacher::factory()->create(['lastname' => 'Flores']);

    $group->sections()->attach($section);
    $group->subjects()->attach([
        $first->id => ['sort_order' => 0],
        $second->id => ['sort_order' => 1],
    ]);

    foreach ([$first, $second] as $subject) {
        SectionSubjectTeacher::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $first->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => null,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);
    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $second->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => null,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $report = app(TeacherLoadReport::class)->forGroup($group);
    $entry = collect($report['teachers'])->firstWhere('id', $teacher->id);

    expect($entry['exempt_days'])->toBe([1])
        ->and($entry['rows'])->toHaveCount(2)
        ->and($entry['teaching'])->toBe(2);
});

test('rows and teachers are sorted for the printed table', function () {
    $group = ScheduleGroup::factory()->create(['days' => 2, 'exams_per_day' => 2]);
    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $grade->id]);
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Mathematics']);

    $group->sections()->attach($section);
    $group->subjects()->attach([$subject->id => ['sort_order' => 0]]);

    $zulu = Teacher::factory()->create(['lastname' => 'Zulu']);
    $alpha = Teacher::factory()->create(['lastname' => 'Alpha']);

    foreach ([
        [$zulu, 2, '11:00:00', '12:00:00'],
        [$alpha, 2, '08:00:00', '09:00:00'],
        [$alpha, 1, '10:00:00', '11:00:00'],
        [$alpha, 1, '08:00:00', '09:00:00'],
    ] as [$teacher, $day, $start, $end]) {
        Schedule::factory()->create([
            'schedule_group_id' => $group->id,
            'day' => $day,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'subject_teacher_id' => $teacher->id,
            'proctor_id' => null,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    $report = app(TeacherLoadReport::class)->forGroup($group);

    expect(array_column($report['teachers'], 'id'))->toBe([$alpha->id, $zulu->id]);

    $alphaRows = collect($report['teachers'])->firstWhere('id', $alpha->id)['rows'];

    expect(array_column($alphaRows, 'day'))->toBe([1, 1, 2]);
    expect(array_column($alphaRows, 'start_time'))->toBe(['08:00:00', '10:00:00', '08:00:00']);
});
