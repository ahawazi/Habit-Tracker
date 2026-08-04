<?php

namespace App\Actions\Habits;

use App\Models\DailyCheckIn;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDailyCheckIn
{
    /**
     * Create or update the user's personal check-in for a single date.
     *
     * The check-in row is created on first save, which is why the tracker never
     * pre-creates rows for the thousands of dates it displays.
     *
     * @param  array<int, bool>  $completions  Keyed by habit ID.
     *
     * @throws ValidationException When the date lies in the future.
     */
    public function handle(
        User $user,
        Team $team,
        CarbonImmutable $date,
        array $completions,
        ?int $moodScore = null,
        ?string $notes = null,
    ): DailyCheckIn {
        $date = $date->startOfDay();

        if ($date->greaterThan(Date::today()->toImmutable())) {
            throw ValidationException::withMessages([
                'date' => __('habits.validation.future_date'),
            ]);
        }

        return DB::transaction(function () use ($user, $team, $date, $completions, $moodScore, $notes): DailyCheckIn {
            $checkIn = DailyCheckIn::query()
                ->ownedBy($user, $team)
                ->whereDate('date', $date->toDateString())
                ->first();

            if (! $checkIn) {
                // team_id and user_id are deliberately not mass-assignable: ownership is
                // set here from the authenticated user and their current team only.
                $checkIn = new DailyCheckIn(['date' => $date->toDateString()]);
                $checkIn->team()->associate($team);
                $checkIn->user()->associate($user);
            }

            $checkIn->fill([
                'mood_score' => $moodScore,
                'notes' => $notes,
            ])->save();

            // Only habits that currently belong to this team may be recorded, so a
            // forged habit ID from another team is silently discarded.
            $habitIds = $team->habits()->activeOrdered()->pluck('id');

            foreach ($habitIds as $habitId) {
                $checkIn->completions()->updateOrCreate(
                    ['habit_id' => $habitId],
                    ['completed' => (bool) ($completions[$habitId] ?? false)],
                );
            }

            return $checkIn->load('completions');
        });
    }
}
