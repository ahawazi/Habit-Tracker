<?php

namespace App\Data;

use App\Actions\Habits\GetHabitProgress;

/**
 * Current and best streak lengths, in days.
 *
 * @see GetHabitProgress::streaks() for the streak rules.
 */
readonly class StreakSummary
{
    public function __construct(
        public int $current,
        public int $best,
    ) {
        //
    }
}
