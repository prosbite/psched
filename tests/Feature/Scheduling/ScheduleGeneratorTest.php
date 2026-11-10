<?php

use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
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

function assignTeacher(Section $section, Subject $subject, Teacher $teacher): SectionSubjectTeacher
{
    return SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);
}

test('generates a valid balanced grid', function () {
    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacherOne = Teacher::factory()->create();
    $teacherTwo = Teacher::factory()->create();
    Teacher::factory()->create();

    assignTeacher($sectionA, $subjectOne, $teacherOne);
    assignTeacher($sectionA, $subjectTwo, $teacherTwo);
    assignTeacher($sectionB, $subjectOne, $teacherOne);
    assignTeacher($sectionB, $subjectTwo, $teacherTwo);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(4);

    $checker = app(ConflictChecker::class);
    $seen = [];

    foreach ($report['placed'] as $entry) {
        // The subject teacher may repeat across sections of a grade at the
        // same time, so proctor-conflict free-ness is checked without it.
        $checkEntry = $entry;
        unset($checkEntry['subject_teacher_id']);

        expect($checker->isConflictFree($seen, $checkEntry, $group->max_proctors_per_teacher))->toBeTrue();
        $seen[] = $checkEntry;
    }

    $proctorLoads = array_count_values(array_map(fn (array $entry): int => $entry['proctor_id'], $report['placed']));

    expect(max($proctorLoads) - min($proctorLoads))->toBeLessThanOrEqual(1);
});

test('the same subject lands on the same day and period for every section of a grade', function () {
    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);
    $sectionC = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $subjectTeacher = Teacher::factory()->create();
    Teacher::factory()->count(5)->create();

    foreach ([$sectionA, $sectionB, $sectionC] as $section) {
        assignTeacher($section, $subjectOne, $subjectTeacher);
        assignTeacher($section, $subjectTwo, $subjectTeacher);
    }

    $preset = generatorPreset(3);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach([$sectionA->id, $sectionB->id, $sectionC->id]);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(6);

    $subjectOneEntries = collect($report['placed'])->where('subject_id', $subjectOne->id);

    expect($subjectOneEntries)->toHaveCount(3);
    expect($subjectOneEntries->pluck('day')->unique()->all())->toBe([1]);
    expect($subjectOneEntries->pluck('start_time')->unique()->all())->toBe(['07:00:00']);
});

test('grade specific slots override the default slots for that grade', function () {
    $gradeSeven = GradeLevel::factory()->create(['grade_level' => '7']);
    $gradeTen = GradeLevel::factory()->create(['grade_level' => '10']);

    $sectionSeven = generatorSectionFor($gradeSeven);
    $sectionTen = generatorSectionFor($gradeTen);

    $subjectSeven = Subject::factory()->create(['grade_level_id' => $gradeSeven->id]);
    $subjectTen = Subject::factory()->create(['grade_level_id' => $gradeTen->id]);

    $teacherSeven = Teacher::factory()->create();
    $teacherTen = Teacher::factory()->create();
    Teacher::factory()->count(3)->create();

    assignTeacher($sectionSeven, $subjectSeven, $teacherSeven);
    assignTeacher($sectionTen, $subjectTen, $teacherTen);

    $preset = TimePreset::factory()->create();

    $preset->slots()->createMany([
        ['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 0],
        ['start_time' => '09:30:00', 'end_time' => '10:30:00', 'sort_order' => 1],
        ['start_time' => '11:00:00', 'end_time' => '12:00:00', 'sort_order' => 2],
        ['grade_level_id' => $gradeTen->id, 'start_time' => '07:30:00', 'end_time' => '08:30:00', 'sort_order' => 0],
        ['grade_level_id' => $gradeTen->id, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 1],
        ['grade_level_id' => $gradeTen->id, 'start_time' => '10:30:00', 'end_time' => '12:00:00', 'sort_order' => 2],
    ]);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach([$sectionSeven->id, $sectionTen->id]);
    $group->subjects()->attach([$subjectSeven->id, $subjectTen->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['unplaced'])->toBeEmpty();

    $seven = collect($report['placed'])->firstWhere('section_id', $sectionSeven->id);
    $ten = collect($report['placed'])->firstWhere('section_id', $sectionTen->id);

    expect($seven['start_time'])->toBe('08:00:00');
    expect($ten['start_time'])->toBe('07:30:00');
});

test('an elective without enrolled sections produces no schedule rows', function () {
    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $elective = Subject::factory()->create(['grade_level_id' => $grade->id, 'is_elective' => true]);
    $subjectThree = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $subjectTeacher = Teacher::factory()->create();
    Teacher::factory()->count(5)->create();

    foreach ([$sectionA, $sectionB] as $section) {
        assignTeacher($section, $subjectOne, $subjectTeacher);
        assignTeacher($section, $subjectThree, $subjectTeacher);
    }

    $preset = generatorPreset(3);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([
        $subjectOne->id => ['sort_order' => 0],
        $elective->id => ['sort_order' => 1],
        $subjectThree->id => ['sort_order' => 2],
    ]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(4);
    expect(collect($report['placed'])->where('subject_id', $elective->id))->toBeEmpty();
});

test('an elective is proctored by its subject teacher', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $elective = Subject::factory()->create(['grade_level_id' => $grade->id, 'is_elective' => true]);
    $subjectTeacher = Teacher::factory()->create();
    $otherTeacher = Teacher::factory()->create();

    assignTeacher($section, $elective, $subjectTeacher);

    $preset = generatorPreset(1);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 1,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$elective->id => ['sort_order' => 0]]);

    $report = app(ScheduleGenerator::class)->generate($group, [$subjectTeacher->id, $otherTeacher->id]);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(1);
    expect($report['placed'][0]['subject_id'])->toBe($elective->id);
    expect($report['placed'][0]['subject_teacher_id'])->toBe($subjectTeacher->id);
    expect($report['placed'][0]['proctor_id'])->toBe($subjectTeacher->id);
});

