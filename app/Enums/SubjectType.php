<?php

namespace App\Enums;

enum SubjectType: string
{
    case Regular = 'regular';
    case Core = 'core';
    case Elective = 'elective';

    /**
     * Get the human-readable label for the subject type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Core => 'Core',
            self::Elective => 'Elective',
        };
    }

    /**
     * Determine whether the subject teacher may proctor their own exam.
     */
    public function allowsSelfProctoring(): bool
    {
        return $this !== self::Regular;
    }

    /**
     * Determine whether the subject teacher is always their own proctor.
     */
    public function requiresSelfProctoring(): bool
    {
        return $this === self::Elective;
    }
}
