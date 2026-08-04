<?php

namespace App\Policies;

use App\Models\DailyCheckIn;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Check-ins are personal: belonging to the team is necessary but never sufficient.
 * A user may only ever read or write their own rows.
 */
class DailyCheckInPolicy
{
    /**
     * Determine whether the user can view their own check-ins for the given team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the check-in.
     */
    public function view(User $user, DailyCheckIn $dailyCheckIn): bool
    {
        return $this->owns($user, $dailyCheckIn);
    }

    /**
     * Determine whether the user can record a check-in for the given team and date.
     */
    public function create(User $user, Team $team, ?CarbonImmutable $date = null): bool
    {
        return $user->belongsToTeam($team) && ! $this->isFuture($date);
    }

    /**
     * Determine whether the user can update the check-in.
     */
    public function update(User $user, DailyCheckIn $dailyCheckIn): bool
    {
        return $this->owns($user, $dailyCheckIn) && ! $this->isFuture($dailyCheckIn->date);
    }

    /**
     * Determine whether the user can delete the check-in.
     */
    public function delete(User $user, DailyCheckIn $dailyCheckIn): bool
    {
        return $this->owns($user, $dailyCheckIn);
    }

    /**
     * Determine whether the check-in belongs to the user, inside a team they are in.
     */
    protected function owns(User $user, DailyCheckIn $dailyCheckIn): bool
    {
        return $dailyCheckIn->user_id === $user->id
            && $user->belongsToTeam($dailyCheckIn->team);
    }

    /**
     * Determine whether the given date lies ahead of today.
     */
    protected function isFuture(?CarbonImmutable $date): bool
    {
        return $date !== null && $date->startOfDay()->greaterThan(Date::today()->toImmutable());
    }
}
