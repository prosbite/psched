# Multi-day per-day time presets + whole-duration load balancing

## Goal

1. Let each day of a schedule group use a different time preset, with the group's existing preset as the default/fallback.
2. Keep the per-day proctor cap, but balance teaching + proctor work across the whole group and show per-day and overall totals so admins can judge whether the generated schedule is balanced.

## Decisions

- **Storage**: keep `schedule_groups.time_preset_id` as the default preset and add a `schedule_group_day_presets` table for per-day overrides. A day without an override uses the default.
- **Daily cap stays**: `max_proctors_per_teacher` remains a per-day cap. It is not removed or changed.
- **Overall total is a balancing target and display metric**, not a new hard cap.
- **Teaching load** = number of distinct busy slots (unique `day + start_time + end_time`) where the teacher is the subject teacher. Multiple sections of the same subject at the same time count as one slot.
- **Proctor load** = number of proctor assignments.
- **Overall load** = number of distinct busy slots where the teacher appears as subject teacher or proctor. A self-proctored elective slot counts once.
- **Keep the existing 2+ distinct-subject proctoring exemption** (distinct subjects, used only for proctor eligibility).
- If neither the day preset nor the default exists, that day's subjects are reported as `missing_slot`.
- **Subject-to-day assignment stays implicit and is out of scope**: the ordered per-grade subject list continues to be chunked by `exams_per_day` (`(day - 1) * exams_per_day + period`). Do not add a `day` column to `schedule_group_subjects`, a per-day subject picker, or subject auto-reflow. A day whose effective preset has more slots than `exams_per_day` simply leaves the extra periods unused; a day with fewer slots reports the overflow subjects as `missing_slot`.

## Data model

- Add migration `database/migrations/2026_01_01_000022_create_schedule_group_day_presets_table.php`:
  - `id`
  - `schedule_group_id` FK → `schedule_groups` cascade delete
  - `day` unsignedTinyInteger
  - `time_preset_id` nullable FK → `time_presets` nullOnDelete
  - timestamps
  - unique `(schedule_group_id, day)`
- Add `app/Models/ScheduleGroupDayPreset.php`:
  - Fillable: `schedule_group_id`, `day`, `time_preset_id`.
  - Casts: `day` integer.
  - Relations: `scheduleGroup()` belongsTo, `timePreset()` belongsTo.
- Add to `app/Models/ScheduleGroup.php`:
  - `dayPresets(): HasMany` ordered by `day`.

## Backend

### Schedule group create/edit/setup

- `app/Http/Requests/ScheduleGroupRequest.php`:
  - Add `day_presets` as `array`.
  - Add `day_presets.*` as `nullable|integer|exists:time_presets,id`.
- `app/Http/Controllers/ScheduleGroupController.php`:
  - Add `syncDayPresets(ScheduleGroup $group, array $dayPresets, int $days)`:
    - Normalize keys to integers.
    - Delete all existing `dayPresets()` rows.
    - For each day `1..days`, create a row only when a preset id is present.
  - `store()`: pull `day_presets` out of the validated data before `ScheduleGroup::create()`, then sync after creation.
  - `update()`: same, using the validated `days`.
  - `setup()`: validate `day_presets`, update the group, sync day presets alongside the sections/subjects sync.
  - `edit()`: eager-load `dayPresets` so the form can prefill.
  - `show()`: eager-load `dayPresets.timePreset.slots` in addition to `timePreset.slots`.
- `app/Http/Controllers/PublicScheduleController.php`:
  - Eager-load `dayPresets.timePreset.slots` for each active group.

### Generator: per-day slots

