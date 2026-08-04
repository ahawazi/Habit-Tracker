<?php

use App\Actions\Habits\GetHabitProgress;
use App\Data\DailyProgress;
use App\Data\ProgressSummary;
use App\Data\StreakSummary;
use App\Data\TrackerPeriod;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A compact heatmap covering today through the end of the tracked period.
 *
 * Days are laid out in GitHub-style columns of weeks so several months fit on one
 * screen without a single database row being created for an untouched date.
 */
new class extends Component {
    /**
     * Get the progress for every day in the tracked period, keyed by "Y-m-d".
     *
     * @return Collection<string, DailyProgress>
     */
    #[Computed]
    public function days(): Collection
    {
        $period = $this->period();

        return app(GetHabitProgress::class)->forRange(
            Auth::user(),
            $this->team(),
            $period->start,
            $period->end,
        );
    }

    /**
     * Group the period into week columns of seven weekday rows.
     *
     * Slots before the period starts or after it ends are null, which renders as an
     * empty gap so every column stays aligned to the same weekday.
     *
     * @return Collection<int, array{label: string|null, days: array<int, DailyProgress|null>}>
     */
    #[Computed]
    public function weeks(): Collection
    {
        $period = $this->period();
        $days = $this->days;

        /** @var Collection<int, array{label: string|null, days: array<int, DailyProgress|null>}> $weeks */
        $weeks = new Collection;

        $cursor = $period->start->startOfWeek();
        $lastLabelledMonth = null;

        while ($cursor->lessThanOrEqualTo($period->end)) {
            $slots = [];

            for ($offset = 0; $offset < 7; $offset++) {
                $slots[] = $days->get($cursor->addDays($offset)->toDateString());
            }

            // Label a column when it introduces a month not yet labelled. The anchor
            // is clamped to the period's end so a week that trails off past 31
            // December never mislabels its (otherwise blank) column as next January.
            $weekEnd = $cursor->addDays(6);
            $anchor = $weekEnd->greaterThan($period->end) ? $period->end : $weekEnd;
            $month = $anchor->format('Y-m');
            $label = $month !== $lastLabelledMonth
                ? CarbonImmutable::createFromFormat('Y-m', $month)->isoFormat('MMM')
                : null;

            if ($label !== null) {
                $lastLabelledMonth = $month;
            }

            $weeks->push(['label' => $label, 'days' => $slots]);

            $cursor = $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * Get the aggregate progress across the tracked period.
     */
    #[Computed]
    public function summary(): ProgressSummary
    {
        return app(GetHabitProgress::class)->summarize($this->days);
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
     * Get the localised weekday row labels.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function weekdays(): array
    {
        $start = $this->period()->start->startOfWeek();

        return collect(range(0, 6))
            ->map(fn (int $offset): string => $start->addDays($offset)->isoFormat('dd'))
            ->all();
    }

    /**
     * Get the tracked period.
     */
    #[Computed]
    public function period(): TrackerPeriod
    {
        return TrackerPeriod::fromConfig();
    }

    /**
     * Get the user's current team.
     */
    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    /**
     * Render the page with a translated title.
     */
    public function render(): View
    {
        return $this->view()->title(__("habits.year.title"));
    }
}; ?>

<div>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('habits.year.title') }}</flux:heading>
            <flux:subheading>
                {{ __('habits.year.subtitle', ['end' => $this->period->end->isoFormat('LL')]) }}
            </flux:subheading>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="space-y-1" size="sm">
                <flux:subheading>{{ __('habits.year.overall_completion') }}</flux:subheading>
                <flux:heading size="xl" class="tabular-nums" data-test="year-percentage">
                    {{ $this->summary->percentage }}%
                </flux:heading>
                <flux:progress
                    :value="$this->summary->percentage"
                    :aria-label="__('habits.year.overall_completion')"
                />
            </flux:card>

            <flux:card class="space-y-1" size="sm">
                <flux:subheading>{{ __('habits.year.completed_days') }}</flux:subheading>
                <flux:heading size="xl" class="tabular-nums" data-test="year-completed-days">
                    {{ $this->summary->completedDays }}
                </flux:heading>
                <flux:text class="text-xs">
                    {{ __('habits.dashboard.completed_days', [
                        'completed' => $this->summary->completedDays,
                        'total' => $this->summary->totalDays,
                    ]) }}
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1" size="sm">
                <flux:subheading>{{ __('habits.year.current_streak') }}</flux:subheading>
                <flux:heading size="xl" class="tabular-nums" data-test="year-current-streak">
                    {{ trans_choice('habits.streak.days', $this->streaks->current, ['count' => $this->streaks->current]) }}
                </flux:heading>
            </flux:card>

            <flux:card class="space-y-1" size="sm">
                <flux:subheading>{{ __('habits.year.best_streak') }}</flux:subheading>
                <flux:heading size="xl" class="tabular-nums" data-test="year-best-streak">
                    {{ trans_choice('habits.streak.days', $this->streaks->best, ['count' => $this->streaks->best]) }}
                </flux:heading>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <div class="overflow-x-auto pb-1">
                <div class="flex min-w-max gap-1.5">
                    {{-- Weekday row labels, shown on alternating rows to save space. --}}
                    <div class="me-1 flex flex-col gap-1.5 pt-5">
                        @foreach ($this->weekdays as $index => $weekday)
                            <div class="flex h-3.5 items-center sm:h-4">
                                @if ($index % 2 === 0)
                                    <flux:text class="text-[10px] leading-none">{{ $weekday }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @foreach ($this->weeks as $index => $week)
                        <div class="flex flex-col gap-1.5" wire:key="week-column-{{ $index }}">
                            <div class="h-4">
                                @if ($week['label'])
                                    <flux:text class="text-[10px] leading-none whitespace-nowrap">
                                        {{ $week['label'] }}
                                    </flux:text>
                                @endif
                            </div>

                            @foreach ($week['days'] as $slot => $day)
                                @if ($day)
                                    <x-habits.day-tile
                                        variant="heatmap"
                                        :progress="$day"
                                        :href="route('habits.check-in', ['date' => $day->date->toDateString()])"
                                    />
                                @else
                                    <div
                                        class="size-3.5 sm:size-4"
                                        aria-hidden="true"
                                        wire:key="year-gap-{{ $index }}-{{ $slot }}"
                                    ></div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <x-habits.progress-legend />

            <flux:text class="text-xs">{{ __('habits.streak.rule') }}</flux:text>
        </flux:card>

        <div>
            <flux:button
                variant="subtle"
                size="sm"
                icon="calendar-days"
                :href="route('habits.calendar')"
                wire:navigate
            >
                {{ __('habits.nav.calendar') }}
            </flux:button>
        </div>
    </div>
</div>
