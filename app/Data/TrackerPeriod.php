<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * The window of time the visual tracker covers.
 *
 * The MVP tracks from "today" through the configured end date (31 December 2026).
 * Calendar and year navigation is clamped to this window; nothing outside it is
 * reachable through the interface.
 */
readonly class TrackerPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        //
    }

    /**
     * Build the period from application configuration, anchored on today.
     */
    public static function fromConfig(): self
    {
        $end = CarbonImmutable::parse(config('habit-tracker.ends_on'))->startOfDay();

        return new self(Date::today()->toImmutable(), $end);
    }

    /**
     * Determine whether the given date falls inside the period.
     */
    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->start, $this->end);
    }

    /**
     * Get the first month reachable through month navigation.
     */
    public function firstMonth(): CarbonImmutable
    {
        return $this->start->startOfMonth();
    }

    /**
     * Get the last month reachable through month navigation.
     */
    public function lastMonth(): CarbonImmutable
    {
        return $this->end->startOfMonth();
    }

    /**
     * Clamp the given month to the navigable range.
     */
    public function clampMonth(CarbonImmutable $month): CarbonImmutable
    {
        $month = $month->startOfMonth();

        return match (true) {
            $month->lessThan($this->firstMonth()) => $this->firstMonth(),
            $month->greaterThan($this->lastMonth()) => $this->lastMonth(),
            default => $month,
        };
    }

    /**
     * Get the total number of days in the period.
     */
    public function totalDays(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }
}
