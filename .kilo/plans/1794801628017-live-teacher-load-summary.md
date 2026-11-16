# Live teacher/proctor load summary on the schedule group page

## Goal

The Teacher load summary on `ScheduleGroups/Show` (teaching slots, proctor count, overall
load, per-day breakdowns, and the min/max/spread line) must always match the schedules
currently shown in the grid. Switching, adding, or removing a proctor must immediately
change the summary — no stale numbers.

## Current behaviour / root cause

- The grid and the **Table** view render from `props.group.schedules`
  (`Show.vue:354`, `Show.vue:404`).
- The T/P/O counts are a **separate set of six props** computed server-side in
  `ScheduleGroupController::show()` from `$scheduleGroup->schedules` via
  `ScheduleLoad::fromSchedules()` (`ScheduleGroupController.php:111-114`,
  `ScheduleGroupController.php:125-130`), then read by `Show.vue:44-52`,
  `Show.vue:1075-1089`, `Show.vue:1144-1159`.
- Schedule mutations redirect with `back()` (`ScheduleController.php:27,42,58`), which
  depends on the referer/session previous URL rather than the canonical show URL.

Because the grid and the summary read from two different prop sets, they can drift:
a saved edit can update `group.schedules` (grid) while the six load props still show the
previous aggregation. The fix is to make the summary derive from the **same
`group.schedules` array** the grid renders, so drift is structurally impossible.

## Decisions

| Topic | Decision |
| --- | --- |
| Source of truth | Derive all schedule-based load maps in the Vue page from `props.group.schedules`. |
| Counting semantics | Preserve `ScheduleLoad::fromSchedules()` exactly (see below). No rule changes. |
| Server props | Remove the six load props from `ScheduleGroupController::show()` and `Show.vue` `defineProps`. Keep `subjectTeacherLoads` / `subjectTeacherLoadsByDay` (assignment-based, unrelated to schedules). |
| `ScheduleLoad` service | Keep the class: `ScheduleGenerator.php:517` still uses `ScheduleLoad::slotKey()`. Only the aggregation call in `show()` is removed. Keep `ScheduleLoadTest.php`. |
| Redirects | Return explicit `redirect()->route('schedule-groups.show', $scheduleGroup)` from schedule store/update/destroy instead of `back()`. |
| Generation report | Out of scope; it stays a one-time flash snapshot. |
| Table view | No behaviour change; it already reads `group.schedules`. Its headers will now agree with the derived maps because both come from the same data. |

### Counting rules to mirror (from `app/Services/ScheduleLoad.php`)

- Normalize `start_time`/`end_time` to `HH:MM` (first 5 chars).
- `teaching`: per teacher, count **distinct** `day|start|end` where `subject_teacher_id` matches.
- `proctor`: per teacher, count **every schedule row** where `proctor_id` matches; also per day.
- `overall`: per teacher, count **distinct** `day|start|end` where the teacher is either the subject teacher or the proctor (so a self-proctored schedule counts once).
- Initialize every teacher from `props.teachers` to `0` / empty per-day map so idle teachers still show zeros.
- `day` defaults to `1`.

## Implementation

### 1. New pure helper `resources/js/lib/scheduleLoad.js`

Do not install new dependencies. Export:

```js
export function buildScheduleLoads(schedules, teacherIds)
// returns { teaching, proctor, overall, teachingByDay, proctorByDay, overallByDay }
```

- Keys are teacher ids (numeric strings are fine; JS object lookup with `teacher.id` works).
- Use `Set`s of `day|HH:MM|HH:MM` for `teaching`/`overall`, a counter for `proctor`, and a per-day counter for `proctorByDay`.
- Reuse the same normalization/derivation for `teachingByDay` and `overallByDay`.
- No Vue imports; plain ESM so it can be unit-tested with Node.

### 2. `resources/js/Pages/ScheduleGroups/Show.vue`

- Import `buildScheduleLoads`.
- Add:
  ```js
  const scheduleLoads = computed(() =>
      buildScheduleLoads(props.group.schedules ?? [], props.teachers.map((teacher) => teacher.id)),
  );
  const teachingLoads = computed(() => scheduleLoads.value.teaching);
  const proctorLoads = computed(() => scheduleLoads.value.proctor);
  const overallLoads = computed(() => scheduleLoads.value.overall);
  const teachingLoadsByDay = computed(() => scheduleLoads.value.teachingByDay);
  const proctorLoadsByDay = computed(() => scheduleLoads.value.proctorByDay);
  const overallLoadsByDay = computed(() => scheduleLoads.value.overallByDay);
  ```
  The `…ByDay` names must exist because the template references them directly
  (`Show.vue:1077,1085,1089,1144,1151,1157`).