test('proctor choice is cumulative across days', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectDayOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectDayTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $subjectTeacher = Teacher::factory()->create();
    $firstProctor = Teacher::factory()->create();
    $secondProctor = Teacher::factory()->create();

    assignTeacher($section, $subjectDayOne, $subjectTeacher);
    assignTeacher($section, $subjectDayTwo, $subjectTeacher);

    $preset = generatorPreset(1);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 2,
        'exams_per_day' => 1,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([
        $subjectDayOne->id => ['sort_order' => 0],
        $subjectDayTwo->id => ['sort_order' => 1],
    ]);

    $report = app(ScheduleGenerator::class)->generate($group, [$firstProctor->id, $secondProctor->id]);

    expect($report['unplaced'])->toBeEmpty();

    $dayOne = collect($report['placed'])->firstWhere('day', 1);
    $dayTwo = collect($report['placed'])->firstWhere('day', 2);

    expect($dayOne['proctor_id'])->toBe($firstProctor->id);
    expect($dayTwo['proctor_id'])->toBe($secondProctor->id);
});

test('the per-day proctor cap allows the same proctor on another day', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectDayOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectDayTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $subjectTeacher = Teacher::factory()->create();
    $onlyProctor = Teacher::factory()->create();

    assignTeacher($section, $subjectDayOne, $subjectTeacher);
    assignTeacher($section, $subjectDayTwo, $subjectTeacher);

    $preset = generatorPreset(1);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 2,
        'exams_per_day' => 1,
        'max_proctors_per_teacher' => 1,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([
        $subjectDayOne->id => ['sort_order' => 0],
        $subjectDayTwo->id => ['sort_order' => 1],
    ]);

    $report = app(ScheduleGenerator::class)->generate($group, [$onlyProctor->id]);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(2);
    expect(collect($report['placed'])->pluck('proctor_id')->unique()->all())->toBe([$onlyProctor->id]);
});

test('reports unplaced items when there are fewer slots than periods', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjects = Subject::factory()->count(3)->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    Teacher::factory()->create();

    foreach ($subjects as $subject) {
        assignTeacher($section, $subject, $teacher);
    }

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach($subjects->pluck('id'));

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(2);
    expect($report['unplaced'])->toHaveCount(1);
    expect($report['unplaced'][0]['reason'])->toContain('time slot');
    expect($report['unplaced'][0]['reason_code'])->toBe('missing_slot');
    expect($report['unplaced'][0]['start_time'])->toBeNull();
    expect($report['unplaced'][0]['end_time'])->toBeNull();
});

test('skips sections that are not enrolled in a subject', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $enrolledSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $unenrolledSubject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    Teacher::factory()->create();

    assignTeacher($section, $enrolledSubject, $teacher);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$enrolledSubject->id, $unenrolledSubject->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(1);
    expect($report['unplaced'])->toBeEmpty();
    expect(collect($report['placed'])->pluck('subject_id')->all())->toBe([$enrolledSubject->id]);
});

test('reports an enrolled section whose teacher was removed', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subject = Subject::factory()->create(['grade_level_id' => $grade->id]);

    Teacher::factory()->create();

    SectionSubjectTeacher::factory()->create([
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'teacher_id' => null,
    ]);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach($subject);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toBeEmpty();
    expect($report['unplaced'])->toHaveCount(1);
    expect($report['unplaced'][0]['subject_id'])->toBe($subject->id);
    expect($report['unplaced'][0]['reason'])->toContain('No teacher is assigned');
    expect($report['unplaced'][0]['reason_code'])->toBe('missing_teacher');
    expect($report['unplaced'][0]['start_time'])->not->toBeNull();
});

