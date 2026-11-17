# Print / download the teacher load table as PDF

## Goal

On the admin Schedule Group show page, add **Print** and **Download PDF** actions to the
Teacher load panel while it is in **Table** view. Both produce the same server-generated
PDF (DomPDF) that mirrors the on-screen table: teachers with at least one teaching or
proctor row, their T/P/O counts with per-day breakdowns, exemption days, and their
Day/Time/Subject/Type rows.

## Current state

- Panel + table: `resources/js/Pages/ScheduleGroups/Show.vue` — panel at `:1051-1268`,
  toolbar `:1069-1127` (Cards/Table toggle, Select all, Fullscreen), table `:1183-1266`.
- Table data: `teacherLoadGroups` computed at `:375-461` (dedupe key
  `day-start-end-subjectId-type`, self-proctored = "Subject Teacher & Proctor", rows sorted
  by day/start/subject, teachers sorted by lastname/firstname/middlename/id).
- Load math already has a PHP mirror: `App\Services\ScheduleLoad::fromSchedules()`
  (`app/Services/ScheduleLoad.php:28`), used by `ScheduleGenerator`. Its JS twin is
  `resources/js/lib/scheduleLoad.js`.
- Exemption days come from the server prop `subjectTeacherLoadsByDay`, built by the private
  `ScheduleGroupController::subjectTeacherLoadsByDay()` (`:398`, called at `:158`) using
  `SubjectSlotPlanner::displayPlanForGroup()` + `SectionSubjectTeacher` rows.
- Existing PDF pipeline to extend: `ScheduleExportController::pdf()` (`:20`) renders
  `resources/views/exports/schedule-pdf.blade.php` (letterhead + `Barryvdh\DomPDF`) and is
  routed inside the `['auth','admin']` group at `routes/web.php:62-65`.
- No print button or print stylesheet exists anywhere in the app.

## Decisions

| Topic | Decision |
| --- | --- |
| Mechanism | Server-side DomPDF, same as the existing grid export. No new JS dependency. |
| Actions | Two anchors in the Table-view toolbar: `Print` opens `...?inline=1` in a new tab (PDF viewer + browser print), `Download PDF` downloads the attachment. |
| Visibility | Only when `teacherView === 'table'`; available for draft/active/archived groups. |
| Route | Admin-only `GET schedule-groups/{scheduleGroup}/export/teacher-load/pdf`, named `schedule-groups.export.teacher-load.pdf`. |
| Contents | Mirror Table view: only teachers with rows; header metadata + overall summary line; no zero-load teachers. |
| Selection state | Ignored: the proctor checkboxes only affect generation, never the load table or export. |
| Public page | Out of scope; no public route for teacher load. |

## PDF content spec

1. Letterhead: copy the exact markup/styles from `exports/schedule-pdf.blade.php:208-233`
   (base64 PSHS + Bagong Pilipinas logos, school lines). Duplicating it in the new blade
   avoids touching the verified grid export.
2. Report heading: `TEACHER LOAD REPORT`, group title, school year, quarter,
   `{days} day(s) × {exams_per_day} exams/day`, `Max proctorships/teacher/day`,
   `Generated {F j, Y}`.
3. Summary line: `Overall load: min {min}, max {max}, average {average}, spread {spread}`
   (average with 1 decimal, computed across **all** teachers, zero for idle ones — exactly
   like `overallSpread` in `Show.vue:65-81`).
