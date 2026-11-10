<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\User;

test('an elective subject can be created from the subjects form', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $grade = GradeLevel::factory()->create();

    $this->actingAs($admin)
        ->post(route('subjects.store'), [
            'subject_name' => 'Robotics',
            'grade_level_id' => $grade->id,
            'is_elective' => true,
        ])
        ->assertRedirect(route('subjects.index'));

    $subject = Subject::query()->where('subject_name', 'Robotics')->first();

    expect($subject)->not->toBeNull();
    expect($subject->is_elective)->toBeTrue();
});

test('a subject elective flag can be toggled from the edit form', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $subject = Subject::factory()->create(['is_elective' => false]);

    $this->actingAs($admin)
        ->put(route('subjects.update', $subject), [
            'subject_name' => $subject->subject_name,
            'grade_level_id' => $subject->grade_level_id,
            'is_elective' => true,
        ])
        ->assertRedirect(route('subjects.index'));

    expect($subject->fresh()->is_elective)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('subjects.update', $subject), [
            'subject_name' => $subject->subject_name,
            'grade_level_id' => $subject->grade_level_id,
            'is_elective' => false,
        ])
        ->assertRedirect(route('subjects.index'));

    expect($subject->fresh()->is_elective)->toBeFalse();
});
