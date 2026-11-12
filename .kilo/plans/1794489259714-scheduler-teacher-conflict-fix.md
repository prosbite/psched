# Fix generator allowing teacher conflicts from custom subject order / timeslots

## Goal

When an admin orders subjects and configures time presets/slots, the generator must not persist a grid where one teacher is double-booked. This covers:

- the same teacher assigned as **subject teacher** of two **different subjects** whose slots overlap in real time (typically across grades, or via grade-specific overlapping slots);
- a teacher being chosen as **proctor** while they are teaching another subject at the same time (or vice versa);
- two different electives sharing one subject teacher at the same time.

Unavoidable conflicts must be reported as **unplaced** (user decision), not silently placed. The legitimate case where the **same subject teacher repeats across sections of the same subject/grade at the same time** (the grid merges those rows) must keep working.

## Root cause

- `ScheduleGenerator::place()` removes `subject_teacher_id` before conflict checking (`app/Services/ScheduleGenerator.php:271-276`), and `placeElective()` removes both roles (`:327-328`). The generator therefore never checks the fixed subject teacher against other subjects in the same real-time slot.
- The plan is built from the admin's subject order and presets (`SubjectSlotPlanner`), so two different subjects/grades can land on overlapping real times. The generator only checks the proctor role and only against entries already placed, so it also cannot see a future teaching commitment.
- `ConflictChecker` has no `subject_id` concept, so it cannot distinguish "same exam repeated across sections" from "two different exams".

Example: grade 7 Subject A (teacher T) and grade 10 Subject B (teacher T) both land on 07:00–08:00. `place()` checks only the proctor, places both, and T is double-booked. With different grade-specific slots that overlap (e.g. 08:00–09:00 and 08:30–09:30) the same happens because `overlaps()` compares real minutes, not slot indexes.

## Decisions

- **Report unavoidable conflicts as unplaced** with a new `reason_code = teacher_conflict` and a clear reason. Do not auto-reorder the admin's subjects or slots.
- Preserve the merged-exam rule: a teacher may repeat as subject teacher of the **same `subject_id`** across sections at the same time.
- Different subjects, and cross-role collisions (teacher teaching while proctoring), are conflicts.
- **Proactively avoid** choosing a proctor who is teaching any subject at that real time, using the full plan up front. This prevents the generator from creating a conflict it could have avoided.
- Do not change the generator's fairness/balancing algorithm.

## Implementation

### 1. `app/Services/ConflictChecker.php`

Keep `conflicts()` and `sharesTeacher()` behavior intact so the existing manual-validation fix and tests do not regress. Add subject-aware helpers.

- Add `subject_id` to `entryFromSchedule()`:
  `'subject_id' => $schedule->subject_id === null ? null : (int) $schedule->subject_id`.
- Add public:
  `public function hasConflictingTeacher(array $existing, array $candidate): bool`
  - Loop existing entries; return `true` on the first entry where `sameDay()` **and** `overlaps()` **and** `teacherRolesConflict($entry, $candidate)` are all true.
- Add private `teacherRolesConflict(array $first, array $second): bool`:
  - Read `subject_teacher_id` / `proctor_id` from both (nullable).
  - `$sameExam = first.subject_id` and `second.subject_id` are both set, equal, **and** both have the same non-null `subject_teacher_id`.
  - `$firstSelf = first.st === first.p` (both non-null); `$secondSelf` likewise.
  - Rule order:
    1. `first.st == second.st` → conflict **unless** `$sameExam`.
    2. `second.st == first.p` → conflict **unless** `$sameExam && $firstSelf`.
    3. `second.p == first.st` → conflict **unless** `$sameExam && $secondSelf`.
    4. `second.p == first.p` → conflict **unless** `$sameExam && $firstSelf && $secondSelf`.
    5. Otherwise no conflict.
  - Entries without `subject_id` are never `$sameExam`, so they keep the old strict behavior.
- This makes same-subject repeats legal, same-subject non-electives still require distinct proctors, and electives (self-proctored) legal across sections but not across different electives.

### 2. `app/Services/ScheduleGenerator.php`

- Build the full planned teaching list once after `$plan` and `$assignments`:
  - For every day/grade/planned entry with a `TimePresetSlot`, for every enrolled section with a non-null teacher, add one deduped entry:
    `{day, subject_id, subject_teacher_id, start_time, end_time}` (no proctor, no section needed).
  - Dedupe by `day|teacher|subject|start|end` so repeated sections count once.
- Extract `private function baseEntry(ScheduleGroup $group, Section $section, Subject $subject, int $subjectTeacherId, int $day, TimePresetSlot $slot): array` returning the current `$base` shape **including** `subject_id`, `subject_teacher_id`, `start_time`, `end_time`, and `slot_id`. `slot_id` is already stripped by the controller.
- In `generate()`, per section:
  - Build `$base` with `baseEntry()`.
  - `$known = array_merge($teachingPlan, $placed);`
  - If `$this->conflicts->hasConflictingTeacher($known, $base)`:
    - push `unplacedEntry(..., 'teacher_conflict', $message, $slot)` and `continue`.
    - Non-elective message: `The subject teacher for "<subject>" is already teaching another subject at this time. Reorder the subjects or adjust the time preset.`
    - Elective message: `The subject teacher for the elective "<subject>" is already teaching another subject at this time.`
  - Otherwise pass `$base` to `place()` / `placeElective()`.
