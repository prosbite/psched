<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_name', 'grade_level_id', 'is_elective'])]
class Subject extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_elective' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return HasMany<SectionSubjectTeacher, $this>
     */
    public function sectionSubjectTeachers(): HasMany
    {
        return $this->hasMany(SectionSubjectTeacher::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return BelongsToMany<ScheduleGroup, $this>
     */
    public function scheduleGroups(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleGroup::class, 'schedule_group_subjects')->withTimestamps();
    }
}
