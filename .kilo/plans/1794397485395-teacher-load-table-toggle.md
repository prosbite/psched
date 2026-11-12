# Teacher load panel: cards/table toggle

## Goal

Add a toggle at the top of the **Teacher load** panel on `ScheduleGroups/Show.vue` to switch between the current card view and a tabular load-detail view. The table lists one row per teacher role assignment with columns: **Teacher**, **Day / Time**, **Subject**, **Section / Room**, **Type** (Subject Teacher, Proctor, or both).

## Decisions (confirmed with user)

- **Table still supports proctor selection**: rows are grouped by teacher; the first column spans that teacher's rows and contains the existing proctor checkbox + clickable name.
- **Role rows**: each persisted schedule yields a Subject Teacher row and a Proctor row. If the same teacher is both (self-proctored elective), render **one** combined row with type `Subject Teacher & Proctor`.
- **Scope/order**: only teachers with at least one assignment row appear. Teacher groups sort by **lastname**, then firstname, then middlename, then id. Within a teacher, rows sort by day, then start time, then subject name, then section name.
- **Teacher name format (table only)**: display `Lastname, Firstname M.` where `M.` is the uppercase middle initial. If there is no middlename, display `Lastname, Firstname`. The existing cards and schedule grid keep `Firstname Middlename Lastname`.
- **Teacher cell**: checkbox, clickable name, compact `T / P / O` counts (with per-day breakdown in the title), proctor-at-cap styling, and the existing `Exempt on day(s)` badge.
- Default view stays the current **cards**; toggle state is local (`ref`) and not persisted.
- Table shows **all days**, not just the active day tab.

## Data source (no backend changes)

`group.schedules` is already loaded in `ScheduleGroupController::show()` with `section.room`, `subject`, `subjectTeacher`, and `proctor`. Build the table entirely client-side from that prop.

Fields per schedule:
- `day`, `start_time`, `end_time`
- `subject.subject_name`
- `section.section_name`, `section.room.room_name`
- `subject_teacher_id` / `subjectTeacher`
- `proctor_id` / `proctor`

No new props, query changes, or migrations.

## Implementation steps

All changes in `resources/js/Pages/ScheduleGroups/Show.vue`.

1. **State**: add `const teacherView = ref('cards')`.

2. **Row builder**: add a `teacherLoadGroups` computed:
   - Loop `props.group.schedules ?? []`.
   - Skip schedules with neither `subject_teacher_id` nor `proctor_id`; skip rows whose teacher relation is missing.
   - If a subject teacher exists, push `{ teacher, day, start_time, end_time, subject, section, type }`.
   - If a proctor exists:
     - when `proctor_id === subject_teacher_id`, the already-pushed subject-teacher row gets type `Subject Teacher & Proctor` (do not push a second row);
     - otherwise push a separate `Proctor` row.
   - Group rows by teacher id, then sort the groups by teacher **lastname** (then firstname, middlename, id) before emitting. Append any schedule teacher not present in `props.teachers` at the end.
   - Sort each group's rows by `day`, then `start_time`, then subject name, then section name.
   - Expose `{ teacher, rows }` for the template.

3. **Helper formatters** (small functions near `teacherName`):
   - `teacherTableName(teacher)` → `Lastname, Firstname M.` using the uppercase first letter of `middlename` as the initial; omit the initial (and the comma) when names are missing. Do not change the existing `teacherName()` helper used by the cards/grid.
   - `loadRowTime(row)` → `Day {day} · {displayTime(start)}–{displayTime(end)}`.
   - `loadRowSection(row)` → `{section_name} · {room_name}` (fall back to `—`).
   - `loadTypeClass(type)` → distinct badge classes for Subject Teacher / Proctor / combined.

4. **Toggle UI**: in the Teacher load header, wrap the existing `Select all` control with a small segmented control (`Cards` / `Table`) using the same button styles as the schedule grid day tabs. Keep the heading, descriptive copy, and overall spread summary unchanged and always visible.

5. **Cards branch**: wrap the existing `<div class="mt-3 grid ...">` teacher cards in `v-if="teacherView === 'cards'"` unchanged.

6. **Table branch**: add `v-else` with `overflow-x-auto` + the project’s existing table classes:
   - Head: `Teacher | Day / Time | Subject | Section / Room | Type`.
   - `tbody` → `template v-for="group in teacherLoadGroups"` and `tr v-for="(row, index) in group.rows"`.
   - First `<td>` only when `index === 0`, with `:rowspan="group.rows.length"`: checkbox (bound to `selectedProctors`, `@change="toggleProctor(group.teacher.id)"`, title from `isSubjectTeacherExempt`/`exemptDays`), clickable name rendered with `teacherTableName(group.teacher)` calling `openTeacher(group.teacher.id)`, compact counts line (reuse `teachingLoad`, `proctorLoad`, `overallLoad`, `proctorAtCap`, `dayBreakdown`), and the exempt badge.
   - Remaining cells: formatted day/time, subject name, section/room, type badge.
   - Empty state row (`v-if="teacherLoadGroups.length === 0"`): “No schedules generated yet. Generate the grid or add schedules to see teacher load details.”

7. **Empty/edge handling**:
   - Pre-generation: no `group.schedules` → single empty-state row; `Select all` and the toggle remain usable.
   - Schedules with only one role: render only the existing role row.
   - Multiple sections of the same exam produce multiple rows (one per section) so section/room stays accurate; note this is detail-level, while the teacher counts remain the existing distinct-role totals.

## Validation

- `npm run build`.
- Manual smoke on a draft group:
  1. Toggle Cards → Table → Cards; header copy, spread summary, and `Select all` stay visible.
  2. A schedule with separate subject teacher and proctor shows two rows.
  3. A self-proctored elective shows one combined-type row.
  4. Teacher checkbox in the table updates the same selection used by Generate.
  5. Regenerating the grid refreshes the table (computed from `group.schedules`).
  6. Clicking a teacher name opens the existing `TeacherDetailsModal`.
  7. Multi-day group shows all days; rows are grouped by teacher, groups are sorted by lastname (then firstname), and each row is ordered by day/time.
  8. Teacher names render as `Lastname, Firstname M.` (and `Lastname, Firstname` without a middlename); the cards and grid still use the old format.
  9. Pre-generation draft shows the empty-state row.
- No Pest changes expected: frontend-only, backed by already-tested props.

## Risks / notes

- `subjectTeacher`/`proctor` relations may be null on manually-created schedules; skip missing roles rather than rendering blank type rows.
- Teaching counts are distinct busy slots while the table is per section; the counts in the teacher cell are intentionally the existing metrics, not a row count.