- `app/Services/ScheduleGenerator.php`:
  - `loadMissing(['timePreset.slots', 'dayPresets.timePreset.slots'])`.
  - Resolve a day's preset: `dayPresets->firstWhere('day', $day)?->timePreset ?? $group->timePreset`.
  - Build `$daySlotsByGrade[$day][$gradeId]` for all days (grade-specific slots override the preset's default slots, as today).
  - Use the day's effective slots inside the day/grade/period loop.
  - Update the `missing_slot` reason/report; no schema change.

### Generator: overall load balancing

- Precompute teaching busy slots per teacher before placing anything:
  - Walk every day, grade, period, subject, and enrolled section with a teacher and a valid slot.
  - Add a unique key `day|start_time|end_time` per teacher.
  - Count the unique keys → `$teachingBusyTotals[teacherId]`.
  - Include electives.
- Initialize `$loadTotals = $teachingBusyTotals`.
- Replace `$proctorTotals` with `$loadTotals` in `place()` and `orderByLoad()`.
- When a normal subject is placed with a proctor, increment `$loadTotals[$proctorId]`.
- When an elective is self-proctored, do **not** increment `$loadTotals`; the slot is already counted as a teaching slot.
- Keep `ConflictChecker::proctorCapExceeded()` unchanged (per-day cap).
- Keep `subjectTeachingExemptions()` unchanged (2+ distinct subjects).
- The result: proctors are chosen by lowest overall distinct busy-slot count, tie-break lowest id, while still respecting the per-day cap and conflicts.

### Load counts for the admin panel

- Add `app/Services/ScheduleLoad.php`:
  - `public function fromSchedules(Collection $schedules, array $teacherIds): array`.
  - Returns:
    - `teaching: [teacherId => int]` (distinct slots)
    - `proctor: [teacherId => int]` (assignments)
    - `overall: [teacherId => int]` (distinct slots in either role)
    - `teaching_by_day`, `proctor_by_day`, `overall_by_day`: `[teacherId => [day => int]]`
  - Use a slot key `day|start_time|end_time`; a self-proctored elective adds the same key to the teacher's teaching and proctor sets, and the overall set counts it once.
  - Add a static `slotKey(int $day, string $start, string $end): string` for reuse by the generator.
- `ScheduleGroupController::show()`:
  - Compute the above for the group's schedules and all teacher ids.
  - Pass `teachingLoads`, `proctorLoads`, `overallLoads`, `teachingLoadsByDay`, `proctorLoadsByDay`, `overallLoadsByDay`.
  - Keep `subjectTeacherLoads` (distinct subjects) for the existing exemption check.

### Grid / exports

- `app/Services/ScheduleGrid.php`:
  - Load `dayPresets.timePreset.slots`.
  - Resolve the day's preset and grade slots per day instead of once for the whole group.
  - Keep the existing grade-specific/default slot fallback.

## Frontend

### `resources/js/Pages/ScheduleGroups/Form.vue`

- Add `day_presets` to the form, initialized from `props.group?.day_presets` as `{ day: preset_id }` (empty for create).
- Keep the existing "Time Preset" select as the **default** preset.
- Add one select per exam day (`v-for="day in Number(form.days) || 1"`):
  - Option `Use default` (empty value).
  - The preset list.
- Label the default clearly (e.g. "Default Time Preset") and the per-day fields "Day 1 preset", etc.
- Submit includes `day_presets`.

### `resources/js/Pages/ScheduleGroups/Show.vue`

- Add `day_presets` to `setupForm`, initialized from `props.group.day_presets`.
- Setup panel:
  - Keep the default preset select.
  - Add one per-day preset select with `Use default`.
  - Save sends `day_presets`.
- Header summary:
  - Show the default preset and any per-day overrides (e.g. "Default: X · Day 1: Y · Day 2: default").
  - Change the cap label to "Max proctorships/teacher/day".
- Grid:
  - Change `timeSlotsForGrade(gradeId)` to `timeSlotsForGrade(day, gradeId)`, resolving the day's preset (`day_presets` override, else `time_preset`).
  - `dayBlocks` passes the day.
  - The "Fix time preset" link should target the day's preset id (fall back to the default).
- Teacher load panel:
  - Add props `teachingLoads`, `proctorLoads`, `overallLoads`, `teachingLoadsByDay`, `proctorLoadsByDay`, `overallLoadsByDay`.
  - Per teacher, show:
    - Teaching slots per day and total.
    - Proctor count per day against `max_proctors_per_teacher` and total.
    - Overall total (teaching + proctor).
  - Add a small summary at the top of the panel: min/max/average/spread of `overallLoads` across the teacher list, so balance is visible.
  - Keep the exemption badge and the existing `subjectTeacherLoads` count.

### `resources/js/Pages/Public/Schedules.vue`

- Change `timeSlotsForGrade(group, gradeId)` to `timeSlotsForGrade(group, day, gradeId)`, resolving the day's preset from `group.day_presets` (override) or `group.time_preset`.
- `buildBlocks(group, day)` passes the day.

## Tests

- `tests/Feature/Scheduling/ScheduleGroupDayPresetTest.php`:
  - Create a group with per-day presets and assert rows are persisted.
  - Update `days` and assert overrides beyond the new day count are removed.
  - Setup syncs per-day presets.
  - A `null`/empty day value removes the override.
- `ScheduleGeneratorTest` additions:
  - Day 1 uses preset A times and day 2 uses preset B times.
  - A day without an override falls back to the default preset.
  - No preset/default for a day → `missing_slot` unplaced rows.
  - The per-day proctor cap still applies.
  - Overall proctor balancing uses teaching busy slots; a teacher teaching the same subject to multiple sections at the same time is not over-penalized.
  - Elective self-proctoring does not double-count the overall load.
- `tests/Feature/Scheduling/ScheduleLoadTest.php`:
  - Distinct teaching slots, proctor counts, overall union, and per-day breakdowns.
  - Self-proctored elective counts once overall.
- `ScheduleGridTest` / export tests:
  - The grid uses each day's preset times.
- Existing tests should keep passing through the default-preset fallback.

## Validation

- Run `php artisan migrate` (run only the new migration; do **not** use `migrate:fresh`).
- `vendor\bin\pint app routes database tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual smoke:
  - Create/edit a group with a default preset and different day overrides.
  - Generate a multi-day group; verify each day's times.
  - Verify the teacher load panel shows per-day and overall totals and the spread summary.
  - Verify the public page and PDF/Excel show each day's times.

## Risks / notes

- The generator's teaching busy-slot pre-pass must use exactly the same day/grade/period/subject ordering as placement; otherwise the balances will be wrong.
- Day override keys may arrive as strings from JSON; normalize to integers.
- If a per-day preset is deleted, the row remains with `time_preset_id = null`; the resolver must fall back to the default.
- The overall load is a balancing target, not a cap; some spread can remain because of the per-day cap, conflicts, and fixed subject-teacher assignments.
- Do not change `max_proctors_per_teacher` semantics; keep it per day.
- The teacher load panel counts are based on persisted schedules (post-generation); before generation, teaching/proctor totals are zero while the existing distinct-subject exemption count remains available.
