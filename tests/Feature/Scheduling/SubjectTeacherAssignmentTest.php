<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

/**
 * @return array{sectionA: Section, sectionB: Section, otherSection: Section, math: Subject, english: Subject, otherSubject: Subject, teacher: Teacher, otherTeacher: Teacher}
 */
function subjectTeacherAssignmentContext(): array
{
    $grade = GradeLevel::factory()->create(['grade_level' => '7']);
    $otherGrade = GradeLevel::factory()->create(['grade_level' => '8']);

    $sectionA = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
    $sectionB = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
    $otherSection = Section::factory()->create([
        'grade_level_id' => $otherGrade->id,
        'room_id' => Room::factory()->create()->id,
    ]);

    $math = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'Mathematics']);
    $english = Subject::factory()->create(['grade_level_id' => $grade->id, 'subject_name' => 'English']);
    $otherSubject = Subject::factory()->create(['grade_level_id' => $otherGrade->id, 'subject_name' => 'Science']);

    $teacher = Teacher::factory()->create();
    $otherTeacher = Teacher::factory()->create();

    return compact(
        'sectionA',
        'sectionB',
        'otherSection',
        'math',
        'english',
        'otherSubject',
        'teacher',
        'otherTeacher',
    );
}

test('assigning subjects from the teachers page replaces that teacher assignments', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [
        'sectionA' => $sectionA,
        'sectionB' => $sectionB,
        'otherSection' => $otherSection,
        'math' => $math,
        'english' => $english,
        'otherSubject' => $otherSubject,
        'teacher' => $teacher,
    ] = subjectTeacherAssignmentContext();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $sectionA->id,
        'subject_id' => $english->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($admin)
        ->put(route('teachers.assignments.update', $teacher), [
            'assignments' => [
                ['section_id' => $sectionA->id, 'subject_id' => $math->id],
                ['section_id' => $sectionB->id, 'subject_id' => $math->id],
                ['section_id' => $otherSection->id, 'subject_id' => $otherSubject->id],
                ['section_id' => $sectionA->id, 'subject_id' => $otherSubject->id],
            ],
        ])
        ->assertRedirect();

    $rows = SectionSubjectTeacher::where('teacher_id', $teacher->id)->get();

    expect($rows)->toHaveCount(3);
    expect($rows->pluck('subject_id')->unique()->sort()->values()->all())
        ->toBe(collect([$math->id, $otherSubject->id])->sort()->values()->all());

    expect(SectionSubjectTeacher::where('section_id', $sectionA->id)->where('subject_id', $english->id)->exists())->toBeFalse();
    expect(SectionSubjectTeacher::where('section_id', $sectionA->id)->where('subject_id', $otherSubject->id)->exists())->toBeFalse();
});

test('assigning subjects from the teachers page reassigns an existing section subject pair', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [
        'sectionA' => $sectionA,
        'math' => $math,
        'teacher' => $teacher,
        'otherTeacher' => $otherTeacher,
    ] = subjectTeacherAssignmentContext();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $sectionA->id,
        'subject_id' => $math->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $this->actingAs($admin)
        ->put(route('teachers.assignments.update', $teacher), [
            'assignments' => [
                ['section_id' => $sectionA->id, 'subject_id' => $math->id],
            ],
        ])
        ->assertRedirect();

    expect(SectionSubjectTeacher::where('section_id', $sectionA->id)->where('subject_id', $math->id)->value('teacher_id'))
        ->toBe($teacher->id);
});

test('assigning teachers from the subjects page sets section teachers', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [
        'sectionA' => $sectionA,
        'sectionB' => $sectionB,
        'otherSection' => $otherSection,
        'math' => $math,
        'teacher' => $teacher,
        'otherTeacher' => $otherTeacher,
    ] = subjectTeacherAssignmentContext();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $sectionA->id,
        'subject_id' => $math->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $this->actingAs($admin)
        ->put(route('subjects.assignments.update', $math), [
            'assignments' => [
                ['section_id' => $sectionA->id, 'teacher_id' => $teacher->id],
                ['section_id' => $sectionB->id, 'teacher_id' => $teacher->id],
                ['section_id' => $otherSection->id, 'teacher_id' => $teacher->id],
            ],
        ])
        ->assertRedirect();

    expect(SectionSubjectTeacher::where('subject_id', $math->id)->count())->toBe(2);
    expect(SectionSubjectTeacher::where('section_id', $sectionA->id)->where('subject_id', $math->id)->value('teacher_id'))
        ->toBe($teacher->id);
    expect(SectionSubjectTeacher::where('section_id', $otherSection->id)->where('subject_id', $math->id)->exists())
        ->toBeFalse();
});

test('assigning teachers from the subjects page can clear a section teacher', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [
        'sectionA' => $sectionA,
        'sectionB' => $sectionB,
        'math' => $math,
        'teacher' => $teacher,
    ] = subjectTeacherAssignmentContext();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $sectionA->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);
    SectionSubjectTeacher::factory()->create([
        'section_id' => $sectionB->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($admin)
        ->put(route('subjects.assignments.update', $math), [
            'assignments' => [
                ['section_id' => $sectionA->id, 'teacher_id' => null],
                ['section_id' => $sectionB->id, 'teacher_id' => $teacher->id],
            ],
        ])
        ->assertRedirect();

    expect(SectionSubjectTeacher::where('subject_id', $math->id)->where('section_id', $sectionA->id)->exists())
        ->toBeFalse();
    expect(SectionSubjectTeacher::where('subject_id', $math->id)->where('section_id', $sectionB->id)->exists())
        ->toBeTrue();
});

test('guests cannot assign subject teachers', function () {
    [
        'sectionA' => $sectionA,
        'math' => $math,
        'teacher' => $teacher,
    ] = subjectTeacherAssignmentContext();

    $this->put(route('teachers.assignments.update', $teacher), [
        'assignments' => [['section_id' => $sectionA->id, 'subject_id' => $math->id]],
    ])->assertRedirect(route('login'));

    $this->put(route('subjects.assignments.update', $math), [
        'assignments' => [['section_id' => $sectionA->id, 'teacher_id' => $teacher->id]],
    ])->assertRedirect(route('login'));
});
