# Persist & Act on Unplaced Exams

## Goal
Stop losing the generator's "unplaced" items in a one-shot flash. Persist them per
schedule group so an admin can open the group, see exactly which section/subject failed,
why, and fix + place each one. Regenerating replaces the list.

## Current behavior (verified)
- `ScheduleGroupController::generate` builds `{placed, unplaced, warnings}` and only
  `->with('generation_report', $report)` (session flash). Nothing is stored; navigating
  away loses it.
- Unplaced entries produced by `ScheduleGenerator::unplacedEntry`:
  `day, section_id, section_name, grade_level_id, subject_id, subject_name, reason`.
- Three failure modes:
  1. `No time slot is configured for this period.` (no effective slot for the grade/period)
  2. `No teacher is assigned to "<subject>" for "<section>".` (no `section_subject_teacher`)
  3. `No conflict-free proctor is available for ...` (pool/cap exhausted)
- `Show.vue` renders a bare `—` in unmatched grid cells; no reason shown.
- The seeded demo currently yields 34 rows, **all `missing_teacher`** (G9 Social Science 3
  Sodium/Barium; G10 Chemistry 3 ×4; G11 Class 1/2/3 + Physics/Biology gaps; G12 gaps).
  That is the real use case for this feature, so no seed changes are required.

## Locked decisions
- Persist unplaced items in a **dedicated table**, snapshot semantics (generate replaces).
- Each row is **actionable**: "Assign teacher" and "Add schedule" shortcuts (plus a
  "Fix time preset" link for the missing-slot case).
- A successful manual add removes the matching unplaced row (avoid stale rows).

## Tasks

### 1. Migration — `database/migrations/2026_01_01_000021_create_schedule_group_unplaced_table.php`
- `id`
- `schedule_group_id` FK `schedule_groups` cascadeOnDelete
- `day` unsignedTinyInteger default 1
- `section_id` FK `sections` cascadeOnDelete
- `subject_id` FK `subjects` cascadeOnDelete
- `reason` string
- `reason_code` string
- `start_time` time nullable, `end_time` time nullable
- timestamps; index on `schedule_group_id`

### 2. Model + relation
- `app/Models/ScheduleGroupUnplaced.php`: `#[Fillable([...])]`, `day` int cast,
  `belongsTo` scheduleGroup/section/subject.
- `ScheduleGroup::unplaced(): HasMany`.

### 3. Generator reason metadata (`app/Services/ScheduleGenerator.php`)
- Change `unplacedEntry(...)` to accept a machine code and the resolved slot:
  `unplacedEntry(int $day, Section $section, Subject $subject, string $code, string $reason, ?TimePresetSlot $slot)`.
- Codes: `missing_slot`, `missing_teacher`, `no_proctor`.
- Add `reason_code`, and `start_time`/`end_time` (null when no slot) to the returned entry.
- Update the 3 call sites (missing slot / missing teacher / no proctor).

### 4. Persist on generate (`app/Http/Controllers/ScheduleGroupController.php::generate`)
- Inside the existing `DB::transaction`: `$scheduleGroup->unplaced()->delete();` then insert
  each `$report['unplaced']` entry (map to columns). Keep the success flash summary.

### 5. Load on show (`show()`)
- Eager-load `unplaced.section`, `unplaced.subject` so the Show page can render names and
  action targets without extra props.

### 6. Manual add/update clears the matching row (`app/Http/Controllers/ScheduleController.php`)
- After `store()` (and `update()`), delete `ScheduleGroupUnplaced` matching
  `schedule_group_id + day + section_id + subject_id`.

### 7. Assign-teacher endpoint stays on-page (`app/Http/Controllers/SectionController.php::assignTeachers`)
- Change the final `redirect()->route('sections.index')` to `back()` so the same endpoint
  works both from `Sections/Index` and from the group Show page. Validation is unchanged and
  already accepts a partial single-row `assignments` payload.
- No test asserts the old redirect target (verified).

### 8. `resources/js/Pages/ScheduleGroups/Show.vue`
- Replace the flash-only inline unplaced `<ul>` with a persistent **"Needs attention (N)"**
  panel (visible whenever `group.unplaced.length > 0`). Keep the concise flash summary.
- Row shows: Day, Grade/Section, Subject, reason text, and actions:
  - **Assign teacher** — opens a modal listing that section's subjects
    (`group.subjects` filtered by grade) with a teacher select (`teachers`), prefilled from
    the existing `assignments` prop; submits `sections.assignments.update` (partial payload).
  - **Add schedule** — opens the existing Add Schedule modal prefilled with the row's
    day/section/subject/start_time/end_time (Save stays disabled until a subject teacher exists).
  - **Fix time preset** — link to `time-presets.edit` when `reason_code === 'missing_slot'`.
- Mark unplaced cells in the grid (small red "Unplaced" + reason title) using a
  `${day}-${section_id}-${subject_id}` set built from `group.unplaced`.
- Reuse the existing `timeSlotsForGrade`/grid helpers; no new props beyond `unplaced`.

### 9. Tests (`tests/Feature/Scheduling/`)
- Extend `ScheduleGeneratorTest`:
  - missing-slot case asserts `reason_code === 'missing_slot'`, null times.
  - missing-teacher case asserts `reason_code === 'missing_teacher'` and non-null `start_time`.
- New `ScheduleGroupUnplacedTest`:
  - generate endpoint persists rows equal to the report's unplaced count, and regenerating
    replaces rather than duplicates.
  - manually storing a schedule for that (day, section, subject) removes the matching row.
  - `show()` exposes `unplaced` (assertInertia, `withoutVite()`).
- Keep all existing suites green.

## Validation
- `php artisan migrate:fresh --seed`
- `vendor\bin\pint app routes database tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual: generate the demo group → 34 persisted rows listed with reasons; assign a teacher
  for `Electron / Chemistry 3` → Add schedule (or Generate) → that row disappears; rows with
  no slot show the preset link.

## Risks / notes
- The same subject teacher legitimately repeats across sections of a grade (merged ST cell);
  it is not an unplaced cause and should not be "fixed" by the feature.
- Deleting a section/subject cascades its unplaced rows.
- `finalize` stays allowed with outstanding unplaced rows (surface a warning only); hard-blocking
  is out of scope unless requested.
- Storage is a snapshot: rows only disappear via regenerate or a matching manual add, not by
  editing external data on its own.
