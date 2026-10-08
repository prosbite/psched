<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class TimePreset extends Model
{
    use HasFactory;

    /**
     * @return HasMany<TimePresetSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(TimePresetSlot::class)->orderBy('sort_order');
    }
}