test('reports an item when proctor capacity is exhausted', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    Teacher::factory()->create();

    assignTeacher($section, $subjectOne, $teacher);
    assignTeacher($section, $subjectTwo, $teacher);

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
        'max_proctors_per_teacher' => 1,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group);

    expect($report['placed'])->toHaveCount(1);
    expect($report['unplaced'])->toHaveCount(1);
});

test('only allows the passed proctors', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacher = Teacher::factory()->create();
    $allowed = Teacher::factory()->create();
    Teacher::factory()->create();

    assignTeacher($section, $subjectOne, $teacher);
    assignTeacher($section, $subjectTwo, $teacher);

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $report = app(ScheduleGenerator::class)->generate($group, [$allowed->id]);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(2);

    foreach ($report['placed'] as $entry) {
        expect($entry['proctor_id'])->toBe($allowed->id);
    }
});

test('teachers handling two or more subjects are exempt from proctoring', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $subjectTeacher = Teacher::factory()->create();
    $proctor = Teacher::factory()->create();

    assignTeacher($section, $subjectOne, $subjectTeacher);
    assignTeacher($section, $subjectTwo, $subjectTeacher);

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
        'max_proctors_per_teacher' => 2,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([
        $subjectOne->id => ['sort_order' => 0],
        $subjectTwo->id => ['sort_order' => 1],
    ]);

    $report = app(ScheduleGenerator::class)->generate($group, [$subjectTeacher->id, $proctor->id]);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(2);
    expect(collect($report['placed'])->pluck('proctor_id')->unique()->all())->toBe([$proctor->id]);
});

test('teachers handling a single subject can still proctor', function () {
    $grade = GradeLevel::factory()->create();
    $section = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacherOne = Teacher::factory()->create();
    $teacherTwo = Teacher::factory()->create();

    assignTeacher($section, $subjectOne, $teacherOne);
    assignTeacher($section, $subjectTwo, $teacherTwo);

    $preset = generatorPreset(2);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
        'max_proctors_per_teacher' => 2,
    ]);
    $group->sections()->attach($section);
    $group->subjects()->attach([
        $subjectOne->id => ['sort_order' => 0],
        $subjectTwo->id => ['sort_order' => 1],
    ]);

    $report = app(ScheduleGenerator::class)->generate($group, [$teacherOne->id, $teacherTwo->id]);

    expect($report['unplaced'])->toBeEmpty();
    expect($report['placed'])->toHaveCount(2);
    expect(collect($report['placed'])->pluck('proctor_id')->unique()->sort()->values()->all())
        ->toBe([$teacherOne->id, $teacherTwo->id]);
});

test('the generate endpoint persists the generated multi-day grid', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $grade = GradeLevel::factory()->create();
    $sectionA = generatorSectionFor($grade);
    $sectionB = generatorSectionFor($grade);

    $subjectOne = Subject::factory()->create(['grade_level_id' => $grade->id]);
    $subjectTwo = Subject::factory()->create(['grade_level_id' => $grade->id]);

    $teacherOne = Teacher::factory()->create();
    $teacherTwo = Teacher::factory()->create();
    $teacherThree = Teacher::factory()->create();

    assignTeacher($sectionA, $subjectOne, $teacherOne);
    assignTeacher($sectionA, $subjectTwo, $teacherTwo);
    assignTeacher($sectionB, $subjectOne, $teacherOne);
    assignTeacher($sectionB, $subjectTwo, $teacherTwo);

    $preset = generatorPreset(4);

    $group = ScheduleGroup::factory()->create([
        'time_preset_id' => $preset->id,
        'days' => 1,
        'exams_per_day' => 3,
    ]);
    $group->sections()->attach([$sectionA->id, $sectionB->id]);
    $group->subjects()->attach([$subjectOne->id, $subjectTwo->id]);

    $this->actingAs($admin)
        ->post(route('schedule-groups.generate', $group), [
            'proctors' => [$teacherOne->id, $teacherTwo->id, $teacherThree->id],
        ])
        ->assertRedirect(route('schedule-groups.show', $group));

    expect($group->schedules()->count())->toBe(4);
    expect($group->schedules()->where('day', 1)->count())->toBe(4);
});

test('the generate endpoint requires at least one proctor', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $group = ScheduleGroup::factory()->create();

    $this->actingAs($admin)
        ->post(route('schedule-groups.generate', $group), [])
        ->assertSessionHasErrors('proctors');
});
