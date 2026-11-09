# Section-Aware Teacher Assignments + Proctor Selection

## Goal
Change the teacher↔subject relationship so it includes the **section** (making each assignment
unique per section+subject), derive the schedule's `subject_teacher` from that mapping instead of a
manual dropdown, and let the admin choose which teachers are eligible **proctors** when
auto-generating a schedule group.

## Locked decisions
1. **Replace** the `teacher_subject` pivot with a section-aware table `section_subject_teacher`:
   `id, section_id FK(sections, cascade), subject_id FK(subjects, cascade), teacher_id FK(teachers, nullOnDelete), timestamps, unique(section_id, subject_id)`.
   One teacher per section+subject (matches the source data, where every `(section, subject)` row has a single teacher).
2. `subject_teacher` on a schedule is **not** chosen by the user anymore. It is resolved from
   `(section_id, subject_id)` in the new mapping.
3. Auto-generation can be restricted to a **subset of proctors**. Only checked teachers may fill the
   `proctor` role; the `subject_teacher` role is still fixed by the mapping. Default = all teachers checked.
4. Greenfield project → edit/replace the initial migration; reset with `migrate:fresh --seed` (no data-preserving migration).

## Schema / migrations
- Delete `database/migrations/2026_01_01_000006_create_teacher_subject_table.php`.
- Add `database/migrations/2026_01_01_000006_create_section_subject_teacher_table.php` (same timestamp slot keeps ordering after `sections` 000004 and `subjects` 000005):
  - `section_id` FK → `sections` (`cascadeOnDelete`)
  - `subject_id` FK → `subjects` (`cascadeOnDelete`)
  - `teacher_id` FK → `teachers` (`nullOnDelete`)
  - `unique(['section_id', 'subject_id'])`

## Models
- Add `App\Models\SectionSubjectTeacher` (`#[Fillable(['section_id','subject_id','teacher_id'])]`), `belongsTo` Section, Subject, Teacher.
- Remove from `Teacher`: `subjects()` belongsToMany (and the `teacher_subject` pivot). Add `sectionSubjectTeachers(): HasMany` (and optional `sections()` through it).
- Remove from `Subject`: `teachers()` belongsToMany. Add `sectionSubjectTeachers(): HasMany`.
- Add to `Section`: `sectionSubjectTeachers(): HasMany`.
- `GradeLevel::subjects()` / `sections()`, `ScheduleGroup::subjects()` / `sections()` (pivot tables) are unrelated — leave them.
- Factories: add `SectionSubjectTeacherFactory`; update nothing else.

## Seeder (`database/seeders/DemoDataSeeder.php`)
- Replace `SUBJECT_TEACHERS` (grade → subject → teacher list) with a **section-aware dataset** that
  reproduces the raw data as `(grade, subject, section, teacher)` rows (the flattened 1–3 teacher lists
  lost the per-section distinction, e.g. Mathematics 1: Aquamarine/Opal/Turquoise → Almocera, Sapphire → Pascual).
  - Encode it faithfully from **all** raw JSON batches combined (the initial `section`-bearing dataset plus every later batch).
  - Simplest form: a flat array of `['grade'=>'7','subject'=>'Mathematics 1','section'=>'Aquamarine','teacher'=>'Almocera, Divine Faith G.']`.
- `seedSubjects()` / `seedSections()` / `seedTeachers()` stay as-is.
- New `assignSubjectTeachers()`: for each row, resolve Section (by name+grade), Subject (by name+grade), Teacher (by data key) and `SectionSubjectTeacher::updateOrCreate(['section_id'=>…,'subject_id'=>…], ['teacher_id'=>…])` (idempotent, enforces one teacher per pair).
- `seedScheduleGroup()` unchanged.

## Manual Add / Edit Schedule (`resources/js/Pages/ScheduleGroups/Show.vue`, `ScheduleRequest`, `ScheduleController`)
- **UI**: remove the Subject Teacher `<select>` (Show.vue lines ~378–389). Replace with a **read-only** display of the teacher assigned to the currently selected `(section_id, subject_id)`, plus a "No teacher assigned" state that disables Save.
  - Needs the mapping in the page props. `ScheduleGroupController@show` passes `assignments` (list of `{section_id, subject_id, teacher:{id, firstname, middlename, lastname}}`) for the group's sections/subjects; the component builds a `section_id-subject_id` lookup.
  - `scheduleForm` drops `subject_teacher_id`; remove `openEditSchedule` assignment of it and the `InputError`.
- **Server**: stop trusting a client `subject_teacher_id`.
  - `ScheduleRequest`: remove `subject_teacher_id` from `rules()` (and from the candidate built in `withValidator`; use the derived id). Update `validateSubjectMatchesSection` to also require an assignment to exist, else add a `subject_id` error ("No teacher is assigned to this subject for the section.").
  - `ScheduleController::payload()`: derive `subject_teacher_id` via `SectionSubjectTeacher::where(section_id, subject_id)->value('teacher_id')`.
  - Conflict check in `ScheduleRequest` must use that derived id.

