<?php

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

/**
 * @return array{group: ScheduleGroup, sections: array{0: Section, 1: Section}, subject: Subject, teacher: Teacher, proctor: Teacher, siblingProctor: Teacher, freeProctor: Teacher}
 */
function scheduleUpdateContext(): array
{
    $grade = GradeLevel::factory()->create();
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();
    $siblingProctor = Teacher::factory()->create();
    $freeProctor = Teacher::factory()->create();

    $sections = [];

    foreach ([1, 2] as $index) {
        $section = Section::factory()->create([
            'grade_level_id' => $grade->id,
            'room_id' => Room::factory()->create()->id,
        ]);

        SectionSubjectTeacher::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $sections[$index - 1] = $section;
    }

    $preset = TimePreset::factory()->create();
    $preset->slots()->create([
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'sort_order' => 0,
    ]);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'max_proctors_per_teacher' => 2,
    ]);
    $group->sections()->attach($sections);
    $group->subjects()->attach($subject);

    return [
        'group' => $group,
        'sections' => $sections,
        'subject' => $subject,
        'teacher' => $teacher,
        'proctor' => $proctor,
        'siblingProctor' => $siblingProctor,
        'freeProctor' => $freeProctor,
    ];
}

/**
 * @param  array{group: ScheduleGroup, sections: array{0: Section, 1: Section}, subject: Subject, teacher: Teacher, proctor: Teacher, siblingProctor: Teacher, freeProctor: Teacher}  $context
 */
function scheduleUpdateEntry(array $context, int $sectionIndex, Teacher $proctor, string $start = '07:00:00', string $end = '08:00:00', int $day = 3): Schedule
{
    return Schedule::factory()->create([
        'schedule_group_id' => $context['group']->id,
        'day' => $day,
        'section_id' => $context['sections'][$sectionIndex]->id,
        'subject_id' => $context['subject']->id,
        'subject_teacher_id' => $context['teacher']->id,
        'proctor_id' => $proctor->id,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

test('updating only the proctor succeeds when the subject teacher repeats in a sibling section', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    $schedule = scheduleUpdateEntry($context, 0, $context['proctor']);
    scheduleUpdateEntry($context, 1, $context['siblingProctor']);

    $this->actingAs($admin)
        ->put(route('schedule-groups.schedules.update', [$context['group'], $schedule]), [
            'day' => 3,
            'section_id' => $context['sections'][0]->id,
            'subject_id' => $context['subject']->id,
            'proctor_id' => $context['freeProctor']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->proctor_id)->toBe($context['freeProctor']->id);
});

test('storing a second section for the same subject and slot succeeds with the same subject teacher', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    scheduleUpdateEntry($context, 0, $context['proctor']);

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $context['group']), [
            'day' => 3,
            'section_id' => $context['sections'][1]->id,
            'subject_id' => $context['subject']->id,
            'proctor_id' => $context['freeProctor']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasNoErrors();

    expect($context['group']->schedules()->count())->toBe(2);
});

test('a genuinely double-booked proctor is still rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    $schedule = scheduleUpdateEntry($context, 0, $context['siblingProctor']);
    scheduleUpdateEntry($context, 1, $context['proctor']);

    $this->actingAs($admin)
        ->put(route('schedule-groups.schedules.update', [$context['group'], $schedule]), [
            'day' => 3,
            'section_id' => $context['sections'][0]->id,
            'subject_id' => $context['subject']->id,
            'proctor_id' => $context['proctor']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasErrors('proctor_id');

    expect($schedule->fresh()->proctor_id)->toBe($context['siblingProctor']->id);
});

test('selecting the subject teacher as proctor for a non-elective is still rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    $schedule = scheduleUpdateEntry($context, 0, $context['proctor']);

    $this->actingAs($admin)
        ->put(route('schedule-groups.schedules.update', [$context['group'], $schedule]), [
            'day' => 3,
            'section_id' => $context['sections'][0]->id,
            'subject_id' => $context['subject']->id,
            'proctor_id' => $context['teacher']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasErrors('proctor_id');
});

test('updating an elective schedule ignores the submitted proctor and keeps the subject teacher', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    $elective = Subject::factory()->create([
        'grade_level_id' => $context['sections'][0]->grade_level_id,
        'is_elective' => true,
    ]);
    $context['group']->subjects()->attach($elective);

    foreach ($context['sections'] as $section) {
        SectionSubjectTeacher::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $elective->id,
            'teacher_id' => $context['teacher']->id,
        ]);
    }

    $schedule = Schedule::factory()->create([
        'schedule_group_id' => $context['group']->id,
        'day' => 3,
        'section_id' => $context['sections'][0]->id,
        'subject_id' => $elective->id,
        'subject_teacher_id' => $context['teacher']->id,
        'proctor_id' => $context['teacher']->id,
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
    ]);

    Schedule::factory()->create([
        'schedule_group_id' => $context['group']->id,
        'day' => 3,
        'section_id' => $context['sections'][1]->id,
        'subject_id' => $elective->id,
        'subject_teacher_id' => $context['teacher']->id,
        'proctor_id' => $context['teacher']->id,
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
    ]);

    $this->actingAs($admin)
        ->put(route('schedule-groups.schedules.update', [$context['group'], $schedule]), [
            'day' => 3,
            'section_id' => $context['sections'][0]->id,
            'subject_id' => $elective->id,
            'proctor_id' => $context['freeProctor']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->proctor_id)->toBe($context['teacher']->id);
});

test('the per-day proctor cap is still enforced when updating a schedule', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $context = scheduleUpdateContext();

    $schedule = scheduleUpdateEntry($context, 0, $context['siblingProctor']);

    scheduleUpdateEntry($context, 1, $context['proctor'], '09:00:00', '10:00:00');
    scheduleUpdateEntry($context, 1, $context['proctor'], '10:00:00', '11:00:00');

    $this->actingAs($admin)
        ->put(route('schedule-groups.schedules.update', [$context['group'], $schedule]), [
            'day' => 3,
            'section_id' => $context['sections'][0]->id,
            'subject_id' => $context['subject']->id,
            'proctor_id' => $context['proctor']->id,
            'start_time' => '07:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasErrors('proctor_id');
});
