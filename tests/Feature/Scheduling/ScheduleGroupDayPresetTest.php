<?php

use App\Enums\UserRole;
use App\Models\Quarter;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\TimePreset;
use App\Models\User;

/**
 * @return array{title: string, days: int, exams_per_day: int, school_year_id: int, quarter_id: int, max_proctors_per_teacher: int}
 */
function dayPresetBasePayload(): array
{
    return [
        'title' => 'Day Preset Schedule',
        'days' => 3,
        'exams_per_day' => 2,
        'school_year_id' => SchoolYear::factory()->create()->id,
        'quarter_id' => Quarter::factory()->create()->id,
        'max_proctors_per_teacher' => 2,
    ];
}

test('storing a group persists its per-day preset overrides', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $default = TimePreset::factory()->create();
    $dayOne = TimePreset::factory()->create();
    $dayThree = TimePreset::factory()->create();

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            ...dayPresetBasePayload(),
            'time_preset_id' => $default->id,
            'day_presets' => [
                1 => $dayOne->id,
                2 => '',
                3 => $dayThree->id,
            ],
        ])
        ->assertSessionHasNoErrors();

    $group = ScheduleGroup::where('title', 'Day Preset Schedule')->firstOrFail();

    expect($group->dayPresets()->orderBy('day')->get()->pluck('time_preset_id', 'day')->all())
        ->toBe([1 => $dayOne->id, 3 => $dayThree->id]);
});

test('updating fewer days removes the overrides beyond the new day count', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $default = TimePreset::factory()->create();
    $dayOne = TimePreset::factory()->create();
    $dayTwo = TimePreset::factory()->create();
    $dayThree = TimePreset::factory()->create();

    $group = ScheduleGroup::factory()->create([
        'title' => 'Day Preset Schedule',
        'time_preset_id' => $default->id,
        'days' => 3,
        'exams_per_day' => 2,
    ]);

    $group->dayPresets()->createMany([
        ['day' => 1, 'time_preset_id' => $dayOne->id],
        ['day' => 2, 'time_preset_id' => $dayTwo->id],
        ['day' => 3, 'time_preset_id' => $dayThree->id],
    ]);

    $this->actingAs($admin)
        ->put(route('schedule-groups.update', $group), [
            ...dayPresetBasePayload(),
            'days' => 2,
            'time_preset_id' => $default->id,
            'day_presets' => [
                1 => $dayOne->id,
                2 => $dayTwo->id,
            ],
        ])
        ->assertSessionHasNoErrors();

    $group->refresh();

    expect($group->days)->toBe(2);
    expect($group->dayPresets()->orderBy('day')->get()->pluck('time_preset_id', 'day')->all())
        ->toBe([1 => $dayOne->id, 2 => $dayTwo->id]);
});

test('the setup endpoint syncs per-day presets and removes emptied days', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $default = TimePreset::factory()->create();
    $dayTwo = TimePreset::factory()->create();
    $dayThree = TimePreset::factory()->create();

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $default->id,
        'days' => 3,
        'exams_per_day' => 2,
    ]);

    $group->dayPresets()->create([
        'day' => 1,
        'time_preset_id' => $dayThree->id,
    ]);

    $this->actingAs($admin)
        ->put(route('schedule-groups.setup', $group), [
            'time_preset_id' => $default->id,
            'days' => 3,
            'exams_per_day' => 2,
            'sections' => [],
            'subjects' => [],
            'day_presets' => [
                1 => '',
                2 => $dayTwo->id,
                3 => $dayThree->id,
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($group->dayPresets()->orderBy('day')->get()->pluck('time_preset_id', 'day')->all())
        ->toBe([2 => $dayTwo->id, 3 => $dayThree->id]);
});

test('per-day presets reject an unknown preset', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            ...dayPresetBasePayload(),
            'day_presets' => [1 => 999999],
        ])
        ->assertSessionHasErrors('day_presets.1');
});