- `place(array $base, int $subjectTeacherId, array $proctors, array $placed, array $loadTotals, array $proctorTotals, int $maxProctors, array $teachingPlan)`:
  - Do **not** unset `subject_teacher_id`.
  - Build `$known = array_merge($teachingPlan, $placed);` once before the loop.
  - For each fairness-ordered proctor (skip `proctorId === subjectTeacherId`):
    - `$candidate = $base + ['proctor_id' => $proctorId];`
    - skip if `hasConflictingTeacher($known, $candidate)` (excludes proctors who teach another subject at that time);
    - skip if `! isConflictFree($placed, $candidate, $maxProctors, false)` (section overlap, proctor overlap against placed, per-day cap — subject-teacher role remains handled by `hasConflictingTeacher`);
    - otherwise return `$candidate`.
- `placeElective(array $base, array $placed, int $maxProctors)`:
  - The inherent teacher check already ran in `generate()`.
  - `$check = $base; unset($check['subject_teacher_id']);` (keeps no proctor) and return `$base + ['proctor_id' => $base['subject_teacher_id']]` when `isConflictFree($placed, $check, $maxProctors)`.
  - This keeps section-only checks and does not charge the proctor budget.
- Update the class docblock: subject teachers are fixed by the timetable and conflict across **different** subjects; the same subject may repeat across sections; proctors are excluded when teaching.

### 3. `app/Http/Requests/ScheduleRequest.php`

Preserve the existing manual fix's behavior and add the subject-aware check.

- Add `'subject_id' => (int) $this->input('subject_id')` to `$candidate`.
- Non-elective branch:
  - `$subjectTeacherCandidate = $candidate; unset($subjectTeacherCandidate['proctor_id']);`
  - If `hasConflictingTeacher($existing, $subjectTeacherCandidate)`, add to `proctor_id`:
    `The subject teacher for this exam is already teaching another subject at this time.`
  - Then keep the existing `conflicts($existing, $candidate, $maxProctors, false)` call (proctor-only overlap, cap, `proctor == subject teacher`). This preserves the improved "selected proctor" message from the previous fix.
- Elective branch:
  - Same subject-teacher check with message:
    `The subject teacher for this elective is already teaching another subject at this time.`
  - Then `$sectionCheck = $candidate; unset($sectionCheck['subject_teacher_id'], $sectionCheck['proctor_id']);` and `conflicts($existing, $sectionCheck, $maxProctors)` for section overlap only.
  - The submitted proctor remains ignored, matching `ScheduleController::payload()`.

## Tests

### `tests/Feature/Scheduling/ConflictCheckerTest.php`

To test the same-exam exemption, pass `subject_id` explicitly in new fixtures. Do **not** add a default `subject_id` to the shared `conflictEntry()` helper (existing tests rely on the strict behavior when it is absent).

- Same subject + same subject teacher at the same time → `hasConflictingTeacher` false.
- Same subject + same teacher + same proctor (non-elective) → true.
- Different subjects + same teacher at the same time → true.
- Candidate proctor equals a different subject's subject teacher at the same time → true.
- Self-proctored same subject across sections → false.
- Non-overlapping/back-to-back or different day → false.

### `tests/Feature/Scheduling/ScheduleGeneratorTest.php`

- Exact bug: two grades, one slot, two subjects, same subject teacher → one placed, one `teacher_conflict` unplaced with the reason naming the subject.
- Same subject teacher across two sections of the same subject/grade in one slot → both placed, no unplaced.
- Proactive proctor exclusion: grade A subject teacher T2; grade B subject teacher T1; allowed proctors include T1 plus two others. T1 must not proctor grade A's exam; both exams place with the other proctors.
- Two different electives with the same teacher in one slot → one `teacher_conflict`.
- Same elective across two sections in one slot → both placed.
- Grade-specific slots whose real times overlap (e.g. 08:00–09:00 vs 08:30–09:30) with the same subject teacher → `teacher_conflict`.
- Endpoint test: `POST schedule-groups.generate` persists the conflicting exam in `schedule_group_unplaced` with `reason_code = teacher_conflict` and does not create a schedule row for it.

### `tests/Feature/Scheduling/ScheduleUpdateTest.php` / `ScheduleStoreTest.php`

- Updating a non-elective schedule to a different subject whose teacher is already teaching an overlapping schedule → `assertSessionHasErrors('proctor_id')` and no change.
- Storing the same different-subject conflict → rejected.
- Existing same-subject sibling update/store tests, elective update, cap, and `proctor == subject teacher` tests must still pass.

## Validation

- `vendor\bin\pest tests\Feature\Scheduling`
- `vendor\bin\pint --dirty`
- Manual smoke:
  1. Order two subjects for different grades and assign the same teacher; use one shared slot; generate. Expect one placed and one `teacher_conflict` in Needs attention.
  2. Reorder/subject or adjust the preset, regenerate; expect both placed.
  3. Two sections of the same subject with the same teacher still place together (merged exam).
  4. A teacher who teaches another subject in the slot is not selected as proctor.
  5. Editing/storing a conflicting different-subject schedule is rejected.

## Risks / notes

- The planned teaching list is built up front, so a conflict is detected even if the other planned exam later fails for another reason. This is deterministic (first in subject order wins) but slightly conservative; acceptable for this fix.
- Proctor filtering against the full plan can reduce candidates, producing `no_proctor` for an exam whose only remaining proctors are teaching at that time. The existing reason remains accurate.
- No schema migration: `schedule_group_unplaced.reason_code` is already a string column.
- No UI change required: `Show.vue` renders `item.reason` for any reason code. The "Add schedule" action remains; it will correctly reject a still-conflicting manual add.
- Do not weaken the existing same-subject repeat behavior or the per-day cap / `proctor == subject teacher` rules.
- Extend the test-only fixture helpers locally; do not change shared helper defaults that other tests depend on.

## Out of scope

- Auto-moving exams to free slots or reordering the admin's subjects.
- Backtracking/optimal proctor reassignment or fairness changes.
- A pre-generation dry-run/preview UI for conflicts.
- Renaming or restructuring load/table views.
