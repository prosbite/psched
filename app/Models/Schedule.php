<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_group_id',
    'section_id',
    'subject_id',
    'subject_teacher_id',
    'proctor_id',
    'start_time',
    'end_time',
])]
class Schedule extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<ScheduleGroup, $this>
     */
    public function scheduleGroup(): BelongsTo
    {
        return $this->belongsTo(ScheduleGroup::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function subjectTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'subject_teacher_id');
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function proctor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'proctor_id');
    }
}
