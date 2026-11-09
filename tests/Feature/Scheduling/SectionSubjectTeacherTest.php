<?php

use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\QueryException;

test('relationships resolve on a section subject teacher', function () {
    $assignment = SectionSubjectTeacher::factory()->create();

    expect($assignment->section)->toBeInstanceOf(Section::class)
        ->and($assignment->subject)->toBeInstanceOf(Subject::class)
        ->and($assignment->teacher)->toBeInstanceOf(Teacher::class);
});

test('a section can only have one teacher per subject', function () {
    $assignment = SectionSubjectTeacher::factory()->create();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $assignment->section_id,
        'subject_id' => $assignment->subject_id,
    ]);
})->throws(QueryException::class);

test('the teacher relation can be null when the teacher is deleted', function () {
    $assignment = SectionSubjectTeacher::factory()->create();

    $assignment->teacher->delete();

    expect($assignment->fresh()->teacher_id)->toBeNull();
});
