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
