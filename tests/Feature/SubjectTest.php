<?php

use App\Enums\SubjectType;
use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Subject;
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
