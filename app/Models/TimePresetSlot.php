<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['time_preset_id', 'start_time', 'end_time', 'sort_order'])]
class TimePresetSlot extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TimePreset, $this>
     */
    public function timePreset(): BelongsTo
    {
        return $this->belongsTo(TimePreset::class);
    }
}
