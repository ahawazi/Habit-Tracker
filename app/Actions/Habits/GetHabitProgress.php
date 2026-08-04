<?php

namespace App\Actions\Habits;

use App\Data\DailyProgress;
use App\Data\ProgressSummary;
use App\Data\StreakSummary;
use App\Models\DailyCheckIn;
use App\Models\Habit;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The read model behind every progress figure in the application.
 *
 * All queries are scoped to one user and one team, so a user can only ever see
 * their own check-ins inside their own team.
 */
class GetHabitProgress
{
    /**
     * Per-team habit lists, memoised for the lifetime of the request.
     *
     * @var array<int, EloquentCollection<int, Habit>>
     */
    protected array $habits = [];

    /**
     * Get the team's active habits in display order.
     *
     * @return EloquentCollection<int, Habit>
     */
    public function habits(Team $team): EloquentCollection
    {
        return $this->habits[$team->id] ??= $team->habits()->activeOrdered()->get();
    }

    /**
     * Get the progress for a single date.
     */
    public function forDate(User $user, Team $team, CarbonImmutable $date): DailyProgress
    {
        $date = $date->startOfDay();

        return $this->forRange($user, $team, $date, $date)->get($date->toDateString())
            ?? DailyProgress::empty($date, $this->habits($team)->count(), Date::today()->toImmutable());
    }

    /**
     * Get the progress for every date in an inclusive range, keyed by "Y-m-d".
     *
     * Dates without a check-in are returned as empty progress rather than omitted,
     * so callers can render a complete calendar from a single call.
     *
     * @return Collection<string, DailyProgress>
     */
    public function forRange(User $user, Team $team, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        $today = Date::today()->toImmutable();
        $totalCount = $this->habits($team)->count();

        $checkIns = DailyCheckIn::query()
            ->ownedBy($user, $team)
            ->betweenDates($start, $end)
            ->with('completions')
            ->get()
            ->keyBy(fn (DailyCheckIn $checkIn): string => $checkIn->date->toDateString());

        /** @var Collection<string, DailyProgress> $progress */
        $progress = new Collection;

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $key = $date->toDateString();

            /** @var DailyCheckIn|null $checkIn */
            $checkIn = $checkIns->get($key);

            if (! $checkIn) {
                $progress->put($key, DailyProgress::empty($date, $totalCount, $today));

                continue;
            }

            $completedCount = $checkIn->completedCount();

            $progress->put($key, new DailyProgress(
                date: $date,
                completedCount: $completedCount,
                totalCount: $totalCount,
                percentage: DailyProgress::percentageFor($completedCount, $totalCount),
                moodScore: $checkIn->mood_score,
                notes: $checkIn->notes,
                hasCheckIn: true,
                isFuture: $date->greaterThan($today),
                isToday: $date->isSameDay($today),
            ));
        }

        return $progress;
    }

    /**
     * Aggregate a range of daily progress.
     *
     * Future dates are excluded from the totals: they cannot have been completed
     * yet, so counting them would drag every percentage towards zero.
     *
     * @param  Collection<string, DailyProgress>  $progress
     */
    public function summarize(Collection $progress): ProgressSummary
    {
        $elapsed = $progress->reject(fn (DailyProgress $day): bool => $day->isFuture);

        $completedHabits = (int) $elapsed->sum(fn (DailyProgress $day): int => $day->completedCount);
        $possibleHabits = (int) $elapsed->sum(fn (DailyProgress $day): int => $day->totalCount);

        $moods = $elapsed
            ->map(fn (DailyProgress $day): ?int => $day->moodScore)
            ->filter(fn (?int $mood): bool => $mood !== null);

        return new ProgressSummary(
            totalDays: $elapsed->count(),
            completedDays: $elapsed->filter(fn (DailyProgress $day): bool => $day->isComplete())->count(),
            completedHabits: $completedHabits,
            possibleHabits: $possibleHabits,
            percentage: $possibleHabits > 0 ? (int) round($completedHabits / $possibleHabits * 100) : 0,
            averageMood: $moods->isNotEmpty() ? round((float) $moods->avg(), 1) : null,
        );
    }

    /**
     * Calculate the user's current and best streaks.
     *
     * Streak rules:
     *  - A day counts towards a streak only when *every* active habit was completed
     *    on that day. A partially completed day does not extend a streak.
     *  - The best streak is the longest unbroken run of complete days in the user's
     *    entire history for the team.
     *  - The current streak is the unbroken run ending today. Because today is still
     *    in progress, an incomplete today does not break the streak: the count then
     *    ends at yesterday instead. If neither today nor yesterday is complete, the
     *    current streak is zero.
     *  - Future dates are never part of a streak; they cannot be saved.
     */
    public function streaks(User $user, Team $team): StreakSummary
    {
        $totalCount = $this->habits($team)->count();

        if ($totalCount === 0) {
            return new StreakSummary(current: 0, best: 0);
        }

        // The "all habits completed" filter runs in SQL, so only days that actually
        // count towards a streak are loaded.
        $completeDates = DailyCheckIn::query()
            ->ownedBy($user, $team)
            ->whereHas('completions', function (Builder $query): void {
                $query->where('completed', true)
                    ->whereHas('habit', fn (Builder $habit) => $habit->where('is_active', true));
            }, '>=', $totalCount)
            ->orderBy('date')
            ->get()
            ->map(fn (DailyCheckIn $checkIn): string => $checkIn->date->toDateString())
            ->values();

        if ($completeDates->isEmpty()) {
            return new StreakSummary(current: 0, best: 0);
        }

        return new StreakSummary(
            current: $this->currentStreak($completeDates->all()),
            best: $this->bestStreak($completeDates->all()),
        );
    }

    /**
     * Count the unbroken run of complete days ending today (or yesterday).
     *
     * @param  array<int, string>  $completeDates  Ascending "Y-m-d" dates.
     */
    protected function currentStreak(array $completeDates): int
    {
        $complete = array_flip($completeDates);
        $today = Date::today()->toImmutable();

        // Today is still in progress, so fall back to yesterday before giving up.
        $cursor = isset($complete[$today->toDateString()]) ? $today : $today->subDay();

        $streak = 0;

        while (isset($complete[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * Find the longest unbroken run of complete days.
     *
     * @param  array<int, string>  $completeDates  Ascending "Y-m-d" dates.
     */
    protected function bestStreak(array $completeDates): int
    {
        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($completeDates as $date) {
            $current = CarbonImmutable::parse($date);

            $run = ($previous && $previous->addDay()->isSameDay($current)) ? $run + 1 : 1;

            $best = max($best, $run);
            $previous = $current;
        }

        return $best;
    }
}
