<?php

use App\Actions\Habits\GetHabitProgress;
use App\Data\ProgressSummary;
use App\Data\StreakSummary;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The dashboard's summary tiles: month progress, streaks and average mood.
 */
new class extends Component {
    /**
     * Refresh the tiles after a check-in is saved elsewhere on the page.
     */
    #[On('habit-check-in-saved')]
    public function refreshStats(): void
    {
        unset($this->month, $this->streaks);
    }

    /**
     * Get the aggregate progress for the current month so far.
     */
    #[Computed]
    public function month(): ProgressSummary
    {
        $today = Date::today()->toImmutable();
        $progress = app(GetHabitProgress::class);

        return $progress->summarize($progress->forRange(
            Auth::user(),
            $this->team(),
            $today->startOfMonth(),
            $today->endOfMonth()->startOfDay(),
        ));
    }

    /**
     * Get the user's current and best streaks.
     */
    #[Computed]
    public function streaks(): StreakSummary
    {
        return app(GetHabitProgress::class)->streaks(Auth::user(), $this->team());
    }

    /**
     * Get the user's current team.
     */
    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-test="progress-stats">
    <flux:card class="space-y-2" size="sm">
        <flux:subheading>{{ __('habits.dashboard.this_month') }}</flux:subheading>
        <flux:heading size="xl" class="tabular-nums" data-test="month-percentage">
            {{ $this->month->percentage }}%
        </flux:heading>
        <flux:progress
            :value="$this->month->percentage"
            :color="$this->month->percentage === 100 ? 'emerald' : 'sky'"
            :aria-label="__('habits.dashboard.this_month')"
        />
        <flux:text class="text-xs">
            {{ __('habits.dashboard.completed_days_elapsed', [
                'completed' => $this->month->completedDays,
                'total' => $this->month->totalDays,
            ]) }}
        </flux:text>
    </flux:card>

    <flux:card class="space-y-2" size="sm">
        <flux:subheading>{{ __('habits.dashboard.current_streak') }}</flux:subheading>
        <flux:heading size="xl" class="tabular-nums" data-test="current-streak">
            {{ trans_choice('habits.streak.days', $this->streaks->current, ['count' => $this->streaks->current]) }}
        </flux:heading>
        <flux:text class="text-xs">{{ __('habits.streak.rule') }}</flux:text>
    </flux:card>

    <flux:card class="space-y-2" size="sm">
        <flux:subheading>{{ __('habits.dashboard.best_streak') }}</flux:subheading>
        <flux:heading size="xl" class="tabular-nums" data-test="best-streak">
            {{ trans_choice('habits.streak.days', $this->streaks->best, ['count' => $this->streaks->best]) }}
        </flux:heading>
    </flux:card>

    <flux:card class="space-y-2" size="sm">
        <flux:subheading>{{ __('habits.dashboard.average_mood') }}</flux:subheading>
        <flux:heading size="xl" class="tabular-nums" data-test="average-mood">
            @if ($this->month->averageMood !== null)
                {{ $this->month->averageMood }} / {{ App\Models\DailyCheckIn::MOOD_MAX }}
            @else
                {{ __('habits.check_in.mood_none') }}
            @endif
        </flux:heading>
        <flux:text class="text-xs">{{ __('habits.dashboard.average_mood_hint') }}</flux:text>
    </flux:card>
</div>