## Generator (`app/Services/ScheduleGenerator.php`, `ScheduleGroupController@generate`)
- Signature: `generate(ScheduleGroup $group, ?array $proctorIds = null): array` (`null` = all teachers).
- Remove `qualifiedTeachers()` and the teaching-load balancing. For each `(section, subject)` task, look up the exact `subject_teacher_id` from `section_subject_teacher` (preload a `section_id-subject_id → teacher_id` map for the group's sections). If none → unplaced with reason `No teacher is assigned to "<subject>" for "<section>".`
- Proctor candidates = `Teacher` ids intersected with `$proctorIds` (when provided); order by least proctor load then lowest id. Keep proctor cap, section overlap, teacher overlap, and `proctor != subject_teacher` checks.
- `ScheduleGroupController@generate`: validate `proctors` → `['required','array','min:1']`, `proctors.*` → `exists:teachers,id`; pass to the generator. (Keep clearing draft schedules + flashing the report.)
- `teachingLoad` bookkeeping can be dropped; keep `proctorLoad`.

## Proctor checklist UI (`resources/js/Pages/ScheduleGroups/Show.vue`)
- In the "Teacher proctor load" card: add a "Select all" checkbox in the header and a checkbox per teacher row; initialize all checked (`selectedProctors` = all teacher ids, set in `onMounted`).
- "Select all" toggles all; individual toggles update the set; show indeterminate state when partially selected.
- The **Generate** action posts `{ proctors: [...selectedProctors] }` (instead of `{}`). Disable Generate when none are checked.
- Selection is transient (resets to all on reload). Optional follow-up: persist per-group in `localStorage`.

## Assignment management UI (recommended placement)
Because `teacher_subject` is removed, the Teachers page subject checkboxes no longer apply.
- **Recommended**: manage assignments on the **Sections** page — per section, assign a teacher for
  each subject of that section's grade level (Section + Subject → Teacher). Add an "Assign Teachers"
  action/modal per section row; a small `SectionSubjectTeacherController` (or extend `SectionController`)
  with an upsert endpoint.
- Update `Teachers/Index.vue` and `TeacherController` to drop the `subjects` field/sync (teacher identity + login only).
- Update `Subjects/Index.vue`: replace `teachers_count` with the number of assignments (`withCount('sectionSubjectTeachers')`), relabel column.

## Tests
- Update `tests/Feature/Scheduling/ScheduleGeneratorTest.php`:
  - Replace `$teacher->subjects()->attach(...)` with `SectionSubjectTeacher::factory()` rows for each `(section, subject)`.
  - "no qualified teacher" → "no teacher assigned": assert unplaced reason mentions the missing assignment.
  - Keep section-overlap / teacher-overlap / proctor-cap behaviour; add a test that only the passed `$proctorIds` are used (unchecked proctor never appears).
  - Update the generate-endpoint test to POST `proctors`.
- Add `tests/Feature/Scheduling/SectionSubjectTeacherTest.php`: unique(section_id, subject_id) enforced; relationships resolve.
- Add a feature test that storing a schedule derives `subject_teacher_id` from the mapping and returns a validation error when unmapped.
- Existing tests touching `teacher->subjects()` (only the generator test) must be updated; all other suites unaffected.

## Validation / commands
- `php artisan migrate:fresh --seed`
- `vendor\bin\pint app database routes tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual: open a draft group → confirm read-only teacher updates when Section+Subject change; uncheck some proctors → Generate → verify unchecked teachers never appear as proctor and the report matches; add a manual schedule for an unmapped pair → blocked with a clear error.

## Risks / edge cases
- **Data reconstruction**: the section-aware seed must be rebuilt from all raw batches; the current flattened
  `SUBJECT_TEACHERS` cannot be automatically split back into per-section teachers. Verify counts (assignments = number of distinct `(section, subject)` rows in the source).
- **Feasibility**: fixed subject teachers reduce generator freedom (a teacher tied to many sections may force
  unplaced items). This is expected and surfaced in the report.
- **Unmapped pairs**: sections/subjects selected in a group without an assignment cannot generate or be added manually (blocked with an error).
- **Deleting a section/subject** cascades its `section_subject_teacher` rows; deleting a teacher nulls the assignment's `teacher_id`, which then makes those pairs unmapped (surfaces in generate report / manual validation).
- Removing `teacher_subject` invalidates any external references (none outside this repo).

## Open questions
1. Assignment editor placement: Sections page (recommended) vs a dedicated "Teacher Assignments" page vs the Subjects page.
2. Should the proctor checklist persist per group (localStorage) or stay transient? (Recommended: transient.)
3. Should Generate be hard-blocked when zero proctors are checked (recommended: yes), or allowed to report everything unplaced?
