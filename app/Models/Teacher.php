<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'lastname', 'firstname', 'middlename', 'email'])]
class Teacher extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject')->withTimestamps();
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function subjectSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'subject_teacher_id');
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function proctorSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'proctor_id');
    }

    /**
     * Get the teacher's full name.
     */
    public function fullName(): string
    {
        $parts = array_filter([
            $this->firstname,
            $this->middlename,
            $this->lastname,
        ], fn (?string $part): bool => filled($part));

        return implode(' ', $parts);
    }
}
