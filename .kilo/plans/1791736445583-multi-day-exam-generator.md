# Multi-Day Exam Generator (replicating the day-1 grid pattern)

## Goal
Make the schedule generator reproduce the school's exam grid pattern and generate a full
**up-to-3-day exam** in one action, balancing teacher proctor load cumulatively across days, and
reshape the admin/public grids to the real layout (grade band → subject/time → section rows).

## Locked decisions
1. **One ScheduleGroup = the whole exam.** Add `schedules.day` (1-3). Unique key becomes
   `(schedule_group_id, day, section_id, start_time)`.
2. **Timetable is `(grade, subject) -> (day, period)`** derived from an explicit per-grade subject
   order on the group's subject selection (`sort_order` on the pivot) plus a group `exams_per_day`
   (default 3). Subjects are chunked in order: day = floor(pos/epd)+1, period = pos % epd.
3. **Per-grade times**: add nullable `grade_level_id` to `time_preset_slots`. Null = applies to all
   grades; a grade-specific row set overrides the defaults for that grade (grade 10 offset).
   `sort_order` = period index.
4. **Cross-day balancing**: keep the per-day cap (`max_proctors_per_teacher`); rank proctor
   candidates by cumulative proctorship total across all placed days (lowest first, tie by id).
5. **Elective** is a subject flagged `is_elective`; it occupies its day/period but yields **no
   schedule rows / no external proctor**, and the grid renders it once per grade.
6. **Grid display**: day tabs on Show + Public, laid out per the image (grade band → subject+time
   group → section rows, subject teacher merged).
7. **Acceptance is rule-conformant**, not exact proctor names: exactly one proctor per exam,
   proctor != that exam's subject teacher, no teacher double-booked at overlapping times, per-day
   cap respected, load balanced cumulatively across days.

## Data model / migrations
Create after `2026_01_01_000015`. All columns are additive with defaults (or nullable), so a plain
`php artisan migrate` works on the populated dev DB; `migrate:fresh --seed` remains valid.

- `2026_01_01_000016_add_multi_day_fields_to_schedule_groups_table.php`
  - `days` unsignedTinyInteger default 3
  - `exams_per_day` unsignedTinyInteger default 3
- `2026_01_01_000017_add_day_to_schedules_table.php`
  - `day` unsignedTinyInteger default 1
  - drop unique `['schedule_group_id','section_id','start_time']`; add unique
    `['schedule_group_id','day','section_id','start_time']`
- `2026_01_01_000018_add_grade_level_to_time_preset_slots_table.php`
  - nullable `foreignId('grade_level_id')->constrained('grade_levels')->nullOnDelete()`
- `2026_01_01_000019_add_sort_order_to_schedule_group_subjects_table.php`
  - `sort_order` unsignedInteger default 0
- `2026_01_01_000020_add_is_elective_to_subjects_table.php`
  - `is_elective` boolean default false

## Backend
- **Models**
  - `ScheduleGroup` (`app/Models/ScheduleGroup.php`): add `days`, `exams_per_day` to `#[Fillable]`,
    cast to int; make `subjects()` use `->withPivot('sort_order')->withTimestamps()`.
  - `Schedule` (`app/Models/Schedule.php`): add `day` to `#[Fillable]`, cast int.
  - `TimePresetSlot` (`app/Models/TimePresetSlot.php`): add `grade_level_id` to `#[Fillable]`; add
    `gradeLevel()` BelongsTo.
  - `Subject` (`app/Models/Subject.php`): add `is_elective` to `#[Fillable]`, cast bool.
- **Requests**
  - `ScheduleGroupRequest`: add `days` (`required|integer|between:1,3`) and
    `exams_per_day` (`required|integer|between:1,10`).
  - `ScheduleRequest` (`app/Http/Requests/ScheduleRequest.php`): add `day`
    (`required|integer|min:1`).
- **`ScheduleGroupController`** (`app/Http/Controllers/ScheduleGroupController.php`)
  - `setup()`: validate/persist `days`, `exams_per_day`, `time_preset_id`, `sections`, and
    `subjects` as an **ordered id list**. Assign pivot `sort_order` = position within the subject's
    grade, then `sync([$id => ['sort_order' => $n], ...])`.
  - `generate()`: unchanged signature; it now deletes all schedules for the group (all days) and
    persists the multi-day report (entries carry `day`).
  - `create`/`edit`: pass `days`/`exams_per_day` defaults for new groups.
  - `show()`: eager-load `timePreset.slots` (with grade scope), `sections.gradeLevel`/`room`,
    `subjects` with pivot `sort_order` so the client can build the timetable and elective rows.
- **`ConflictChecker`** (`app/Services/ConflictChecker.php`)
  - Add `day` to entries and a `sameDay()` guard: only section/teacher overlap and proctor-cap
    checks apply between entries of the **same day**. Entries missing `day` are treated as day 1 so
    existing unit tests keep passing.

## Generator algorithm (`app/Services/ScheduleGenerator.php`)
Replace the per-section "earliest slot" pass with a fixed-timetable, day-major pass:

1. Load group; `days = group.days`, `epd = group.exams_per_day`; sections (with grade), subjects
   (with pivot `sort_order`), preset slots (with `grade_level_id`).
