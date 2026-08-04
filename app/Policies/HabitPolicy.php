<?php

namespace App\Policies;

use App\Models\Habit;
use App\Models\Team;
use App\Models\User;

class HabitPolicy
{
    /**
     * Determine whether the user can view the given team's habits.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the habit.
     */
    public function view(User $user, Habit $habit): bool
    {
        return $user->belongsToTeam($habit->team);
    }
}
