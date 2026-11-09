# Teacher Details Modal

## Goal
Clicking a teacher's name in the admin **Teachers** page and in the admin **schedule grid**
(`ScheduleGroups/Show.vue`) opens a modal that shows the teacher's identity, the subjects/sections
they handle, and every exam schedule they are involved in (as subject teacher or proctor).

## Locked decisions
1. **Placement**: admin Teachers page **and** the admin schedule grid. The public `/schedules` page is
   **out of scope** (no public endpoint — teacher schedules stay admin-only).
2. **Schedules shown**: all groups (draft/active/archived), **both roles** (subject teacher and
   proctor), grouped by schedule group with a status badge.
3. **Subjects handled**: grouped by subject, each listing the grade level and the sections the teacher
   covers it for (section-aware data).
4. **Data delivery**: fetched lazily from an admin-only JSON endpoint when the modal opens. No page
   reload, no preloading every teacher's data into page props.

## Backend
1. **Route** (inside the `['auth','admin']` group in `routes/web.php`):
   `Route::get('teachers/{teacher}/details', [TeacherController::class, 'details'])->name('teachers.details');`
2. **`TeacherController@details(Teacher $teacher): JsonResponse`** returning:
   - `teacher`: `{ id, firstname, middlename, lastname, email }`.
   - `subjects`: from `$teacher->sectionSubjectTeachers()->with(['section.gradeLevel','subject.gradeLevel'])->get()`,
     grouped by subject (dedupe repeated subjects across sections):
     ```
     [{ subject_id, subject_name, grade_level,
        sections: [{ id, section_name, grade_level }] }]
     ```
     Sort by `subject_name`, then `section_name`.
   - `schedules`: `Schedule::where('subject_teacher_id', $teacher->id)->orWhere('proctor_id', $teacher->id)`
     with `scheduleGroup`, `section.gradeLevel`, `section.room`, `subject` eager-loaded; map each:
     ```
     { id, role: 'subject_teacher'|'proctor', start_time, end_time,
       section: { id, section_name, room },
       subject: { id, subject_name },
       group: { id, title, status, status_label } }
     ```
     `role` = `proctor` when `proctor_id === teacher->id`, else `subject_teacher` (the same teacher
     cannot hold both on one row — `ConflictChecker` forbids `proctor == subject_teacher`).
     `status` = `status->value`, `status_label` = `status->label()` (emit the scalar explicitly rather
     than relying on enum JSON serialization). Sort by group id desc, then `start_time`, then `section_id`.
3. Route-model binding gives an automatic 404 for a missing teacher.

## Frontend
1. **New shared component `resources/js/Components/TeacherDetailsModal.vue`**
   - Props: `show` (Boolean), `teacherId` (Number|null); emits `close`.
   - Wrap the existing `Modal` (`max-width="2xl"`); when opened, `fetch(route('teachers.details', teacherId), { headers: { Accept: 'application/json' } })` (native fetch, Ziggy for the URL — no axios dependency).
   - Render three blocks: identity (name + email, `—` when null), **Subjects handled** (grouped list),
     **Exam schedules** (grouped by group, status badge using the same colors as `Show.vue`, rows:
     time range, section + room, subject, role label).
   - Loading / inline error (+ Retry) states; clear prior data when `teacherId` changes.
   - Empty states: "No subjects assigned." / "No exam schedules yet."
2. **`resources/js/Pages/Teachers/Index.vue`**
   - Turn the name cell into a `<button class="text-indigo-600 hover:text-indigo-900">` that sets
     `detailsTeacherId` and opens the modal.
   - Mount one `<TeacherDetailsModal :show="detailsOpen" :teacher-id="detailsTeacherId" @close="detailsOpen = false" />`.
3. **`resources/js/Pages/ScheduleGroups/Show.vue`**
   - Schedule grid: render subject-teacher / proctor names as buttons when the id is present
     (`schedule.subject_teacher_id` / `schedule.proctor_id`), otherwise `—`.
   - Proctor-load card: make each teacher row's name a button.
   - Mount the shared modal and add `openTeacher(id)`.

## Edge cases / failure modes
- No login/user or null email → show `—`.
- Null proctor on manual rows → not clickable.
- Fetch error → inline error with Retry; modal stays open.
- Repeated subject names across grades stay distinct because the group label includes grade.
- Deleted teacher → 404 from the endpoint (route binding).

## Tests
- New `tests/Feature/Scheduling/TeacherDetailsTest.php`:
  - admin `GET teachers/{id}/details` returns `subjects` grouped by subject with sections, and
    `schedules` containing the teacher's rows with the correct `role` (cover a teacher who both
    teaches and proctors, across two groups) and only that teacher's rows.
  - guest is redirected to login; authenticated non-admin gets **403**.
  - missing teacher → **404**.
  - Endpoint is plain JSON (no Inertia/Vite), so no `withoutVite()` needed.

## Validation commands
- `vendor\bin\pint app routes tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual: open Teachers → click a name → modal shows grouped subjects + schedules; open a schedule
  group → click a subject-teacher and a proctor name → same modal for that teacher; verify a teacher
  with no schedules shows the empty state.

## Out of scope
- Public `/schedules` page.
- Editing assignments or schedules from the modal (read-only).
- Pagination of a teacher's schedules.
