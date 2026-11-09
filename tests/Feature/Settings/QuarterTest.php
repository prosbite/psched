<?php

use App\Enums\UserRole;
use App\Models\Quarter;
use App\Models\ScheduleGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the admin index renders the quarters page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Quarter::factory()->create(['name' => 'Quarter 1']);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('quarters.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quarters/Index')
            ->has('quarters', 1)
            ->where('quarters.0.name', 'Quarter 1')
        );
});

test('a quarter can be created', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('quarters.store'), [
            'name' => 'Quarter 1',
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect(route('quarters.index'))
        ->assertSessionHas('success');

    expect(Quarter::where('name', 'Quarter 1')->exists())->toBeTrue();
});

test('a quarter name must be unique', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Quarter::factory()->create(['name' => 'Quarter 1']);

    $this->actingAs($admin)
        ->post(route('quarters.store'), [
            'name' => 'Quarter 1',
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertSessionHasErrors('name');
});

test('the quarter end date must not precede the start date', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('quarters.store'), [
            'name' => 'Quarter 1',
            'start_date' => '2026-10-31',
            'end_date' => '2026-08-01',
        ])
        ->assertSessionHasErrors('end_date');
});

test('a quarter can be updated', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $quarter = Quarter::factory()->create();

    $this->actingAs($admin)
        ->put(route('quarters.update', $quarter), [
            'name' => 'Quarter 1',
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect(route('quarters.index'))
        ->assertSessionHas('success');

    expect(Quarter::find($quarter->id)->name)->toBe('Quarter 1');
});

test('deleting a quarter used by a schedule group is blocked', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $quarter = Quarter::factory()->create();
    ScheduleGroup::factory()->create(['quarter_id' => $quarter->id]);

    $this->actingAs($admin)
        ->from(route('quarters.index'))
        ->delete(route('quarters.destroy', $quarter))
        ->assertRedirect(route('quarters.index'))
        ->assertSessionHas('error');

    expect(Quarter::find($quarter->id))->not->toBeNull();
});

test('an unused quarter can be deleted', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $quarter = Quarter::factory()->create();

    $this->actingAs($admin)
        ->delete(route('quarters.destroy', $quarter))
        ->assertRedirect(route('quarters.index'))
        ->assertSessionHas('success');

    expect(Quarter::find($quarter->id))->toBeNull();
});

test('guests are redirected to login', function () {
    $this->get(route('quarters.index'))->assertRedirect(route('login'));
});

test('non-admin users are forbidden', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);

    $this->actingAs($teacher)->get(route('quarters.index'))->assertForbidden();
});
