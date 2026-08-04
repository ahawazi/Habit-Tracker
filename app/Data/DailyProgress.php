<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * A single day's progress for one user in one team.
 *
 * Days the user has never saved are represented by {@see self::empty()} rather than
 * a database row, which is why the tracker never needs to pre-create rows.
 */
readonly class DailyProgress
{
    public function __construct(
        public CarbonImmutable $date,
        public int $completedCount,
        public int $totalCount,
        public int $percentage,
        public ?int $moodScore = null,
        public ?string $notes = null,
        public bool $hasCheckIn = false,
        public bool $isFuture = false,
        public bool $isToday = false,
    ) {
        //
    }

    /**
     * Build the progress for a date the user has no check-in for.
     */
    public static function empty(CarbonImmutable $date, int $totalCount, CarbonImmutable $today): self
    {
        return new self(
            date: $date,
            completedCount: 0,
            totalCount: $totalCount,
            percentage: 0,
            hasCheckIn: false,
            isFuture: $date->greaterThan($today),
            isToday: $date->isSameDay($today),
        );
    }

    /**
     * Calculate the completion percentage for a number of completed habits.
     *
     * With the MVP's three habits this yields exactly 0, 33, 67 and 100.
     */
    public static function percentageFor(int $completedCount, int $totalCount): int
    {
        if ($totalCount <= 0) {
            return 0;
        }

        return (int) round($completedCount / $totalCount * 100);
    }

    /**
     * Determine whether every habit was completed on this day.
     *
     * This is the definition a streak is built from, so a day with no habits at all
     * never counts as complete.
     */
    public function isComplete(): bool
    {
        return $this->totalCount > 0 && $this->completedCount === $this->totalCount;
    }

    /**
     * Get the coarse progress level used for visual states: 0, 1, 2 or 3.
     */
    public function level(): int
    {
        return $this->completedCount;
    }
}
