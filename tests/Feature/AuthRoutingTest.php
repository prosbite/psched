<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from the root', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('authenticated users are redirected to the dashboard from the root', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('the registration route no longer exists', function () {
    $this->get('/register')->assertNotFound();
});

test('the public schedules page is reachable without authentication', function () {
    $this->withoutVite()
        ->get('/schedules')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Schedules'));
});

test('teacher users are forbidden from admin routes', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);

    $this->actingAs($teacher)->get('/grade-levels')->assertForbidden();
});

test('admin users may access admin routes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->withoutVite()->actingAs($admin)->get('/grade-levels')->assertOk();
});
