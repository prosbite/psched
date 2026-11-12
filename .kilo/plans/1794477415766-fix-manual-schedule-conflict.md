# Fix false teacher-conflict rejection on proctor-only schedule edits

## Goal

This is **not** a schedule swap. The user edits an existing schedule and changes only the **Proctor** dropdown to a teacher who has no schedule on that day. The save must succeed when that proctor is genuinely free. It must still fail for real proctor overlaps, per-day cap violations, and `proctor == subject teacher` on non-elective subjects.

## Reported reproduction

- A day-3 exam is shared by two or more sections of the same subject, using the same subject teacher (the normal generated layout).
- Both section schedules are persisted at the same day/time.
- The admin opens Edit on one schedule and changes only the Proctor field to a teacher with zero day-3 assignments.
- The request is rejected with an "overlapping schedule" error attached to `proctor_id`.
- The selected proctor is genuinely free on day 3; the conflict actually belongs to the *fixed subject teacher* of the edited exam.

## Root cause

- The generator deliberately does **not** treat a schedule's fixed subject teacher as a conflict when choosing a proctor, because the same subject teacher legitimately teaches several sections of the same exam at the same day/time:
  - `app/Services/ScheduleGenerator.php:275` (non-elective: `unset($check['subject_teacher_id'])`)
  - `app/Services/ScheduleGenerator.php:327` (elective: unsets both roles)
- The manual validation path includes the subject teacher in the conflict candidate:
  - `app/Http/Requests/ScheduleRequest.php:79-86` sets both `subject_teacher_id` and `proctor_id`.
  - `app/Services/ConflictChecker.php:34-36` then reports "A teacher is already assigned to an overlapping schedule." for the *timetable subject teacher*, not the proctor the user selected.
  - The message does not name the teacher, which is why it reads as if the newly selected proctor is the conflict.
- Any multi-section exam (same subject/day/time) therefore rejects every edit, even a pure proctor change.
- Electives are also broken: `ScheduleRequest` validates the submitted proctor, but `ScheduleController::payload()` (`ScheduleController.php:89-91`) forces `proctor_id = subject_teacher_id`. Editing an elective hits the `proctor == subject teacher` rule even though self-proctoring is the intended persisted state.

## Decisions

- Manual schedule validation will use the same assignment semantics as the generator:
  - Non-elective: check section overlap, proctor-only teacher overlap, and the per-day proctor cap; still reject `proctor == subject teacher`.
  - Elective: check section overlap only (mirror `ScheduleGenerator::placeElective()`); the effective proctor is the subject teacher.
- Keep `ConflictChecker`'s current default behavior so the generator and existing tests are unchanged; the new behavior is opt-in.
- Improve the assignment-mode conflict message so it names the proctor role rather than the ambiguous "A teacher...".
- No frontend changes are expected: the Edit Schedule modal already renders `scheduleForm.errors.proctor_id`.

## Implementation steps

### 1. `app/Services/ConflictChecker.php`

- Add an optional flag to `conflicts()`:
  `public function conflicts(array $existing, array $candidate, int $maxProctorsPerTeacher, bool $checkSubjectTeacherOverlap = true): array`
- Build a candidate copy used only for the teacher-overlap test:
  - `$overlapCandidate = $candidate;`
  - when the flag is `false`, `unset($overlapCandidate['subject_teacher_id']);`
- Use `$overlapCandidate` in the `sharesTeacher()` call at line 34. Leave `proctorEqualsSubjectTeacher()` and `proctorCapExceeded()` on the original `$candidate`.
- When `$checkSubjectTeacherOverlap === false`, use the message
  `"The selected proctor is already assigned to another schedule at this time."`
  instead of `"A teacher is already assigned to an overlapping schedule."`
- Optionally pass the flag through `isConflictFree()` for parity (default `true`).

### 2. `app/Http/Requests/ScheduleRequest.php`

- Import `App\Models\Subject`.
- In `withValidator()` after the existing early return:
  - Resolve `$subjectTeacherId = $this->resolveSubjectTeacherId();` once.
  - Determine `$isElective = (bool) Subject::query()->find($this->input('subject_id'))?->is_elective;`
  - Build the candidate with the effective proctor:
    `'proctor_id' => $isElective ? $subjectTeacherId : (int) $this->input('proctor_id')`.
  - Elective branch: copy the candidate, unset `subject_teacher_id` and `proctor_id`, then call
    `$checker->conflicts($existing, $check, $max)` → section overlap only.
  - Non-elective branch: call
    `$checker->conflicts($existing, $candidate, $max, false)` → section overlap + proctor-only overlap + cap + `proctor == subject teacher` rejection.
  - Keep the existing error-attachment loop (`proctor_id` field).
- Do not change `rules()` or `payload()`.

### 3. Tests

Add to `tests/Feature/Scheduling/ConflictCheckerTest.php`:
- `checkSubjectTeacherOverlap: false` ignores a repeated subject teacher in another section at the same time.
- The same mode still rejects a proctor already assigned in that slot, and still enforces the same-day cap.

Add to `tests/Feature/Scheduling/ScheduleStoreTest.php` (or a new `ScheduleUpdateTest.php`):
- Exact bug reproduction: two sections sit the same subject on the same day/time with the same subject teacher; both schedules are persisted. `PUT` the update route for one schedule with only `proctor_id` changed to a teacher who has no entries on that day. Assert redirect success and that the new proctor is saved.
- Storing a second section's schedule for the same subject/day/time with the same subject teacher succeeds (no false teacher conflict).
- Updating an existing schedule's proctor succeeds when the subject teacher repeats in a sibling section at the same time. Seed two schedules for the same subject/day/time in different sections, then `PUT` the update route with a free proctor.
- A genuinely double-booked proctor is still rejected.
- Selecting the subject teacher as proctor for a non-elective is still rejected.
- Updating an elective schedule succeeds, ignoring the submitted `proctor_id`, and persists the subject teacher as proctor.
- The per-day proctor cap is still enforced for non-electives.

## Validation

- `vendor\bin\pest tests\Feature\Scheduling` (or `php artisan test --filter=Scheduling`).
- `vendor\bin\pint --dirty`.
- Manual smoke:
  1. Pick a day-3 exam shared by multiple sections; edit one schedule and change only the proctor to a teacher with no day-3 assignment. Save succeeds.
  2. Try the same with a proctor who genuinely proctors or teaches that slot. Save is rejected.
  3. Try the non-elective `proctor == subject teacher` case. Still rejected.
  4. Edit a self-proctored elective. Save succeeds and the proctor remains the subject teacher.
- No `npm run build` needed unless frontend files are touched.

## Risks / notes

- Do not weaken the generator: its calls to `ConflictChecker` rely on the default flag (`true`) and must remain unchanged.
- Subject teachers may be double-booked across sections by design; this fix mirrors existing generator semantics rather than introducing a new invariant.
- Elective schedules remain self-proctored; the submitted proctor is ignored as before, but now validation matches persistence.
- Generator fairness remains best-effort: some teachers can still finish with one proctorship when the greedy pass runs out of slots. This plan unblocks manual correction; a guaranteed per-teacher target or one-click swap/rebalance UI is out of scope.

## Out of scope

- Changes to `ScheduleGenerator` balancing/fairness.
- A dedicated "swap proctor" action in the UI.
- Renaming or restructuring the teacher load/table views.