2. **Effective slots per grade**: if slots exist with `grade_level_id === grade.id`, use them;
   otherwise use the `grade_level_id === null` slots. Order by `sort_order`; index = period.
3. **Ordered subject list per grade**: pivot `sort_order`, tie-break `subject.id`.
4. Iterate `day = 1..days` → each grade (by `grade_level`) → `period = 0..epd-1` →
   `subject = orderedGradeSubjects[(day-1)*epd + period]`.
   - Skip `is_elective` subjects (no rows).
   - Missing slot for that grade/period → mark all sections of that grade unplaced
     (`reason: 'No time slot configured for this period.'`).
   - For each section of the grade (by id): resolve subject teacher from
     `section_subject_teacher`; if missing → unplaced.
   - **Proctor choice**: candidates = allow-list (or all teachers) minus the subject teacher, minus
     candidates already at the per-day cap, minus candidates with a same-day overlap conflict
     (as ST or proctor). Sort by cumulative proctor total across all placed days asc, then id asc;
     take the first. None → unplaced.
   - Record the placed entry with `day`, `slot_id`; update cumulative + per-day counters.
5. Return `['placed' => [...], 'unplaced' => [...], 'warnings' => [...]]`, entries including `day`.
   Determinism preserved (load asc, id asc).

## Frontend
- **`resources/js/Pages/ScheduleGroups/Show.vue`**
  - Setup panel: add `days` and `exams_per_day` inputs; list subjects **grouped by grade** with
    order controls (up/down); submit `subjects` as the ordered id list.
  - Grid: add **Day tabs**; inside a day render grade bands → subject+time groups → section rows
    with Proctor + Room and a merged Subject Teacher cell. Render elective subjects once per grade
    as Section `NA` / Proctor `Subject Teachers` / Room `Classroom Assignment`.
  - Add/Edit schedule modal: add a `day` select.
- **`resources/js/Pages/ScheduleGroups/Form.vue`**: add `days`/`exams_per_day` fields.
- **`resources/js/Pages/ScheduleGroups/Index.vue`**: optional Days column.
- **`resources/js/Pages/Public/Schedules.vue`**: Day tabs + the same read-only grouped layout.
- **`resources/js/Pages/TimePresets/Form.vue`**: add an optional grade scope select per slot.

## Seeders
- `DemoDataSeeder` (`database/seeders/DemoDataSeeder.php`)
  - "Standard Exam Day" preset: default slots 08:00-09:00 / 09:30-10:30 / 11:00-12:00
    (`grade_level_id` null) **plus** grade-10 slots 07:30-08:30 / 09:00-10:00 / 10:30-12:00.
  - Add a grade-10 `Elective` subject flagged `is_elective` and place it 3rd in grade-10 order
    (image order: Chemistry, Social Science 4, Elective, ...).
  - Set the demo group `days = 3`, `exams_per_day = 3`; attach subjects with per-grade `sort_order`
    matching the image (at least grade 10).
- `TimePreset` seeder/factory: keep `grade_level_id` defaulting to null.

## Tests
- `tests/Feature/Scheduling/ScheduleGeneratorTest.php`: adapt for the new fixed-timetable pass.
  Add: same subject lands on the same day/period for every section of a grade; per-grade slots are
  honoured (grade 10 uses its own times); cumulative balancing pushes day-1-heavy proctors later;
  per-day cap enforced; elective produces no rows; unplaced reported for missing slot/teacher.
- `tests/Feature/Scheduling/ConflictCheckerTest.php`: add cases proving cross-day non-overlap is
  allowed and same-day overlap is caught (entries without `day` still behave as day 1).
- `tests/Feature/Scheduling/ScheduleStoreTest.php`: cover the new `day` field.
- `database/factories/ScheduleFactory.php`: default `day => 1`; `ScheduleGroupFactory`: `days`/
  `exams_per_day` defaults.
- Keep all existing suites green.

## Validation commands
- `php artisan migrate` (additive) or `php artisan migrate:fresh --seed`
- `vendor\bin\pint app routes database tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual: create a group, set days/exams_per_day, order subjects per grade, generate; confirm day
  tabs, per-grade times, no double-booked teachers, cap, and the elective row.

## Risks / assumptions
- **Exact proctor names will not match the sample** (accepted). Repeats in the sample imply a
  smaller allow-list; shrinking the selected proctor pool reproduces that shape.
- Days 2-3 are assumed to use the same per-grade time bands and the **next sequential subject
  chunks**. Confirm when day 2/3 images are available.
- The image shows grade-10 `Chemistry 2` while the seed has `Chemistry 3`; subject naming is a data
  concern, not addressed here.
- Demo data spans grades 7-12; the image shows 7-10, so a full demo generation will include 11-12.
- `days` default 3 changes output for existing groups that generate again.

## Out of scope
- Scoping which teachers belong to which grade level for proctoring.
- Conflict detection across separate ScheduleGroups (balancing only within one exam group).
- Any change to the `max_proctors_per_teacher` meaning (still a per-day cap).
- Rewriting historical/archived schedules (fresh migration is acceptable).
