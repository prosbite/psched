<?php

use App\Enums\SubjectType;
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

test('a core subject can be created from the subjects form', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $grade = GradeLevel::factory()->create();

    $this->actingAs($admin)
        ->post(route('subjects.store'), [
            'subject_name' => 'Robotics',
            'grade_level_id' => $grade->id,
            'type' => SubjectType::Core->value,
        ])
        ->assertRedirect(route('subjects.index'));

    $subject = Subject::query()->where('subject_name', 'Robotics')->first();

    expect($subject)->not->toBeNull();
    expect($subject->type)->toBe(SubjectType::Core);
});

test('a subject type can be changed from the edit form', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $subject = Subject::factory()->create(['type' => SubjectType::Regular]);

    $this->actingAs($admin)
        ->put(route('subjects.update', $subject), [
            'subject_name' => $subject->subject_name,
            'grade_level_id' => $subject->grade_level_id,
            'type' => SubjectType::Core->value,
        ])
        ->assertRedirect(route('subjects.index'));

    expect($subject->fresh()->type)->toBe(SubjectType::Core);

    $this->actingAs($admin)
        ->put(route('subjects.update', $subject), [
            'subject_name' => $subject->subject_name,
            'grade_level_id' => $subject->grade_level_id,
            'type' => SubjectType::Elective->value,
        ])
        ->assertRedirect(route('subjects.index'));

    expect($subject->fresh()->type)->toBe(SubjectType::Elective);
});

/**
 * @return array{subject: Subject, group: ScheduleGroup, otherSubject: Subject, otherSchedule: Schedule}
 */
function subjectCascadeContext(): array
{
    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
    $teacher = Teacher::factory()->create();

    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $otherSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);

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

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section->id);
    $group->subjects()->attach($subject->id);

    Schedule::factory()->create([
        'schedule_group_id' => $group->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $teacher->id,
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
    ]);

    $group->unplaced()->create([
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'reason' => 'No slot available.',
        'reason_code' => 'no_slot',
        'start_time' => null,
        'end_time' => null,
    ]);

    $otherGroup = ScheduleGroup::factory()->create(['days' => 1, 'exams_per_day' => 3]);
    $otherSchedule = Schedule::factory()->create([
        'schedule_group_id' => $otherGroup->id,
        'day' => 1,
        'section_id' => $section->id,
        'subject_id' => $otherSubject->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $teacher->id,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    return compact('subject', 'group', 'otherSubject', 'otherSchedule');
}

test('deleting a subject cascades its schedules, unplaced rows, group links and teacher assignments', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    ['subject' => $subject, 'group' => $group, 'otherSubject' => $otherSubject, 'otherSchedule' => $otherSchedule] = subjectCascadeContext();

    $this->actingAs($admin)
        ->delete(route('subjects.destroy', $subject))
        ->assertRedirect(route('subjects.index'));

    expect(Subject::query()->whereKey($subject->id)->exists())->toBeFalse();

    $this->assertDatabaseMissing('schedule_group_subjects', ['subject_id' => $subject->id]);
    $this->assertDatabaseMissing('section_subject_teacher', ['subject_id' => $subject->id]);
    $this->assertDatabaseMissing('schedules', ['subject_id' => $subject->id]);
    $this->assertDatabaseMissing('schedule_group_unplaced', ['subject_id' => $subject->id]);

    $this->assertDatabaseHas('schedule_groups', ['id' => $group->id]);

    expect(Subject::query()->whereKey($otherSubject->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('schedules', ['id' => $otherSchedule->id]);
});

test('deleting a subject used only by a schedule group removes it from the group', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $grade = GradeLevel::factory()->create();
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $group = ScheduleGroup::factory()->create();
    $group->subjects()->attach($subject->id);

    $this->actingAs($admin)
        ->delete(route('subjects.destroy', $subject))
        ->assertRedirect(route('subjects.index'));

    expect(Subject::query()->whereKey($subject->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('schedule_group_subjects', ['subject_id' => $subject->id]);
    $this->assertDatabaseHas('schedule_groups', ['id' => $group->id]);
});
