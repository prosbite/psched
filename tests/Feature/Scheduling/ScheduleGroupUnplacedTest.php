<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: ScheduleGroup, 1: Section, 2: Subject, 3: Subject, 4: Teacher, 5: Teacher}
 */
function unplacedContext(): array
{
    $grade = GradeLevel::factory()->create();

    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);

    $covered = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $uncovered = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $covered->id,
        'teacher_id' => $teacher->id,
    ]);

    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $uncovered->id,
        'teacher_id' => null,
    ]);

    $preset = TimePreset::factory()->create();
    $preset->slots()->createMany([
        ['start_time' => '07:00:00', 'end_time' => '08:00:00', 'sort_order' => 0],
        ['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1],
    ]);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([
        $covered->id => ['sort_order' => 0],
        $uncovered->id => ['sort_order' => 1],
    ]);

    return [$group, $section, $covered, $uncovered, $teacher, $proctor];
}

/**
 * @return array{0: ScheduleGroup, 1: Section, 2: Subject}
 */
function unplacedWithRow(): array
{
    [$group, $section, , $uncovered] = unplacedContext();

    $group->unplaced()->create([
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $uncovered->id,
        'reason' => sprintf('No teacher is assigned to "%s" for "%s".', $uncovered->subject_name, $section->section_name),
        'reason_code' => 'missing_teacher',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    return [$group, $section, $uncovered];
}

test('generating persists the unplaced rows for the group', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, , $uncovered, $teacher, $proctor] = unplacedContext();

    $this->actingAs($admin)
        ->post(route('schedule-groups.generate', $group), [
            'proctors' => [$teacher->id, $proctor->id],
        ])
        ->assertRedirect(route('schedule-groups.show', $group));

    $rows = $group->unplaced()->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->subject_id)->toBe($uncovered->id);
    expect($row->section_id)->toBe($section->id);
    expect($row->day)->toBe(1);
    expect($row->reason_code)->toBe('missing_teacher');
    expect($row->start_time)->not->toBeNull();
});

test('regenerating replaces unplaced rows instead of duplicating them', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, , , , $teacher, $proctor] = unplacedContext();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($admin)->post(route('schedule-groups.generate', $group), [
            'proctors' => [$teacher->id, $proctor->id],
        ]);
    }

    expect($group->unplaced()->count())->toBe(1);
});

test('manually adding a schedule clears the matching unplaced row', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, $uncovered] = unplacedWithRow();
    $proctor = Teacher::factory()->create();

    SectionSubjectTeacher::query()
        ->where('section_id', $section->id)
        ->where('subject_id', $uncovered->id)
        ->update(['teacher_id' => Teacher::factory()->create()->id]);

    expect($group->unplaced()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $group), [
            'day' => 1,
            'section_id' => $section->id,
            'subject_id' => $uncovered->id,
            'proctor_id' => $proctor->id,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

    expect($group->unplaced()->count())->toBe(0);
});

test('the show page exposes persisted unplaced rows', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, $uncovered] = unplacedWithRow();

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('schedule-groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScheduleGroups/Show')
            ->has('group.unplaced', 1)
            ->where('group.unplaced.0.section_id', $section->id)
            ->where('group.unplaced.0.subject_id', $uncovered->id)
            ->where('group.unplaced.0.reason_code', 'missing_teacher')
        );
});
