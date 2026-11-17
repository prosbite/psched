# Copy a previous group's setup while creating a new schedule group

## Goal

When creating a new schedule group, let the user pick an existing group and reuse its
saved setup instead of re-selecting the preset, days, exams per day, per-day presets,
sections, and ordered subjects.

Teacher assignments do **not** need to be copied: `section_subject_teacher` is keyed by
`section_id + subject_id` globally (see `database/migrations/2026_01_01_000006_create_section_subject_teacher_table.php`),
not by schedule group. Reusing the same sections and subjects therefore reuses the same
teacher assignments automatically.

## Current state

- The create/edit form `resources/js/Pages/ScheduleGroups/Form.vue` only edits group
  metadata (title, school year, quarter, preset, days, exams/day, max proctors, day
  presets). Sections/subjects are configured later on `ScheduleGroups/Show`.
- `ScheduleGroupController::create()` (`:46-54`) does not pass any group list.
- `ScheduleGroupController::store()` (`:59-75`) creates the group and saves day presets
  only.
- An uncommitted Show-page addition already exists in the working tree:
  `ScheduleGroupController::copyableGroups()` (`:331-372`) and a "Copy setup from a
  previous group" block in `Show.vue` (`~:109-155` script, `~:884-905` template) that
  prefills the draft group's setup form client-side. Keep it; the new create flow will
  reuse the same payload helper. Its test is `tests/Feature/Scheduling/ScheduleGroupCopySetupTest.php`.

## Decisions

| Topic | Decision |
| --- | --- |
| Entry point | On the New Schedule Group form only (`v-if="!group"`), above the Title field: a "Copy setup from" select + "Use setup" button. |
| What is prefilled | `time_preset_id`, `days`, `exams_per_day`, `max_proctors_per_teacher`, `day_presets`. Fields stay editable after prefill. |
| What is copied server-side | Sections (`schedule_group_sections`) and subjects with `sort_order` (`schedule_group_subjects`), on save. |
| Teacher assignments | Not copied; they are global per section+subject and already apply. |
| Not copied | Title, description, school year, quarter, schedules, unplaced rows, generation report. |
| Timing | Single `POST` to `schedule-groups.store`; no extra endpoint. |
| Existing groups | All existing groups are selectable (draft/active/archived); UI hides groups with neither sections nor subjects. |
| Show-page copy block | Keep as a secondary path for existing draft groups. |

## Implementation

### 1. Backend payload helper

`app/Http/Controllers/ScheduleGroupController.php`

- Change `copyableGroups()` to accept an optional exclusion: `copyableGroups(?ScheduleGroup $exclude = null)`, using
  `->when($exclude !== null, fn ($query) => $query->where('id', '!=', $exclude->id))`.
- Add `'max_proctors_per_teacher' => (int) $group->max_proctors_per_teacher` to each item
  (needed by the create-form prefill).
- Call `'copyableGroups' => $this->copyableGroups()` in `create()` alongside the existing
  `timePresets`, `schoolYears`, `quarters` props.
- `show()` keeps calling `$this->copyableGroups($scheduleGroup)`.

### 2. Request validation

`app/Http/Requests/ScheduleGroupRequest.php`

- Add to `rules()`:
  `'copy_setup_from' => ['nullable', 'integer', Rule::exists('schedule_groups', 'id')]`.

### 3. Copy on store

`app/Http/Controllers/ScheduleGroupController.php::store()` (`:59-75`)

- Pull `$copyFrom = $data['copy_setup_from'] ?? null;` then `unset($data['day_presets'], $data['copy_setup_from']);`.
- Wrap creation + `syncDayPresets()` + copy in `DB::transaction()` (`DB` is already imported):
  - Create the group with the submitted metadata and `status` Draft.
  - If `$copyFrom !== null`, load `ScheduleGroup::with(['sections', 'subjects'])->findOrFail($copyFrom)` and:
    - `$group->sections()->sync($source->sections->pluck('id')->all());`
    - `$group->subjects()->sync($source->subjects->mapWithKeys(fn (Subject $subject): array => [
      $subject->id => ['sort_order' => (int) $subject->pivot->sort_order],
      ])->all());`
