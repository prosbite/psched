<?php

use App\Enums\UserRole;
use App\Models\Quarter;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\User;

test('a schedule group requires a school year and quarter', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            'title' => 'Midterm Exam Day',
            'max_proctors_per_teacher' => 2,
        ])
        ->assertSessionHasErrors(['school_year_id', 'quarter_id']);

    expect(ScheduleGroup::where('title', 'Midterm Exam Day')->exists())->toBeFalse();
});

test('a schedule group persists its school year and quarter', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $year = SchoolYear::factory()->create();
    $quarter = Quarter::factory()->create();

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            'title' => 'Midterm Exam Day',
            'max_proctors_per_teacher' => 2,
            'days' => 3,
            'exams_per_day' => 3,
            'school_year_id' => $year->id,
            'quarter_id' => $quarter->id,
        ])
        ->assertSessionHasNoErrors();

    $group = ScheduleGroup::where('title', 'Midterm Exam Day')->firstOrFail();

    expect($group->school_year_id)->toBe($year->id);
    expect($group->quarter_id)->toBe($quarter->id);
    expect($group->days)->toBe(3);
    expect($group->exams_per_day)->toBe(3);
});

test('a schedule group rejects an unknown school year or quarter', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            'title' => 'Midterm Exam Day',
            'max_proctors_per_teacher' => 2,
            'school_year_id' => 999999,
            'quarter_id' => 999999,
        ])
        ->assertSessionHasErrors(['school_year_id', 'quarter_id']);
});
