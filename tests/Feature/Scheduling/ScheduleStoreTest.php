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

/**
 * @return array{0: ScheduleGroup, 1: Section, 2: Subject, 3: Teacher, 4: Teacher}
 */
function scheduleStoreContext(): array
{
    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    $preset = TimePreset::factory()->create();
    $preset->slots()->create([
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'sort_order' => 0,
    ]);

    $group = ScheduleGroup::factory()->create(['time_preset_id' => $preset->id]);
    $group->sections()->attach($section);
    $group->subjects()->attach($subject);

    return [$group, $section, $subject, $teacher, $proctor];
}

test('storing a schedule derives the subject teacher from the assignment', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, $subject, $teacher, $proctor] = scheduleStoreContext();

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $group), [
            'day' => 2,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'proctor_id' => $proctor->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ]);

    $schedule = $group->schedules()->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->subject_teacher_id)->toBe($teacher->id);
    expect($schedule->day)->toBe(2);
});

test('storing an elective schedule uses the subject teacher as proctor', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, $subject, $teacher, $proctor] = scheduleStoreContext();

    $elective = Subject::factory()->create([
        'grade_level_id' => $section->grade_level_id,
        'is_elective' => true,
    ]);
    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $elective->id,
        'teacher_id' => $teacher->id,
    ]);
    $group->subjects()->attach($elective);

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $group), [
            'day' => 1,
            'section_id' => $section->id,
            'subject_id' => $elective->id,
            'proctor_id' => $proctor->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ]);

    $schedule = $group->schedules()->where('subject_id', $elective->id)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->subject_teacher_id)->toBe($teacher->id);
    expect($schedule->proctor_id)->toBe($teacher->id);
});

test('storing a schedule for an unmapped pair is rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$group, $section, $subject, $teacher, $proctor] = scheduleStoreContext();

    $unmapped = Subject::factory()->create(['grade_level_id' => $section->grade_level_id]);
    $group->subjects()->attach($unmapped);

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $group), [
            'day' => 1,
            'section_id' => $section->id,
            'subject_id' => $unmapped->id,
            'proctor_id' => $proctor->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasErrors('subject_id');

    expect($group->schedules()->count())->toBe(0);
});
