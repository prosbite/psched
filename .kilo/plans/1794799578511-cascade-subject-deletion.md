# Cascade subject deletion (remove subject from scheduling)

## Goal

Allow an admin to delete any subject. Deleting it must remove the subject's scheduling
data instead of being blocked by the current guard, matching the existing cascade
behaviour of the other subject foreign keys.

## Current behaviour / root cause

`SubjectController::destroy()` (`app/Http/Controllers/SubjectController.php:127-136`)
refuses deletion when the subject has schedules or schedule groups:

```php
if ($subject->schedules()->exists() || $subject->scheduleGroups()->exists()) {
    return back()->with('error', 'This subject is used by a schedule group or schedule and cannot be deleted.');
}
```

At the database level, three subject FKs already cascade:

| Table | Column | On subject delete |
| --- | --- | --- |
| `section_subject_teacher` (`..._000006`) | `subject_id` | cascade |
| `schedule_group_subjects` (`..._000011`) | `subject_id` | cascade |
| `schedule_group_unplaced` (`..._000021`) | `subject_id` | cascade |
| `schedules` (`..._000012`) | `subject_id` | **restrict** (the only blocker) |

So the only schema change needed is `schedules.subject_id` → `cascadeOnDelete`. That
automatically removes placed schedules, and the other tables clean up via their
existing cascades. No application-level loop is needed.

## Decisions

| Topic | Decision |
| --- | --- |
| Delete semantics | Hard delete: subject row plus its schedules/unplaced rows/group links/teacher assignments are removed. |
| Blocking rules | Removed entirely. Subject deletion is allowed even if the subject is in an active/archived schedule group. |
| Schema approach | Change `schedules.subject_id` FK to cascade (new migration), rather than manually deleting children in the controller. |
| Schedule groups | Groups themselves are not deleted; only the subject link is removed via cascade. |
| Confirmation | Keep the existing native `confirm()` prompt (the app's convention for every delete) but make it an explicit "Are you sure..." warning that schedules and assignments are removed too. No new modal component. |

## Implementation

### 1. Migration

Add `database/migrations/2026_01_01_000024_cascade_schedules_on_subject_delete.php`
(latest existing migration is `2026_01_01_000023_...`):

```php
public function up(): void
{
    Schema::table('schedules', function (Blueprint $table) {
        $table->dropForeign(['subject_id']);
    });

    Schema::table('schedules', function (Blueprint $table) {
        $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('schedules', function (Blueprint $table) {
        $table->dropForeign(['subject_id']);
    });

    Schema::table('schedules', function (Blueprint $table) {
        $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
    });
}
```

Keep the two `Schema::table` calls separate (drop then add) so SQLite's alter/rebuild
handling is applied cleanly. Laravel 13's `SQLiteGrammar` supports `dropForeign` via
table alteration.

### 2. Controller

`app/Http/Controllers/SubjectController.php:127-136` — remove the guard and keep the
delete + redirect:

```php
public function destroy(Subject $subject): RedirectResponse
{
    $subject->delete();

    return redirect()->route('subjects.index')->with('success', 'Subject deleted.');
}
```

### 3. UI confirmation

`resources/js/Pages/Subjects/Index.vue:50-54` already gates deletion behind a native
`confirm()`. Every other page in the app uses the same pattern
(`ScheduleGroups/Index.vue:20`, `Sections/Index.vue:51`, etc.), so keep it and only
make the wording explicit:

```js
const destroy = (subject) => {
    if (confirm(`Are you sure you want to delete "${subject.subject_name}"? Its schedules and teacher assignments will also be removed.`)) {
        form.delete(route('subjects.destroy', subject.id));
    }
};
```

No custom confirmation modal or new component is needed.

### 4. Tests

Add to `tests/Feature/SubjectTest.php`:

1. **Subject with schedules, unplaced rows, group link and teacher assignment deletes
   everything.** Build grade/section/room, subject, teacher, `SectionSubjectTeacher`,
   time preset + slot, `ScheduleGroup` attached to the section and subject, one
   `Schedule` for the subject, one `ScheduleGroupUnplaced` row, and a second unrelated
   subject + schedule. Act as admin, `delete(route('subjects.destroy', $subject))`.
   Assert:
   - redirect to `subjects.index`;
   - subject row gone;
   - `schedule_group_subjects`, `section_subject_teacher`, `schedules`,
     `schedule_group_unplaced` rows for that subject are gone;
   - the schedule group still exists;
   - the unrelated subject and its schedule still exist.
2. **Subject used only by a group (no schedules) deletes** and its
   `schedule_group_subjects` row is removed.

These run on SQLite with `foreign_key_constraints` enabled (`config/database.php:40`),
so the cascades are exercised for real.

## Risks / notes

- Deletion is now destructive and irreversible: placed schedules and unplaced rows are
  lost, including for active/archived groups. The confirm copy is the only warning.
- Existing deployments must run `php artisan migrate` (not `migrate:fresh`).
- The SQLite `dropForeign` path rebuilds the `schedules` table; verify the unique index
  (`schedule_group_id, section_id, start_time`) and the `subject_id` FK cascade after
  migrating. Tests run migrations fresh and will surface a broken cascade.
- No model observers/events depend on `Schedule` deletes, so a DB-level cascade loses
  no application behaviour.
- If history/auditing is ever required, archiving (`status`) should be used instead of
  deletion; out of scope here.

## Validation

- `php artisan migrate`
- `vendor\bin\pint --dirty`
- `vendor\bin\pest`
- `npm run build` (Subjects page copy changed)
- Manual smoke: click Delete on a subject used by a generated group and confirm the
  "Are you sure…" prompt appears; after accepting, the subject is gone, the group no
  longer lists it, its schedule rows are gone, and teacher assignments for that subject
  are removed. Cancelling the prompt deletes nothing.