- Keep the same flash message and redirect to `schedule-groups.show`.
- In `update()`, also `unset($data['copy_setup_from']);` so an edit submit can never mass-assign it.

### 4. Create/edit form

`resources/js/Pages/ScheduleGroups/Form.vue`

- Add prop `copyableGroups: { type: Array, default: () => [] }`.
- Add `copy_setup_from: null` to the `useForm({...})` payload.
- Add `copySourceId = ref('')`, `copyNotice = ref('')`, `copyableSources` computed (filter
  `section_ids.length || subject_ids.length`), and `copySource` computed by id.
- `watch(copySourceId, ...)`: clear `copyNotice` and set `form.copy_setup_from = null` so a
  changed selection is not silently applied until "Use setup" is pressed.
- `applyCopiedSetup()`:
  - Set `form.time_preset_id = source.time_preset_id ?? ''`.
  - Set `form.days`, `form.exams_per_day`, `form.max_proctors_per_teacher` from the source.
  - Build `form.day_presets` for days `1..days` (default `''`), overlaying the source's
    `day_presets` within range.
  - Set `form.copy_setup_from = source.id`.
  - Set `copyNotice` to note the copied sections/subjects counts and that they apply on save.
- Template: insert a bordered block at the top of the `<form>` with
  `v-if="!group && copyableSources.length > 0"`: label "Copy setup from a previous group",
  select + `SecondaryButton type="button" :disabled="!copySource" @click="applyCopiedSetup"`,
  hint text, `copyNotice`, and `InputError :message="form.errors.copy_setup_from"`.

### 5. Tests

Extend `tests/Feature/Scheduling/ScheduleGroupCopySetupTest.php`:

- `creating a group from a previous setup copies its sections and ordered subjects`
  - Build source with preset, day presets, 2 sections, subjects with distinct `sort_order`.
  - `POST route('schedule-groups.store')` with full required payload plus
    `copy_setup_from => $source->id` and the prefilled metadata.
  - Assert redirect to `schedule-groups.show`, new group's `sections` ids and
    `subjects` pivot `sort_order` match the source, day presets persisted, metadata saved.
- `creating a group rejects an unknown setup source`
  - `copy_setup_from => 999999` → `assertSessionHasErrors('copy_setup_from')`.
- `the create page lists groups whose setup can be copied`
  - `withoutVite()->get(route('schedule-groups.create'))` → Inertia component
    `ScheduleGroups/Form` and `copyableGroups` includes the source with its
    `section_ids`, `subject_ids`, and `max_proctors_per_teacher`.
- Keep the existing show-page payload test.

## Validation

- `vendor\bin\pest`
- `vendor\bin\pint --dirty` (or targeted `pint` on the changed PHP files)
- `npm run build`
- Manual smoke:
  1. Create a group with sections/subjects configured and save its setup.
  2. Create a second group, pick the first in "Copy setup from", click "Use setup".
  3. Confirm the metadata fields prefill, enter title/year/quarter, save.
  4. The Show page setup panel shows the same preset, days, exams/day, day presets,
     sections, and subject order; generating a grid works without reconfiguring.
  5. Repeat create with no source selected and confirm the group starts empty.

## Risks / notes

- Server copies only sections/subjects; the prefilled metadata comes from the form. If the
  user edits the prefilled fields before saving, their edits win. This is intentional.
- Source groups' sections/subjects always exist because the pivot tables cascade on
  delete, so `sync()` cannot receive dangling ids.
- The Show-page copy block remains; it uses the same payload and is unaffected.
- Copying teacher assignments into *new* sections is a different feature and out of scope.