4. Table (A4 portrait, columns `Teacher | Day / Time | Subject | Type`), one block per
   teacher with rows:
   - Teacher cell (rowspan over the teacher's rows): display name
     `Lastname, Firstname M.`; lines `T {n} ({D1 x · D2 y …})`,
     `P {n} ({D1 x …})`, `O {n} ({D1 x …})`; plus
     `Exempt on day(s) 1, 2` when applicable.
   - Row cells: `Day {day} · {h:mm AM}–{h:mm AM}`, subject name (`—` if missing), type
     badge text (`Subject Teacher`, `Proctor`, `Subject Teacher & Proctor`).
5. Empty state: if there are no rows at all, render
   `No schedules generated yet. Generate the grid or add schedules to see teacher load details.`
   inside the PDF (HTTP 200, not an error).
6. Page breaks: keep each teacher block unbroken where DomPDF allows
   (`page-break-inside: avoid` on the teacher `<tbody>`).

## Implementation

### 1. Extract the day-exemption computation

New `app/Services/SubjectTeacherDayLoads.php`:

- Move the body of `ScheduleGroupController::subjectTeacherLoadsByDay()` verbatim into
  `compute(array $subjectPlan, ScheduleGroup $scheduleGroup, Collection $assignments): array`.
- In `ScheduleGroupController::show()` replace the private call at `:158` with
  `app(SubjectTeacherDayLoads::class)->compute($subjectPlan, $scheduleGroup, $assignments)`
  and delete the private method (`:398`+). Add the `use` import.
- Keep the return shape `[teacherId => [day => distinctSubjectCount]]` unchanged so the
  Show payload is byte-for-byte identical.

### 2. Build the report data

New `app/Services/TeacherLoadReport.php` (`forGroup(ScheduleGroup $group): array`):

- Inject `ScheduleLoad`, `SubjectTeacherDayLoads`, `SubjectSlotPlanner`.
- `$group->loadMissing(['schedules.subject', 'schedules.subjectTeacher', 'schedules.proctor', 'sections', 'subjects', 'schoolYear', 'quarter', 'timePreset.slots', 'dayPresets.timePreset.slots'])`.
- `$teachers = Teacher::orderBy('lastname')->get()`; compute
  `$loads = $this->loads->fromSchedules($group->schedules, $teachers->pluck('id')->all())`.
- Exemption map: build `$assignments` exactly as `ScheduleGroupController::show()` does
  (`SectionSubjectTeacher` where section_ids ∩ group sections and subject_ids ∩ group
  subjects), then `$dayLoads = $this->dayLoads->compute($this->planner->displayPlanForGroup($group), $group, $assignments)`.
  Exempt days = days `1..days` where the count is `>= 2`.
- Rows: iterate `$group->schedules` mirroring `teacherLoadGroups` in `Show.vue:375-461`:
  - skip schedules with null `start_time`/`end_time`;
  - `selfProctored = proctor_id && subject_teacher_id && proctor_id === subject_teacher_id`;
  - add subject-teacher row (`Subject Teacher & Proctor` when self-proctored, otherwise
    `Subject Teacher`) and add the proctor row only when not self-proctored;
  - dedupe per teacher on `day|start|end|subjectId|type` (two sections taught at once =
    one row, even though both count in the load numbers);
  - sort rows by day, `start_time`, subject name.
- Keep only teachers with at least one row; sort them by lastname, firstname, middlename,
  id (the JS comparator).
- Summary: min/max/average/spread of `$loads['overall']` across **all** teacher ids
  (missing = 0), average rounded to 1 decimal in the blade.
- Return:
  ```php
  [
      'title' => string,
      'school_year' => ?string,
      'quarter' => ?string,
      'days' => int,
      'exams_per_day' => int,
      'max_proctors_per_teacher' => int,
      'summary' => ['min' => int, 'max' => int, 'average' => float, 'spread' => int],
      'teachers' => [
          [
              'id' => int,
              'name' => string,            // "Lastname, Firstname M."
              'teaching' => int, 'proctor' => int, 'overall' => int,
              'teaching_by_day' => array<int, int>,
              'proctor_by_day' => array<int, int>,
              'overall_by_day' => array<int, int>,
              'exempt_days' => array<int, int>,
              'rows' => [['day' => int, 'time' => string, 'subject' => string, 'type' => string], ...],
          ],
      ],
  ]
  ```

### 3. Controller + route

`app/Http/Controllers/ScheduleExportController.php` — add:

```php
public function teacherLoadPdf(ScheduleGroup $scheduleGroup, TeacherLoadReport $report): Response
{
    $pdf = Pdf::loadView('exports.teacher-load-pdf', ['report' => $report->forGroup($scheduleGroup)])
        ->setPaper('a4', 'portrait');
    $filename = $this->filename($scheduleGroup, 'teacher-load.pdf');

    return request()->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
}
```

- `filename()` already slugifies the title, so the file is `{slug}-teacher-load.pdf`
  (`midterm-schedule-teacher-load.pdf` in tests).
- No `ensureDownloadable()` call: the route is admin-only, and drafts are exportable from
  the admin page (same as the existing grid export).

`routes/web.php` — inside the `['auth','admin']` group, next to the existing exports:

```php
Route::get('schedule-groups/{scheduleGroup}/export/teacher-load/pdf', [ScheduleExportController::class, 'teacherLoadPdf'])
    ->name('schedule-groups.export.teacher-load.pdf');
```

### 4. Blade view

New `resources/views/exports/teacher-load-pdf.blade.php`, modeled on
`schedule-pdf.blade.php` (DejaVu Sans, same letterhead, muted palette). Render the spec
above. Helpers can be local `@php` functions: display time (`8:00 AM`), per-day
breakdown (`D1 2 · D2 1`), and an empty value `—`.

### 5. Toolbar buttons

`resources/js/Pages/ScheduleGroups/Show.vue`, in the right-side control cluster after the
Cards/Table toggle (`:1069-1087`), gated `v-if="teacherView === 'table'"`:

```html
<a :href="route('schedule-groups.export.teacher-load.pdf', group.id) + '?inline=1'"
   target="_blank" rel="noopener" class="...">Print</a>
<a :href="route('schedule-groups.export.teacher-load.pdf', group.id)"
   class="...">Download PDF</a>
```

Reuse the header export-link classes (`:786-797`) for visual consistency. No script changes.

### 6. Tests

- `tests/Feature/Scheduling/ScheduleExportTest.php` (reuse `scheduleExportContext()`):
  - admin gets the teacher load PDF: 200, `content-type` contains `application/pdf`,
    `content-disposition` contains `midterm-schedule-teacher-load.pdf`, body starts with
    `%PDF`;
  - `?inline=1` returns an `inline` disposition (print path);
  - guest is redirected to `route('login')`.
- New `tests/Feature/Scheduling/TeacherLoadReportTest.php` (service-level, avoids parsing
  PDFs) covering:
  - two sections, same subject/teacher/time, same proctor → teacher T=1, P=0, O=1 with
    1 row; proctor T=0, P=2, O=1 with 1 deduped row;
  - self-proctored schedule → one `Subject Teacher & Proctor` row and T=P=O=1;
  - summary min/max/average/spread includes idle teachers as 0;
  - a teacher with two distinct subjects on day 1 is listed with `exempt_days = [1]`
    (fixture needs a preset with two slots, two subjects, assignments, and schedules);
  - rows sorted by day then start time, teachers sorted by lastname.

## Risks / notes

- Do not duplicate load math: `ScheduleLoad` is the single PHP source of truth; the report
  must not reimplement T/P/O counting.
- Row dedupe is display-only (matches the table); counts still use every schedule row for
  proctoring.
- The existing grid PDF and Show props must stay unchanged; the exemption extraction is a
  pure move.
- Copying the letterhead markup duplicates ~25 lines of Blade/CSS. Acceptable here; do not
  refactor `schedule-pdf.blade.php` in this change.
- DomPDF `page-break-inside: avoid` support is limited; worst case a teacher block splits
  across pages without losing data.

## Validation

- `vendor\bin\pest` (full suite; baseline 161 tests / 649 assertions)
- `vendor\bin\pint --dirty`
- `npm run build`
- Manual smoke: open a group with generated schedules → Teacher load → Table → both buttons
  produce a PDF matching the on-screen rows, counts, and exemption days; a group with no
  schedules renders the empty-state PDF; draft group still downloads.

## Out of scope

- Public `Public\Schedules` teacher load export.
- Excel teacher load export.
- Exporting the Cards view.
- PDFs that honor the proctor checkbox selection or any view filters.
