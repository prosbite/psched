<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Quarter;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimePreset;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the show page lists the saved setup of other groups for copying', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $current = ScheduleGroup::factory()->create();

    $grade = GradeLevel::factory()->create();
    $firstSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $secondSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);

    $defaultPreset = TimePreset::factory()->create();
    $dayTwoPreset = TimePreset::factory()->create();

    $source = ScheduleGroup::factory()->create([
        'time_preset_id' => $defaultPreset->id,
        'days' => 2,
        'exams_per_day' => 5,
    ]);
    $source->sections()->attach($section);
    $source->subjects()->attach([
        $firstSubject->id => ['sort_order' => 1],
        $secondSubject->id => ['sort_order' => 0],
    ]);
    $source->dayPresets()->create([
        'day' => 2,
        'time_preset_id' => $dayTwoPreset->id,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('schedule-groups.show', $current))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('copyableGroups', 1)
            ->where('copyableGroups.0.id', $source->id)
            ->where('copyableGroups.0.title', $source->title)
            ->where('copyableGroups.0.days', 2)
            ->where('copyableGroups.0.exams_per_day', 5)
            ->where('copyableGroups.0.time_preset_id', $defaultPreset->id)
            ->where('copyableGroups.0.day_presets', [
                ['day' => 2, 'time_preset_id' => $dayTwoPreset->id],
            ])
            ->where('copyableGroups.0.section_ids', [$section->id])
            ->where('copyableGroups.0.subject_ids', [$secondSubject->id, $firstSubject->id])
        );
});

test('creating a group from a previous setup copies its sections and ordered subjects', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $grade = GradeLevel::factory()->create();
    $room = Room::factory()->create();
    $firstSection = Section::factory()->create(['grade_level_id' => $grade->id, 'room_id' => $room->id]);
    $secondSection = Section::factory()->create(['grade_level_id' => $grade->id, 'room_id' => $room->id]);
    $firstSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $secondSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $defaultPreset = TimePreset::factory()->create();
    $dayTwoPreset = TimePreset::factory()->create();

    $source = ScheduleGroup::factory()->create([
        'time_preset_id' => $defaultPreset->id,
        'days' => 2,
        'exams_per_day' => 5,
        'max_proctors_per_teacher' => 4,
    ]);
    $source->sections()->attach([$firstSection->id, $secondSection->id]);
    $source->subjects()->attach([
        $firstSubject->id => ['sort_order' => 3],
        $secondSubject->id => ['sort_order' => 0],
    ]);
    $source->dayPresets()->create([
        'day' => 2,
        'time_preset_id' => $dayTwoPreset->id,
    ]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            'title' => 'Copied Schedule',
            'days' => 2,
            'exams_per_day' => 5,
            'time_preset_id' => $defaultPreset->id,
            'school_year_id' => SchoolYear::factory()->create()->id,
            'quarter_id' => Quarter::factory()->create()->id,
            'max_proctors_per_teacher' => 4,
            'day_presets' => [
                1 => '',
                2 => $dayTwoPreset->id,
            ],
            'copy_setup_from' => $source->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('schedule-groups.show', ScheduleGroup::where('title', 'Copied Schedule')->firstOrFail()));

    $group = ScheduleGroup::where('title', 'Copied Schedule')->firstOrFail();

    expect($group->max_proctors_per_teacher)->toBe(4);
    expect($group->days)->toBe(2);
    expect($group->exams_per_day)->toBe(5);
    expect($group->time_preset_id)->toBe($defaultPreset->id);

    expect($group->sections()->pluck('sections.id')->sort()->values()->all())
        ->toBe(collect([$firstSection->id, $secondSection->id])->sort()->values()->all());

    expect(
        $group->subjects()
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => (int) $subject->pivot->sort_order])
            ->all()
    )->toBe([
        $firstSubject->id => 3,
        $secondSubject->id => 0,
    ]);

    expect($group->dayPresets()->orderBy('day')->get()->pluck('time_preset_id', 'day')->all())
        ->toBe([2 => $dayTwoPreset->id]);
});

test('creating a group rejects an unknown setup source', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.store'), [
            'title' => 'Unknown Source Schedule',
            'days' => 2,
            'exams_per_day' => 3,
            'school_year_id' => SchoolYear::factory()->create()->id,
            'quarter_id' => Quarter::factory()->create()->id,
            'max_proctors_per_teacher' => 2,
            'copy_setup_from' => 999999,
        ])
        ->assertSessionHasErrors('copy_setup_from');

    expect(ScheduleGroup::where('title', 'Unknown Source Schedule')->exists())->toBeFalse();
});

test('the create page lists groups whose setup can be copied', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $grade = GradeLevel::factory()->create();
    $section = Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $source = ScheduleGroup::factory()->create([
        'max_proctors_per_teacher' => 6,
    ]);
    $source->sections()->attach($section);
    $source->subjects()->attach($subject->id, ['sort_order' => 0]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('schedule-groups.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScheduleGroups/Form')
            ->has('copyableGroups', 1)
            ->where('copyableGroups.0.id', $source->id)
            ->where('copyableGroups.0.section_ids', [$section->id])
            ->where('copyableGroups.0.subject_ids', [$subject->id])
            ->where('copyableGroups.0.max_proctors_per_teacher', 6)
        );
});
