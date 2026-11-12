<?php

use App\Services\ConflictChecker;

beforeEach(function () {
    $this->checker = app(ConflictChecker::class);
});

function conflictEntry(array $overrides = []): array
{
    return array_merge([
        'section_id' => 1,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ], $overrides);
}

test('a section may not have two overlapping schedules', function () {
    $existing = [conflictEntry()];

    $candidate = conflictEntry([
        'subject_teacher_id' => 11,
        'proctor_id' => 21,
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2))->not->toBeEmpty();
});

test('a section may have back to back schedules', function () {
    $existing = [conflictEntry()];

    $candidate = conflictEntry([
        'subject_teacher_id' => 11,
        'proctor_id' => 21,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    expect($this->checker->isConflictFree($existing, $candidate, 2))->toBeTrue();
});

test('a teacher may not have overlapping subject teacher roles', function () {
    $existing = [conflictEntry()];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_teacher_id' => 10,
        'proctor_id' => 22,
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2))->not->toBeEmpty();
});

test('a teacher conflict is detected across mixed roles', function () {
    $existing = [conflictEntry()];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_teacher_id' => 20,
        'proctor_id' => 30,
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2))->not->toBeEmpty();
});

test('the proctor may not be the subject teacher', function () {
    $candidate = conflictEntry([
        'subject_teacher_id' => 10,
        'proctor_id' => 10,
    ]);

    expect($this->checker->conflicts([], $candidate, 2))->not->toBeEmpty();
});

test('a teacher may not exceed the proctor cap', function () {
    $existing = [
        conflictEntry(['proctor_id' => 20, 'start_time' => '07:00:00', 'end_time' => '08:00:00']),
        conflictEntry(['section_id' => 2, 'proctor_id' => 20, 'start_time' => '09:00:00', 'end_time' => '10:00:00']),
    ];

    $candidate = conflictEntry([
        'section_id' => 3,
        'subject_teacher_id' => 11,
        'proctor_id' => 20,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2))->not->toBeEmpty();
    expect($this->checker->isConflictFree($existing, $candidate, 3))->toBeTrue();
});

test('overlapping exams on different days do not conflict', function () {
    $existing = [conflictEntry(['day' => 1])];

    $candidate = conflictEntry(['day' => 2]);

    expect($this->checker->isConflictFree($existing, $candidate, 2))->toBeTrue();
});

test('overlapping exams on the same day still conflict', function () {
    $existing = [conflictEntry(['day' => 2])];

    $candidate = conflictEntry(['day' => 2]);

    expect($this->checker->conflicts($existing, $candidate, 2))->not->toBeEmpty();
});

test('the proctor cap is scoped to a single day', function () {
    $existing = [
        conflictEntry(['day' => 1, 'proctor_id' => 20, 'start_time' => '07:00:00', 'end_time' => '08:00:00']),
        conflictEntry(['day' => 2, 'section_id' => 2, 'proctor_id' => 20, 'start_time' => '09:00:00', 'end_time' => '10:00:00']),
    ];

    $candidate = conflictEntry([
        'day' => 3,
        'section_id' => 3,
        'subject_teacher_id' => 11,
        'proctor_id' => 20,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    expect($this->checker->isConflictFree($existing, $candidate, 1))->toBeTrue();
});

test('a repeated subject teacher across sections is ignored when the overlap check is disabled', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_teacher_id' => 10,
        'proctor_id' => 21,
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2, false))->toBe([]);
});

test('a genuinely overlapped proctor is still rejected when the subject teacher check is disabled', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_teacher_id' => 11,
        'proctor_id' => 20,
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2, false))->not->toBeEmpty();
});

test('the same exam may repeat its subject teacher across sections when the subject ids match', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 21,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $candidate))->toBeFalse();
});

test('a non-elective exam may not share a proctor across sections of the same subject', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $candidate))->toBeTrue();
});

test('a teacher may not be the subject teacher of two different subjects at the same time', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_id' => 6,
        'subject_teacher_id' => 10,
        'proctor_id' => 21,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $candidate))->toBeTrue();
});

test('a candidate proctor who is teaching another subject at the same time conflicts', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 20,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_id' => 6,
        'subject_teacher_id' => 30,
        'proctor_id' => 10,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $candidate))->toBeTrue();
});

test('a self-proctored elective may repeat across sections of the same subject', function () {
    $existing = [conflictEntry([
        'section_id' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 10,
    ])];

    $candidate = conflictEntry([
        'section_id' => 2,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
        'proctor_id' => 10,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $candidate))->toBeFalse();
});

test('non-overlapping or cross-day entries never conflict a teacher', function () {
    $existing = [conflictEntry([
        'day' => 1,
        'subject_id' => 5,
        'subject_teacher_id' => 10,
    ])];

    $backToBack = conflictEntry([
        'day' => 1,
        'section_id' => 2,
        'subject_id' => 6,
        'subject_teacher_id' => 10,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);

    $nextDay = conflictEntry([
        'day' => 2,
        'section_id' => 2,
        'subject_id' => 6,
        'subject_teacher_id' => 10,
    ]);

    expect($this->checker->hasConflictingTeacher($existing, $backToBack))->toBeFalse();
    expect($this->checker->hasConflictingTeacher($existing, $nextDay))->toBeFalse();
});

test('the proctor cap is still enforced when the subject teacher check is disabled', function () {
    $existing = [
        conflictEntry(['section_id' => 1, 'proctor_id' => 20, 'start_time' => '07:00:00', 'end_time' => '08:00:00']),
        conflictEntry(['section_id' => 2, 'proctor_id' => 20, 'start_time' => '09:00:00', 'end_time' => '10:00:00']),
    ];

    $candidate = conflictEntry([
        'section_id' => 3,
        'subject_teacher_id' => 11,
        'proctor_id' => 20,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    expect($this->checker->conflicts($existing, $candidate, 2, false))->not->toBeEmpty();
});
