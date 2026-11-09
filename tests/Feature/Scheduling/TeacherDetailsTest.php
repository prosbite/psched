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
use App\Models\User;

/**
 * @return array{0: Teacher, 1: Schedule, 2: Schedule}
 */
function teacherDetailsContext(): array
{
    $grade = GradeLevel::factory()->create(['grade_level' => 'Grade 7']);

    $sectionA = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create(['room_name' => 'Room 101'])->id,
        'section_name' => 'Section A',
    ]);
    $sectionB = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create(['room_name' => 'Room 102'])->id,
        'section_name' => 'Section B',
    ]);

    $math = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Mathematics']);
    $english = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'English']);

    $teacher = Teacher::factory()->create(['firstname' => 'Jane', 'lastname' => 'Doe']);
    $otherTeacher = Teacher::factory()->create();

    SectionSubjectTeacher::factory()->create(['section_id' => $sectionA->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id]);
    SectionSubjectTeacher::factory()->create(['section_id' => $sectionB->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id]);
    SectionSubjectTeacher::factory()->create(['section_id' => $sectionA->id, 'subject_id' => $english->id, 'teacher_id' => $teacher->id]);

    $activeGroup = ScheduleGroup::factory()->create(['status' => ScheduleGroupStatus::Active]);
    $draftGroup = ScheduleGroup::factory()->create(['status' => ScheduleGroupStatus::Draft]);

    $teachRow = Schedule::factory()->create([
        'schedule_group_id' => $activeGroup->id,
        'section_id' => $sectionA->id,
        'subject_id' => $math->id,
        'subject_teacher_id' => $teacher->id,
        'proctor_id' => $otherTeacher->id,
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
    ]);

    $proctorRow = Schedule::factory()->create([
        'schedule_group_id' => $draftGroup->id,
        'section_id' => $sectionB->id,
        'subject_id' => $english->id,
        'subject_teacher_id' => $otherTeacher->id,
        'proctor_id' => $teacher->id,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    Schedule::factory()->create([
        'schedule_group_id' => $activeGroup->id,
        'subject_teacher_id' => $otherTeacher->id,
        'proctor_id' => Teacher::factory()->create()->id,
    ]);

    return [$teacher, $teachRow, $proctorRow];
}

test('details returns subjects grouped by subject and the teacher schedules with roles', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$teacher, $teachRow, $proctorRow] = teacherDetailsContext();

    $response = $this->actingAs($admin)->getJson(route('teachers.details', $teacher));

    $response->assertOk()
        ->assertJsonPath('teacher.id', $teacher->id)
        ->assertJsonPath('teacher.firstname', 'Jane')
        ->assertJsonPath('teacher.lastname', 'Doe')
        ->assertJsonPath('teacher.email', $teacher->email);

    $subjects = $response->json('subjects');

    expect($subjects)->toHaveCount(2);
    expect($subjects[0]['subject_name'])->toBe('English');
    expect($subjects[0]['grade_level'])->toBe('Grade 7');
    expect($subjects[0]['sections'])->toHaveCount(1);
    expect($subjects[0]['sections'][0]['section_name'])->toBe('Section A');
    expect($subjects[1]['subject_name'])->toBe('Mathematics');
    expect(array_column($subjects[1]['sections'], 'section_name'))->toBe(['Section A', 'Section B']);

    $schedules = $response->json('schedules');

    expect($schedules)->toHaveCount(2);
    expect(array_column($schedules, 'id'))->toBe([$proctorRow->id, $teachRow->id]);

    expect($schedules[0]['role'])->toBe('proctor');
    expect($schedules[0]['group']['status'])->toBe('draft');
    expect($schedules[0]['group']['status_label'])->toBe('Draft');
    expect($schedules[0]['section']['section_name'])->toBe('Section B');
    expect($schedules[0]['section']['room'])->toBe('Room 102');
    expect($schedules[0]['subject']['subject_name'])->toBe('English');

    expect($schedules[1]['role'])->toBe('subject_teacher');
    expect($schedules[1]['group']['status'])->toBe('active');
    expect($schedules[1]['group']['status_label'])->toBe('Active');
    expect($schedules[1]['section']['room'])->toBe('Room 101');
    expect($schedules[1]['subject']['subject_name'])->toBe('Mathematics');
});

test('details returns empty collections for a teacher with no assignments', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $teacher = Teacher::factory()->create();

    $response = $this->actingAs($admin)->getJson(route('teachers.details', $teacher));

    $response->assertOk()
        ->assertJsonPath('subjects', [])
        ->assertJsonPath('schedules', []);
});

test('guests are redirected to login', function () {
    $teacher = Teacher::factory()->create();

    $this->get(route('teachers.details', $teacher))->assertRedirect(route('login'));
});

test('non-admin users are forbidden', function () {
    $teacher = Teacher::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Teacher]);

    $this->actingAs($user)->getJson(route('teachers.details', $teacher))->assertForbidden();
});

test('a missing teacher returns 404', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->getJson(route('teachers.details', 999999))->assertNotFound();
});
