<?php

namespace App\Models;

use App\Enums\ScheduleGroupStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status', 'days', 'exams_per_day', 'time_preset_id', 'school_year_id', 'quarter_id', 'max_proctors_per_teacher'])]
class ScheduleGroup extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScheduleGroupStatus::class,
            'days' => 'integer',
            'exams_per_day' => 'integer',
            'max_proctors_per_teacher' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TimePreset, $this>
     */
    public function timePreset(): BelongsTo
    {
        return $this->belongsTo(TimePreset::class);
    }

    /**
     * @return HasMany<ScheduleGroupDayPreset, $this>
     */
    public function dayPresets(): HasMany
    {
        return $this->hasMany(ScheduleGroupDayPreset::class)->orderBy('day');
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return BelongsTo<Quarter, $this>
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'schedule_group_sections')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'schedule_group_subjects')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return HasMany<ScheduleGroupUnplaced, $this>
     */
    public function unplaced(): HasMany
    {
        return $this->hasMany(ScheduleGroupUnplaced::class);
    }
}
