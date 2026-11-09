<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class SchoolYear extends Model
{
    use HasFactory;

    /**
     * @return HasMany<ScheduleGroup, $this>
     */
    public function scheduleGroups(): HasMany
    {
        return $this->hasMany(ScheduleGroup::class);
    }
}
