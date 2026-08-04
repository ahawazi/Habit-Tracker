<?php

use App\Actions\Habits\GetHabitProgress;
use App\Data\DailyProgress;
use App\Data\ProgressSummary;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The current week as seven compact, clickable day cards.
 */
new class extends Component {
    /**
     * Refresh the cards after a check-in is saved elsewhere on the page.
     */
    #[On('habit-check-in-saved')]
    public function refreshWeek(): void
    {
        unset($this->days, $this->summary);
    }

    /**
     * Get the seven days of the current week.
     *
     * @return Collection<string, DailyProgress>
     */
    #[Computed]
    public function days(): Collection
    {
        $today = Date::today()->toImmutable();

        return app(GetHabitProgress::class)->forRange(
            Auth::user(),
            $this->team(),
            $today->startOfWeek(),
            $today->endOfWeek()->startOfDay(),
        );
    }

    /**
     * Get the aggregate progress for the week so far.
     */
    #[Computed]
    public function summary(): ProgressSummary
    {
        return app(GetHabitProgress::class)->summarize($this->days);
    }

    /**
     * Get the user's current team.
     */
    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<flux:card class="space-y-4" data-test="weekly-overview">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <flux:heading size="lg">{{ __('habits.week.title') }}</flux:heading>
            <flux:subheading>{{ __('habits.week.subtitle') }}</flux:subheading>
        </div>

        <flux:badge color="zinc" data-test="week-percentage">
            {{ __('habits.check_in.progress_value', ['percentage' => $this->summary->percentage]) }}
        </flux:badge>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ($this->days as $key => $day)
            <a
                href="{{ route('habits.check-in', ['date' => $key]) }}"
                wire:navigate
                wire:key="week-{{ $key }}"
                aria-label="{{ __('habits.week.open_day', ['date' => $day->date->isoFormat('LL')]) }}"
                data-test="week-day"
                data-date="{{ $key }}"
                @class([
                    'flex flex-col gap-1.5 rounded-lg border p-3 transition hover:border-zinc-400 focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:outline-hidden dark:hover:border-zinc-500 dark:focus-visible:ring-white',
                    'border-zinc-200 dark:border-white/10' => ! $day->isToday,
                    'border-zinc-900 dark:border-white' => $day->isToday,
                    'opacity-60' => $day->isFuture,
                ])
            >
                <div class="flex items-baseline justify-between gap-1">
                    <flux:text class="text-xs font-medium uppercase">{{ $day->date->isoFormat('ddd') }}</flux:text>
                    <flux:text class="text-sm font-semibold tabular-nums">{{ $day->date->format('j') }}</flux:text>
                </div>

                <flux:progress
                    :value="$day->percentage"
                    :color="$day->isComplete() ? 'emerald' : 'sky'"
                    :aria-label="__('habits.check_in.progress')"
                />

                <div class="flex items-center justify-between gap-1">
                    <flux:text class="text-xs tabular-nums">
                        @if ($day->isFuture)
                            &mdash;
                        @else
                            {{ $day->percentage }}%
                        @endif
                    </flux:text>

                    @if ($day->isComplete())
                        <flux:icon.check-circle variant="micro" class="text-emerald-600 dark:text-emerald-400" />
                    @endif
                </div>

                <flux:text class="text-xs tabular-nums">
                    @if ($day->moodScore !== null)
                        {{ __('habits.check_in.mood_label') }}: {{ $day->moodScore }}
                    @else
                        <span class="text-zinc-400 dark:text-zinc-500">{{ __('habits.check_in.mood_none') }}</span>
                    @endif
                </flux:text>
            </a>
        @endforeach
    </div>
</flux:card>
