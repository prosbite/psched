<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_group_id', 'day', 'time_preset_id'])]
class ScheduleGroupDayPreset extends Model
{
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
     * @return BelongsTo<TimePreset, $this>
     */
    public function timePreset(): BelongsTo
    {
        return $this->belongsTo(TimePreset::class);
    }
}
