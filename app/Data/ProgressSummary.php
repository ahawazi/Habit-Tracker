<?php

namespace App\Data;

/**
 * Aggregate progress over a date range (a week, a month, or the whole tracker).
 */
readonly class ProgressSummary
{
    public function __construct(
        public int $totalDays,
        public int $completedDays,
        public int $completedHabits,
        public int $possibleHabits,
        public int $percentage,
        public ?float $averageMood = null,
    ) {
        //
    }
}
