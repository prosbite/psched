# School Year & Quarter Settings + Schedule Group linkage

## Goal
Add two standalone lookup tables, `school_years` and `quarters`, managed under a new **Settings**
section in the admin sidebar (one CRUD page each). Every schedule group must then carry a required
`school_year_id` and `quarter_id`, chosen on the create/edit form.

## Locked decisions
1. **Quarters are standalone** (not owned by a school year) and carry `start_date` / `end_date`.
2. **School years** carry a single `name` label only (e.g. `2025-2026`).
3. Schedule groups **require** both `school_year_id` and `quarter_id` (non-nullable FKs, required in
   `ScheduleGroupRequest` and the form).
4. **Settings menu = sidebar group with 2 pages** (`School Years`, `Quarters`), each following the
   existing Grade Levels CRUD pattern. Existing nav items stay where they are.
5. Deleting a school year/quarter that is **referenced by any schedule group is blocked** with a flash
   error (same pattern as `GradeLevelController::destroy`); FK uses `restrictOnDelete` as a safety net.
6. The seeder creates the **current school year + 4 quarters** (dates derived from `now()`); the demo
   schedule group is attached to them.

## Data model
- `school_years`: `id`, `name` (string, unique), timestamps.
- `quarters`: `id`, `name` (string, unique), `start_date` (date), `end_date` (date), timestamps.
- `schedule_groups`: add `school_year_id` FK → `school_years` (restrictOnDelete, not null) and
  `quarter_id` FK → `quarters` (restrictOnDelete, not null).

Models:
- `App\Models\SchoolYear`: `#[Fillable(['name'])]`, `scheduleGroups()` HasMany.
- `App\Models\Quarter`: `#[Fillable(['name','start_date','end_date'])]`, `casts` dates to `date`,
  `scheduleGroups()` HasMany.
- `App\Models\ScheduleGroup`: add `school_year_id`, `quarter_id` to `#[Fillable]`; add
  `schoolYear()` and `quarter()` BelongsTo.

Factories:
- `SchoolYearFactory` (`name` = current school year label), `QuarterFactory` (`name`, `start_date`,
  `end_date`).
- `ScheduleGroupFactory`: add `'school_year_id' => SchoolYear::factory()`,
  `'quarter_id' => Quarter::factory()` so existing tests keep working unchanged.

## Migrations
Create after `2026_01_01_000012`:
- `2026_01_01_000013_create_school_years_table.php`
- `2026_01_01_000014_create_quarters_table.php`
- `2026_01_01_000015_add_school_year_and_quarter_to_schedule_groups_table.php`

Caveat: the new `schedule_groups` columns are non-nullable, so this migration fails on a populated
table. The supported path is `php artisan migrate:fresh --seed` (documented dev workflow). Note this
in the task; if the real DB must be preserved, add the columns nullable, backfill each group to a
seeded year/quarter, then tighten to non-nullable — that is explicitly optional.

## Backend
1. **Controllers** `App\Http\Controllers\SchoolYearController` and `QuarterController` mirroring
   `GradeLevelController` (index/store/update/destroy, `Inertia::render`, redirect with `success`):
   - `index`: `SchoolYear::withCount('scheduleGroups')->orderByDesc('name')->get()` and
     `Quarter::withCount('scheduleGroups')->orderBy('start_date')->get()`.
   - `store`/`update` validation:
     - SchoolYear: `name` required, string, max 255, unique (`Rule::unique('school_years','name')->ignore($id)`).
     - Quarter: `name` required, string, max 255, unique; `start_date` required date;
       `end_date` required date, `after_or_equal:start_date`.
   - `destroy`: if `scheduleGroups()->exists()` → `back()->with('error', ...)`, else delete.
2. **Routes** (inside the `['auth','admin']` group in `routes/web.php`):
   `Route::resource('school-years', ...)`, `Route::resource('quarters', ...)`
   (`->except(['create','show','edit'])`).
3. **`ScheduleGroupRequest`**: add `school_year_id` and `quarter_id` as
   `['required','integer', Rule::exists(...)]`.
4. **`ScheduleGroupController`**:
   - `index`: also `with(['schoolYear','quarter'])`.
   - `create`/`edit`: also pass `schoolYears` (orderByDesc name) and `quarters` (orderBy start_date).
   - `show`: add `schoolYear`/`quarter` to the eager-load list.

## Frontend
1. New `resources/js/Pages/SchoolYears/Index.vue` and `resources/js/Pages/Quarters/Index.vue` copied
   from `GradeLevels/Index.vue` (table + modal form + Edit/Delete, `FlashBanner`). Add a
   "Schedule Groups" `withCount` column. Quarter form uses `TextInput type="date"` for
   `start_date`/`end_date` and shows `InputError`s including `end_date`.
2. `resources/js/Layouts/AdminLayout.vue`: add a `settingsNav` array
   (`school-years.index`, `quarters.index`) and render a **Settings** section heading + links below
   the existing `adminNav`, admin-only, honoring the collapsed state (hide heading text/keep icons).
3. `resources/js/Pages/ScheduleGroups/Form.vue`: add `schoolYears`/`quarters` props; add two required
   `<select>`s (`school_year_id`, `quarter_id`) with `InputError`s; include both in the `useForm` init.
4. `resources/js/Pages/ScheduleGroups/Index.vue`: add **School Year** and **Quarter** columns
   (`group.school_year?.name`, `group.quarter?.name`, `—` fallback); bump empty-row `colspan` to 9.
5. `resources/js/Pages/ScheduleGroups/Show.vue`: show the school year + quarter next to the status
   badge in the header (read-only).

## Seeders
- New `database/seeders/SchoolYearQuarterSeeder.php`: create the current school year (label from
  `now()`, e.g. `2026-2027` when month ≥ 6 else previous→current) and 4 quarters with approximate
  ranges (Q1 Aug–Oct, Q2 Nov–Jan, Q3 Jan–Mar, Q4 Apr–Jun); use `firstOrCreate` for idempotency.
- `DatabaseSeeder`: call `SchoolYearQuarterSeeder` before `DemoDataSeeder`.
- `DemoDataSeeder::seedScheduleGroup()`: attach the first school year and first quarter to the demo
  group (`SchoolYear::orderByDesc('name')->first()`, `Quarter::orderBy('start_date')->first()`).

## Tests
- `tests/Feature/Settings/SchoolYearTest.php`: admin index renders Inertia component; store/update
  happy path + unique-name validation; destroy blocked when a schedule group references it and
  allowed otherwise; guest redirected to login; non-admin 403.
- `tests/Feature/Settings/QuarterTest.php`: same, plus `end_date` before `start_date` fails validation.
- `tests/Feature/Scheduling/ScheduleGroupRequestTest.php`: `POST schedule-groups.store` without
  `school_year_id`/`quarter_id` fails validation; with both succeeds and persists them.
- Existing suites must still pass thanks to `ScheduleGroupFactory` defaults.

## Validation commands
- `php artisan migrate:fresh --seed`
- `vendor\bin\pint app routes database tests --format agent`
- `vendor\bin\pest --compact`
- `npm run build`
- Manual: Settings → School Years / Quarters CRUD; create a Schedule Group (both selects required);
  confirm Index/Show display them; try deleting a year/quarter in use → blocked with flash error.

## Out of scope
- Filtering the public `/schedules` page or any other query by school year/quarter.
- Scoping quarters to a school year.
- Unique constraints beyond `name` (e.g. no `(school_year, quarter, title)` rule).
- Backfilling preserved production data beyond the documented `migrate:fresh --seed` path.