- Change helpers to read the computed maps:
  ```js
  const teachingLoad = (teacher) => Number(teachingLoads.value?.[teacher?.id] ?? 0);
  const proctorLoad = (teacher) => Number(proctorLoads.value?.[teacher?.id] ?? 0);
  const overallLoad = (teacher) => Number(overallLoads.value?.[teacher?.id] ?? 0);
  ```
- Update `proctorAtCap` (`Show.vue:50-51`) to use `proctorLoadsByDay.value`.
- Leave `dayBreakdown`, `overallSpread`, the cards view, and the Table view unchanged otherwise.
- Delete the six load props from `defineProps` (`Show.vue:21-26`). Keep `subjectTeacherLoads` and `subjectTeacherLoadsByDay`.
- Expected result: when `group.schedules` changes after a save, every count recomputes in the same render — no extra request and no second data source.

### 3. `app/Http/Controllers/ScheduleGroupController.php`

- Remove `$loads = app(ScheduleLoad::class)->fromSchedules(...)` and the six
  `teachingLoads`/`proctorLoads`/`overallLoads`/…`ByDay` payload keys (`:111-114`, `:125-130`).
- Remove the now-unused `use App\Services\ScheduleLoad;` import (`:16`).
- Keep `$teachers`, `subjectTeacherLoads`, and `subjectTeacherLoadsByDay`.

### 4. `app/Http/Controllers/ScheduleController.php`

Replace `back()` with an explicit show redirect in `store()` (`:27`), `update()` (`:42`), and
`destroy()` (`:58`), keeping the same flash messages:

```php
return redirect()->route('schedule-groups.show', $scheduleGroup)->with('success', 'Schedule updated.');
```

Keep the existing draft-only guard and 404 check in `destroy()`/`update()`.

### 5. Tests

**Pest (`tests/Feature/Scheduling/ScheduleUpdateTest.php`)** — reuse the existing
`scheduleUpdateContext()` / `scheduleUpdateEntry()` helpers:

- `test('the show page reflects a proctor switch immediately')`:
  1. Create one schedule with `$context['proctor']`.
  2. `get(route('schedule-groups.show', $group))` with `Inertia::assert`/`AssertableInertia` and assert the schedule's `proctor_id` is the first proctor (this is the data the summary derives from).
  3. `put(route('schedule-groups.schedules.update', [$group, $schedule]), [... 'proctor_id' => $context['freeProctor']->id ...])` and assert it redirects to `route('schedule-groups.show', $group)`.
  4. `get(show)` again and assert the payload's schedule now has `proctor_id === $context['freeProctor']->id`.
- Optionally assert the same for `store` and `destroy` redirect targets.

**Node (`resources/js/lib/scheduleLoad.test.js`, zero new deps)** — pure-function coverage:

- Two sections of the same subject in the same slot: subject teacher `teaching = 1`, `overall = 1`; proctor with both rows `proctor = 2`, `overall = 1`.
- Self-proctored slot: teacher has `teaching = 1`, `proctor = 1`, `overall = 1`.
- Per-day breakdowns and idle teachers (`0` / empty map).
- Time strings with and without seconds normalize to the same slot.
- Run with `node --test resources/js/lib/scheduleLoad.test.js`.

## Validation

- `vendor\bin\pest`
- `vendor\bin\pint --dirty`
- `node --test resources/js/lib/scheduleLoad.test.js`
- `npm run build`
- Manual smoke on a draft group:
  1. Generate a grid; note a proctor's `P` count and the overall spread.
  2. Edit one schedule and switch the proctor; save.
  3. The grid's Proctor column and the Teacher load card / Table view must both change in the same render: old proctor `P` decreases, new proctor `P` increases, per-day `D…` values and `Overall` update; the min/max/spread line recomputes.
  4. Add a schedule with another proctor and remove it; counts return to the previous values.
  5. Reopen an active group as draft first if the edit buttons are hidden.

## Risks / notes

- `proctor` is intentionally an **assignment count** (one per schedule row) while `teaching`/`overall` count **distinct slots**; keep that documented behaviour. Do not "fix" the difference unless the user asks.
- `subjectTeacherLoads`/`…ByDay` change only when section-subject assignments change, not when schedules are edited; that is expected.
- Removing the six server props is API-compatible for this app (only `Show.vue` read them).
- `ScheduleLoad::fromSchedules()` will then be exercised only by `ScheduleLoadTest`; `ScheduleLoad::slotKey()` remains used by the generator, so the class must stay.
- The generation report is still a flash snapshot and will not recompute; that is out of scope.
