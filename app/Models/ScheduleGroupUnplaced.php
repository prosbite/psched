<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_group_id',
    'day',
    'section_id',
    'subject_id',
    'reason',
    'reason_code',
    'start_time',
    'end_time',
])]
class ScheduleGroupUnplaced extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedule_group_unplaced';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
        ];
    }

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
}
