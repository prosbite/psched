<?php

use App\Enums\ScheduleGroupStatus;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function scheduleGroupAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('finalizing a draft group makes it active', function () {
    $admin = scheduleGroupAdmin();
    $group = ScheduleGroup::factory()->create();
    Schedule::factory()->create(['schedule_group_id' => $group->id]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.finalize', $group))
        ->assertRedirect(route('schedule-groups.show', $group));

    expect($group->fresh()->status)->toBe(ScheduleGroupStatus::Active);
});

test('an active group cannot be edited and can be reopened', function () {
    $admin = scheduleGroupAdmin();
    $group = ScheduleGroup::factory()->active()->create();

    $section = Section::factory()->create();
    $subject = Subject::factory()->create(['grade_level_id' => $section->grade_level_id]);

    $payload = [
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'subject_teacher_id' => Teacher::factory()->create()->id,
        'proctor_id' => Teacher::factory()->create()->id,
        'start_time' => '08:00',
        'end_time' => '09:00',
    ];

    $this->actingAs($admin)
        ->post(route('schedule-groups.schedules.store', $group), $payload)
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('schedule-groups.reopen', $group))
        ->assertRedirect(route('schedule-groups.show', $group));

    expect($group->fresh()->status)->toBe(ScheduleGroupStatus::Draft);
});

test('the public page lists every active group only', function () {
    $active = ScheduleGroup::factory()->active()->create();
    Schedule::factory()->create(['schedule_group_id' => $active->id]);
    ScheduleGroup::factory()->create();

    $this->withoutVite()
        ->get('/schedules')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Schedules')
            ->has('groups', 1)
        );
});
