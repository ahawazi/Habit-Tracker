<?php

namespace App\Actions\Habits;

use App\Enums\DefaultHabit;
use App\Models\Habit;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EnsureDefaultHabits
{
    /**
     * Make sure the given team has the three default habits.
     *
     * Idempotent: existing habits are matched on their stable slug and left alone,
     * including any customisation to their name, order or active flag. Safe to run
     * against brand-new teams, long-standing teams, and repeatedly.
     *
     * @return Collection<int, Habit>
     */
    public function handle(Team $team): Collection
    {
        return DB::transaction(function () use ($team): Collection {
            foreach (DefaultHabit::cases() as $default) {
                $team->habits()->firstOrCreate(
                    ['slug' => $default->value],
                    $default->toAttributes(),
                );
            }

            return $team->habits()->activeOrdered()->get();
        });
    }
}
