<?php

use App\Http\Controllers\GradeLevelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicScheduleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleGroupController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TimePresetController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/schedules', [PublicScheduleController::class, 'index'])->name('public.schedules');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('grade-levels', GradeLevelController::class)->except(['create', 'show', 'edit']);
    Route::resource('rooms', RoomController::class)->except(['create', 'show', 'edit']);
    Route::resource('sections', SectionController::class)->except(['create', 'show', 'edit']);
    Route::resource('subjects', SubjectController::class)->except(['create', 'show', 'edit']);
    Route::resource('teachers', TeacherController::class)->except(['create', 'show', 'edit']);
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);
    Route::resource('time-presets', TimePresetController::class)->except(['show']);

    Route::resource('schedule-groups', ScheduleGroupController::class);

    Route::put('schedule-groups/{scheduleGroup}/setup', [ScheduleGroupController::class, 'setup'])
        ->name('schedule-groups.setup');
    Route::post('schedule-groups/{scheduleGroup}/generate', [ScheduleGroupController::class, 'generate'])
        ->name('schedule-groups.generate');
    Route::post('schedule-groups/{scheduleGroup}/finalize', [ScheduleGroupController::class, 'finalize'])
        ->name('schedule-groups.finalize');
    Route::post('schedule-groups/{scheduleGroup}/reopen', [ScheduleGroupController::class, 'reopen'])
        ->name('schedule-groups.reopen');
    Route::post('schedule-groups/{scheduleGroup}/archive', [ScheduleGroupController::class, 'archive'])
        ->name('schedule-groups.archive');

    Route::post('schedule-groups/{scheduleGroup}/schedules', [ScheduleController::class, 'store'])
        ->name('schedule-groups.schedules.store');
    Route::put('schedule-groups/{scheduleGroup}/schedules/{schedule}', [ScheduleController::class, 'update'])
        ->name('schedule-groups.schedules.update');
    Route::delete('schedule-groups/{scheduleGroup}/schedules/{schedule}', [ScheduleController::class, 'destroy'])
        ->name('schedule-groups.schedules.destroy');
});

require __DIR__.'/auth.php';
