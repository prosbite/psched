<?php

use App\Enums\UserRole;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the admin index renders the school years page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    SchoolYear::factory()->create(['name' => '2025-2026']);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('school-years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SchoolYears/Index')
            ->has('schoolYears', 1)
            ->where('schoolYears.0.name', '2025-2026')
        );
});

test('a school year can be created', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('school-years.store'), ['name' => '2026-2027'])
        ->assertRedirect(route('school-years.index'))
        ->assertSessionHas('success');

    expect(SchoolYear::where('name', '2026-2027')->exists())->toBeTrue();
});

test('a school year name must be unique', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    SchoolYear::factory()->create(['name' => '2025-2026']);

    $this->actingAs($admin)
        ->post(route('school-years.store'), ['name' => '2025-2026'])
        ->assertSessionHasErrors('name');
});

test('a school year can be updated keeping its own name', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $year = SchoolYear::factory()->create(['name' => '2025-2026']);

    $this->actingAs($admin)
        ->put(route('school-years.update', $year), ['name' => '2025-2026'])
        ->assertRedirect(route('school-years.index'));

    $other = SchoolYear::factory()->create(['name' => '2026-2027']);

    $this->actingAs($admin)
        ->put(route('school-years.update', $year), ['name' => '2026-2027'])
        ->assertSessionHasErrors('name');

    expect(SchoolYear::find($year->id)->name)->toBe('2025-2026');
    expect(SchoolYear::find($other->id)->name)->toBe('2026-2027');
});

test('deleting a school year used by a schedule group is blocked', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $year = SchoolYear::factory()->create();
    ScheduleGroup::factory()->create(['school_year_id' => $year->id]);

    $this->actingAs($admin)
        ->from(route('school-years.index'))
        ->delete(route('school-years.destroy', $year))
        ->assertRedirect(route('school-years.index'))
        ->assertSessionHas('error');

    expect(SchoolYear::find($year->id))->not->toBeNull();
});

test('an unused school year can be deleted', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $year = SchoolYear::factory()->create();

    $this->actingAs($admin)
        ->delete(route('school-years.destroy', $year))
        ->assertRedirect(route('school-years.index'))
        ->assertSessionHas('success');

    expect(SchoolYear::find($year->id))->toBeNull();
});

test('guests are redirected to login', function () {
    $this->get(route('school-years.index'))->assertRedirect(route('login'));
});

test('non-admin users are forbidden', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);

    $this->actingAs($teacher)->get(route('school-years.index'))->assertForbidden();
});
