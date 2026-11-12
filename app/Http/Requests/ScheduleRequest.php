<?php

namespace App\Http\Requests;

use App\Enums\ScheduleGroupStatus;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Models\SectionSubjectTeacher;
use App\Models\Subject;
use App\Services\ConflictChecker;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! ($this->user()?->isAdmin() ?? false)) {
            return false;
        }

        return $this->group()?->status === ScheduleGroupStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day' => ['required', 'integer', 'min:1'],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'proctor_id' => ['required', 'integer', Rule::exists('teachers', 'id')],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $group = $this->group();

            if ($group === null) {
                return;
            }

            if (strcmp((string) $this->input('end_time'), (string) $this->input('start_time')) <= 0) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }

            $this->validateSubjectMatchesSection($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $checker = app(ConflictChecker::class);
            $schedule = $this->route('schedule');

            $existing = $group->schedules()
                ->when(
                    $schedule instanceof Schedule,
                    fn ($query) => $query->whereKeyNot($schedule->getKey()),
                )
                ->get()
                ->map(fn (Schedule $entry): array => $checker->entryFromSchedule($entry))
                ->all();

            $subjectTeacherId = $this->resolveSubjectTeacherId();
            $isElective = (bool) Subject::query()->find($this->input('subject_id'))?->is_elective;

            $candidate = [
                'day' => (int) $this->input('day'),
                'section_id' => (int) $this->input('section_id'),
                'subject_id' => (int) $this->input('subject_id'),
                'subject_teacher_id' => (int) $subjectTeacherId,
                'proctor_id' => $isElective ? (int) $subjectTeacherId : (int) $this->input('proctor_id'),
                'start_time' => (string) $this->input('start_time'),
                'end_time' => (string) $this->input('end_time'),
            ];

            $maxProctors = (int) $group->max_proctors_per_teacher;

            $subjectTeacherCandidate = $candidate;
            unset($subjectTeacherCandidate['proctor_id']);

            if ($isElective) {
                if ($checker->hasConflictingTeacher($existing, $subjectTeacherCandidate)) {
                    $validator->errors()->add('proctor_id', 'The subject teacher for this elective is already teaching another subject at this time.');
                }

                // Electives are self-proctored: only section overlaps matter,
                // mirroring ScheduleGenerator::placeElective().
                $check = $candidate;
                unset($check['subject_teacher_id'], $check['proctor_id']);

                $reasons = $checker->conflicts($existing, $check, $maxProctors);
            } else {
                if ($checker->hasConflictingTeacher($existing, $subjectTeacherCandidate)) {
                    $validator->errors()->add('proctor_id', 'The subject teacher for this exam is already teaching another subject at this time.');
                }

                $reasons = $checker->conflicts($existing, $candidate, $maxProctors, false);
            }

            foreach ($reasons as $reason) {
                $validator->errors()->add('proctor_id', $reason);
            }
        });
    }

    /**
     * Ensure the chosen subject belongs to the section's grade level.
     */
    private function validateSubjectMatchesSection(Validator $validator): void
    {
        $group = $this->group();

        if ($group === null) {
            return;
        }

        $section = $group->sections()->find($this->input('section_id'));

        if ($section === null) {
            $validator->errors()->add('section_id', 'The section must belong to this schedule group.');

            return;
        }

        $subjectBelongs = $group->subjects()
            ->whereKey($this->input('subject_id'))
            ->where('grade_level_id', $section->grade_level_id)
            ->exists();

        if (! $subjectBelongs) {
            $validator->errors()->add('subject_id', 'The subject is not part of this schedule group for the section grade level.');

            return;
        }

        if ($this->resolveSubjectTeacherId() === null) {
            $validator->errors()->add('subject_id', 'No teacher is assigned to this subject for the section.');
        }
    }

    /**
     * Resolve the subject teacher for the requested section/subject mapping.
     */
    private function resolveSubjectTeacherId(): ?int
    {
        $teacherId = SectionSubjectTeacher::query()
            ->where('section_id', $this->input('section_id'))
            ->where('subject_id', $this->input('subject_id'))
            ->whereNotNull('teacher_id')
            ->value('teacher_id');

        return $teacherId === null ? null : (int) $teacherId;
    }

    /**
     * Resolve the schedule group from the route.
     */
    private function group(): ?ScheduleGroup
    {
        $group = $this->route('scheduleGroup')
            ?? $this->route('schedule')?->scheduleGroup;

        return $group instanceof ScheduleGroup ? $group : null;
    }
}
