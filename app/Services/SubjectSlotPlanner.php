<?php

namespace App\Services;

use App\Models\ScheduleGroup;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimePresetSlot;
use Illuminate\Support\Collection;

/**
 * Assigns each grade's ordered subjects to concrete day/period slots.
 *
 * Subjects are consumed as a queue: every day contributes
 * `min(exams_per_day, effective slot count)` periods, and a day with fewer
 * slots carries the remaining subjects into the next day. Subjects that do not
 * fit in any day are reported on the last exam day with a null period/slot.
 *
 * The generator, grid/exports, and both Vue grids read from this plan so they
 * cannot disagree about which subject sits on which day.
 */
class SubjectSlotPlanner
{
    /**
     * Build the per-day, per-grade subject plan.
     *
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Subject>  $subjects  Already ordered per grade.
     * @return array<int, array<int, array<int, array{subject: Subject, period: int|null, slot: TimePresetSlot|null}>>>
     */
    public function plan(ScheduleGroup $group, Collection $sections, Collection $subjects, int $days, int $examsPerDay): array
    {
        $group->loadMissing(['timePreset.slots', 'dayPresets.timePreset.slots']);

        $days = max(1, $days);
        $examsPerDay = max(1, $examsPerDay);

        $gradeIds = $sections
            ->pluck('grade_level_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $subjectsByGrade = $subjects->groupBy(fn (Subject $subject): int => (int) $subject->grade_level_id);

        $effectiveSlotsByDay = $this->effectiveSlotsByDay($group, $days, $gradeIds);

        $plan = [];

        for ($day = 1; $day <= $days; $day++) {
            foreach ($gradeIds as $gradeId) {
                $plan[$day][$gradeId] = [];
            }
        }

        foreach ($gradeIds as $gradeId) {
            $queue = $subjectsByGrade->get($gradeId, collect())->values();
            $total = $queue->count();
            $index = 0;

            for ($day = 1; $day <= $days; $day++) {
                $slots = $effectiveSlotsByDay[$day][$gradeId] ?? collect();
                $capacity = min($examsPerDay, $slots->count());

                for ($period = 0; $period < $capacity; $period++) {
                    if ($index >= $total) {
                        break;
                    }

                    $plan[$day][$gradeId][] = [
                        'subject' => $queue->get($index),
                        'period' => $period,
                        'slot' => $slots->get($period),
                    ];

                    $index++;
                }
            }

            while ($index < $total) {
                $plan[$days][$gradeId][] = [
                    'subject' => $queue->get($index),
                    'period' => null,
                    'slot' => null,
                ];

                $index++;
            }
        }

        return $plan;
    }

    /**
     * Build the compact plan shape consumed by the Vue grids.
     *
     * @return array<int, array<int, array<int, array{subject_id: int, period: int|null}>>>
     */
    public function displayPlanForGroup(ScheduleGroup $group): array
    {
        $group->loadMissing([
            'timePreset.slots',
            'dayPresets.timePreset.slots',
            'sections',
            'subjects',
        ]);

        $days = max(1, (int) ($group->days ?? 1));
        $examsPerDay = max(1, (int) ($group->exams_per_day ?? 1));

        $sections = $group->sections->sortBy('id')->values();
        $subjects = $group->subjects
            ->sort(function (Subject $a, Subject $b): int {
                return ((int) ($a->pivot->sort_order ?? 0) <=> (int) ($b->pivot->sort_order ?? 0))
                    ?: ((int) $a->id <=> (int) $b->id);
            })
            ->values();

        $plan = $this->plan($group, $sections, $subjects, $days, $examsPerDay);

        $display = [];

        foreach ($plan as $day => $grades) {
            foreach ($grades as $gradeId => $entries) {
                $display[$day][$gradeId] = array_map(fn (array $entry): array => [
                    'subject_id' => (int) $entry['subject']->id,
                    'period' => $entry['period'],
                ], $entries);
            }
        }

        return $display;
    }

    /**
     * Resolve each day's effective slots per grade.
     *
     * A day's override preset (when present and not deleted) replaces the
     * group's default preset. Within a preset, grade-specific slots replace
     * the preset's default slots.
     *
     * @param  Collection<int, int>  $gradeIds
     * @return array<int, array<int, Collection<int, TimePresetSlot>>>
     */
    private function effectiveSlotsByDay(ScheduleGroup $group, int $days, Collection $gradeIds): array
    {
        $dayPresets = $group->dayPresets->keyBy('day');
        $result = [];

        for ($day = 1; $day <= $days; $day++) {
            $preset = $dayPresets->get($day)?->timePreset ?? $group->timePreset;
            $slots = $preset?->slots ?? collect();
            $defaultSlots = $slots->whereNull('grade_level_id')->sortBy('sort_order')->values();
            $slotsByGrade = $slots->whereNotNull('grade_level_id')
                ->groupBy(fn (TimePresetSlot $slot): int => (int) $slot->grade_level_id);

            foreach ($gradeIds as $gradeId) {
                $gradeSlots = $slotsByGrade->get($gradeId, collect())->sortBy('sort_order')->values();

                $result[$day][$gradeId] = $gradeSlots->isNotEmpty() ? $gradeSlots : $defaultSlots;
            }
        }

        return $result;
    }
}
