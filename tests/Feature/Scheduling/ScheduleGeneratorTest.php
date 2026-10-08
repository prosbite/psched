<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimePreset;
use App\Models\User;
use App\Services\ConflictChecker;
use App\Services\ScheduleGenerator;

function generatorSectionFor(GradeLevel $grade): Section
{
    return Section::factory()->create([
        'grade_level_id' => $grade->id,
        'room_id' => Room::factory()->create()->id,
    ]);
}

function generatorPreset(int $slotCount): TimePreset
{
    $preset = TimePreset::factory()->create();

    for ($index = 0; $index < $slotCount; $index++) {
        $preset->slots()->create([
            'start_time' => sprintf('%02d:00:00', 7 + $index),
            'end_time' => sprintf('%02d:00:00', 8 + $index),
            'sort_order' => $index,
        ]);
    }

    return $preset;
}

test('generates a valid balanced grid', function () {
    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacherOne = Teacher::factory()->create();
    $teacherTwo = Teacher::factory()->create();
    $teacherOne->subjects()->attach($subjectOne);
    $teacherTwo->subjects()->attach($subjectTwo);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create(['time_preset_id' => $preset->id]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(4);

    $checker = app(ConflictChecker::class);
    $seen = [];

    foreach ($report['placed'] as $entry) {
        expect($checker->isConflictFree($seen, $entry, $group->max_proctors_per_teacher))->toBeTrue();
        $seen[] = $entry;
    }

    $proctorLoads = array_count_values(array_map(fn (array $entry): int => $entry['proctor_id'], $report['placed']));

    expect(max($proctorLoads) - min($proctorLoads))->toBeLessThanOrEqual(1);
});

test('reports unplaced items when there are fewer slots than subjects', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjects = Subject::factory()->count(3)->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $teacher->subjects()->attach($subjects->pluck('id'));
    Teacher::factory()->create();

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create(['time_preset_id' => $preset->id]);
    $group->sections()->attach($section);
    $group->subjects()->attach($subjects->pluck('id'));

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(2);
    expect($report['unplaced'])->toHaveCount(1);
    expect($report['unplaced'][0]['reason'])->toContain('slots');
});

test('reports a subject that has no qualified teacher', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $coveredSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $uncoveredSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $teacher->subjects()->attach($coveredSubject);
    Teacher::factory()->create();

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create(['time_preset_id' => $preset->id]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$coveredSubject->id, $uncoveredSubject->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(1);
    expect($report['unplaced'])->toHaveCount(1);
    expect($report['unplaced'][0]['subject_id'])->toBe($uncoveredSubject->id);
    expect($report['unplaced'][0]['reason'])->toContain('qualified');
});

test('reports an item when proctor capacity is exhausted', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $teacher->subjects()->attach([$subjectOne->id, $subjectTwo->id]);
    Teacher::factory()->create();

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'max_proctors_per_teacher' => 1,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(1);
    expect($report['unplaced'])->toHaveCount(1);
});

test('the generate endpoint persists the generated grid', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacherOne = Teacher::factory()->create();
    $teacherTwo = Teacher::factory()->create();
    $teacherOne->subjects()->attach($subjectOne);
    $teacherTwo->subjects()->attach($subjectTwo);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create(['time_preset_id' => $preset->id]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.generate', $group))
        ->assertRedirect(route('schedule-groups.show', $group));

    expect($group->schedules()->count())->toBe(4);
});
