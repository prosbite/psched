# Add core subject type (optional self-proctoring)

## Goal

Replace the `subjects.is_elective` boolean with an explicit `subjects.type` value of
`regular`, `core`, or `elective`.

- **Regular** — current non-elective behaviour: the subject teacher may not proctor their own exam.
- **Core** — the subject teacher **may** proctor their own exam, but is not forced to. The generator treats them as a normal proctor candidate and counts the assignment like any other proctorship.
- **Elective** — unchanged: the subject teacher is always the proctor, does not use the proctor budget, and empty groups render the "Subject Teachers" placeholder.

Existing non-elective subjects migrate to `regular`, so behaviour changes only after an admin marks a subject `core`.

## Decisions

| Topic | Decision |
| --- | --- |
| Storage | New `subjects.type` string column + `App\Enums\SubjectType` backed enum. Drop `is_elective` after backfill. |
| Default | `regular` for new and existing non-elective subjects. |
| Generator candidate order | Core subject teacher joins the existing fairness-ordered proctor list. No new priority. |
| Cap/load | Self-proctoring counts toward `max_proctors_per_teacher` and proctor totals/loads like any other proctorship. |
| Exemption | Core self-proctoring follows the existing per-day proctoring exemption (`subjectTeachingExemptionsByDay`); an exempt teacher is not in the day's proctor list, so cannot self-proctor that day. |
| Manual flow | `proctor_id` remains required. For core, selecting the subject teacher is allowed; regular still rejects it. |
| Merged exam | A core teacher may self-proctor the same subject across sections at the same time (same-exam exemption already in `ConflictChecker::teacherRolesConflict`). |
| Electives | No behaviour change, including the placeholder rows and proctor-budget exclusion. |

## Implementation

### 1. Enum, schema, model

- Add `app/Enums/SubjectType.php`:

  ```php
  enum SubjectType: string
  {
      case Regular = 'regular';
      case Core = 'core';
      case Elective = 'elective';

      public function label(): string { /* Regular | Core | Elective */ }
      public function allowsSelfProctoring(): bool { return $this !== self::Regular; }
      public function requiresSelfProctoring(): bool { return $this === self::Elective; }
  }
  ```

- Add `database/migrations/2026_01_01_000023_add_type_to_subjects_table.php`:
  - `up()`: add `type` string, default `regular`, after `grade_level_id`; `DB::table('subjects')->where('is_elective', true)->update(['type' => 'elective'])`; drop `is_elective`.
  - `down()`: re-add `is_elective` boolean default false; backfill `type = 'elective'`; drop `type`.
- `app/Models/Subject.php`: fillable `type`; cast `'type' => SubjectType::class`; import the enum.
- `database/factories/SubjectFactory.php`: add `'type' => SubjectType::Regular`.
- `database/seeders/DemoDataSeeder.php:250`: replace `is_elective` with `type` (`SubjectType::Elective` for the existing `ELECTIVES` mapping, otherwise `SubjectType::Regular`).

### 2. Subject management UI/API

- `app/Http/Controllers/SubjectController.php`:
  - Validate `type` with `['required', Rule::enum(SubjectType::class)]` in `store()` and `update()`.
  - Pass `subjectTypes` to `Subjects/Index`: ordered `{value, label}` pairs from `SubjectType::cases()`.
- `resources/js/Pages/Subjects/Index.vue`:
  - Form state `type: 'regular'`; on edit set `form.type = subject.type`.
  - Replace the "Elective subject" checkbox with a "Subject type" select (Regular/Core/Elective).
  - Render a badge for `core` (e.g. blue) alongside the existing `elective` (amber) badge.

### 3. Conflict checker

Add two public methods to `app/Services/ConflictChecker.php`; **do not change `conflicts()` or `isConflictFree()`**:

```php
public function coreConflicts(array $existing, array $candidate, int $maxProctorsPerTeacher): array
{
    // section overlap reason (same message as conflicts())
    // teacherRolesConflict() reason: 'A teacher is already assigned to an overlapping schedule.'
    // proctorCapExceeded() reason (same message as conflicts())
    // return array_values(array_unique($reasons));
}

public function isCoreConflictFree(array $existing, array $candidate, int $maxProctorsPerTeacher): bool
{
    return $this->coreConflicts($existing, $candidate, $maxProctorsPerTeacher) === [];
}
```

`teacherRolesConflict()` already permits same-exam self-proctoring via `$sameExam` / `$firstSelfProctored` / `$secondSelfProctored`, so core needs no new rule logic.

### 4. Schedule generator

`app/Services/ScheduleGenerator.php`:

- Replace `$subject->is_elective` dispatch (`:175`) with `SubjectType` comparisons.
- Keep the regular `place()` unchanged.
- Add `placeCore()` mirroring `place()` but:
  - Do **not** skip `proctorId === $subjectTeacherId`.
  - Use `isCoreConflictFree($placed, $candidate, $maxProctors)` for section overlap + subject-aware teacher conflict + proctor cap.
  - When `proctorId !== $subjectTeacherId`, keep the proactive future-teaching exclusion: `hasConflictingTeacher($teachingPlan, $proctorCheck)` with `proctorCheck['subject_teacher_id'] = null`.
  - When `proctorId === $subjectTeacherId`, skip the `$teachingPlan` check (the plan's own entry is this exam; first-in-subject-order wins for any future collision).
- Dispatch in `generate()` after the existing `teacher_conflict` base check:

  ```php
  $entry = $subject->type === SubjectType::Core
      ? $this->placeCore($base, $subjectTeacherId, $dayProctors, $placed, $loadTotals, $proctorTotals, $maxProctors, $teachingPlan)
      : $this->place(...);
  ```

- Keep incrementing `$loadTotals` / `$proctorTotals` for core exactly as for regular, including when the proctor is the subject teacher.
- Update the class docblock to mention core subjects.

### 5. Manual schedule request/persistence

- `app/Http/Requests/ScheduleRequest.php`:
  - Resolve the subject once and branch on `$subject?->type ?? SubjectType::Regular`.
  - `proctor_id` in `$candidate`: force to subject teacher only for `Elective`; otherwise use the submitted proctor.
  - Elective branch: unchanged.
  - Regular branch: unchanged (`$subjectTeacherCandidate` specific message + `conflicts(..., false)`).
  - New core branch: `$reasons = $checker->coreConflicts($existing, $candidate, $maxProctors);` then add each reason to `proctor_id`. This allows `proctor_id === subject_teacher_id` while still rejecting cross-subject teaching conflicts, section overlaps, and cap violations.
- `app/Http/Controllers/ScheduleController.php:89`: force the proctor to the subject teacher only when `$subject?->type === SubjectType::Elective`.

### 6. Grid / public pages / exports

Replace `is_elective` reads with the enum:

- `app/Services/ScheduleGrid.php:117,144`: `$subject->type === SubjectType::Elective` for the placeholder and `elective` flag. No core placeholder.
- `resources/js/Pages/ScheduleGroups/Show.vue`:
  - `dayBlocks` (`:526,:531`): `subject.type === 'elective'` for placeholder/elective flag.
  - Setup subject list (`:938`): show an Elective badge for `type === 'elective'` and a Core badge for `type === 'core'`.
- `resources/js/Pages/Public/Schedules.vue:154,159`: `subject.type === 'elective'`.
- PDF export uses `ScheduleGrid`'s `elective` flag, so no Blade change is required.

## Tests

Update every existing `is_elective` fixture to `type`:

- `tests/Feature/SubjectTest.php` — create/toggle `core` and `elective`; assert `SubjectType` values.
- `tests/Feature/Scheduling/ScheduleGeneratorTest.php` — existing elective fixtures (`:189,225,1045,1050,1087`).
- `tests/Feature/Scheduling/ScheduleStoreTest.php:77`, `tests/Feature/Scheduling/ScheduleUpdateTest.php:173`.

Add coverage:

**ConflictCheckerTest**
- `coreConflicts` returns empty for a self-proctored core subject repeated across sections of the same subject at the same time.
- `coreConflicts` reports a conflict when the same subject teacher teaches a different subject at the same time.
- `coreConflicts` reports a proctor who is teaching another subject at the same time.

**ScheduleGeneratorTest**
- Core subject, only the subject teacher allowed as proctor → placed with `proctor_id === subject_teacher_id`.
- Core subject with the subject teacher plus an unloaded proctor → the unloaded proctor is chosen (self-proctoring not compulsory).
- Core self-proctoring counts toward the cap: two same-day core exams, only the subject teacher allowed, `max_proctors_per_teacher = 1` → one placed, one `no_proctor`.
- Same core subject across two sections, only the subject teacher allowed, cap ≥ 2 → both placed, both self-proctored.
- Two core subjects in different grades sharing one teacher and one real time → one placed, one `teacher_conflict`.
- Regular subject with only the subject teacher allowed → unplaced `no_proctor` (guards the non-core skip).

**ScheduleStoreTest / ScheduleUpdateTest**
- Storing a core schedule with the subject teacher as proctor succeeds and persists that proctor.
- Storing a core schedule whose subject teacher is already teaching another subject at the same time is rejected on `proctor_id`.
- Existing regular self-proctor rejection test must still pass.
- Existing elective update test must still pass.

## Risks / notes

- Removing `is_elective` is a breaking column change; the migration backfills before dropping. Existing deployments must run `migrate`, not `migrate:fresh`, unless demo data is disposable.
- Core self-proctoring is a normal proctorship, so it consumes the per-day cap. For a core exam repeated across three or more sections with only the subject teacher available, the later sections may fall back to `no_proctor` once the cap is reached. This follows the chosen cap semantics.
- Core self-proctoring follows the per-day proctoring exemption; a teacher who handles two or more subjects that day cannot self-proctor that day.
- The manual core conflict message is the generic "A teacher is already assigned to an overlapping schedule."; the regular branch keeps its subject-teacher-specific message.
- No core badge is added to the schedule grid/PDF; core rows look like regular rows. Only the Subjects page and the group setup subject list distinguish Core.

## Validation

- `php artisan migrate`
- `vendor\bin\pint --dirty`
- `vendor\bin\pest`
- `npm run build` (Vue files changed)
- Manual smoke:
  1. Create a core subject and confirm the badge/type.
  2. Generate a one-slot group with only the core teacher allowed as proctor; confirm they are recorded as both subject teacher and proctor.
  3. Add an extra unloaded proctor and regenerate; confirm the extra proctor is preferred.
  4. Add/edit a core schedule via the modal with the subject teacher as proctor; confirm it saves.
  5. Add/edit a regular schedule with the subject teacher as proctor; confirm it is still rejected.
