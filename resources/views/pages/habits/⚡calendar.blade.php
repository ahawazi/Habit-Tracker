<?php

use App\Actions\Habits\GetHabitProgress;
use App\Data\DailyProgress;
use App\Data\ProgressSummary;
use App\Data\TrackerPeriod;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A standard month grid where every date shows its completion state.
 *
 * Month navigation is clamped to the tracked period, so the user can never reach a
 * month outside the supported range.
 */
new class extends Component {
    /**
     * The displayed month, as "Y-m".
     */
    public string $month = '';

    public function mount(?string $month = null): void
    {
        $requested = $month
            ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()
            : Date::today()->toImmutable()->startOfMonth();

        $this->month = TrackerPeriod::fromConfig()->clampMonth($requested)->format('Y-m');
    }

    /**
     * Move to the previous month, within the tracked period.
     */
    public function previousMonth(): void
    {
        $this->month = $this->period()->clampMonth($this->currentMonth()->subMonth())->format('Y-m');
    }

    /**
     * Move to the next month, within the tracked period.
     */
    public function nextMonth(): void
    {
        $this->month = $this->period()->clampMonth($this->currentMonth()->addMonth())->format('Y-m');
    }

    /**
     * Get the progress for every day of the displayed month, keyed by "Y-m-d".
     *
     * @return Collection<string, DailyProgress>
     */
    #[Computed]
    public function days(): Collection
    {
        $month = $this->currentMonth();

        return app(GetHabitProgress::class)->forRange(
            Auth::user(),
            $this->team(),
            $month,
            $month->endOfMonth()->startOfDay(),
        );
    }

    /**
     * Get the aggregate progress for the displayed month.
     *
     * Days after today are excluded by {@see GetHabitProgress::summarize()}, so for
     * the current month this is already an "elapsed days" total; for a past month
     * every day has elapsed, so the total is naturally the whole month.
     */
    #[Computed]
    public function summary(): ProgressSummary
    {
        return app(GetHabitProgress::class)->summarize($this->days);
    }

    /**
     * Determine whether the displayed month is the current calendar month.
     */
    #[Computed]
    public function isCurrentMonth(): bool
    {
        return $this->currentMonth()->isSameMonth(Date::today()->toImmutable());
    }

    /**
     * Determine whether the displayed month lies entirely in the future.
     *
     * A future month has no elapsed days at all, so there is nothing meaningful to
     * summarise yet.
     */
    #[Computed]
    public function isFutureMonth(): bool
    {
        return $this->currentMonth()->greaterThan(Date::today()->toImmutable()->startOfMonth());
    }

    /**
     * Get the number of blank cells needed before the first day of the month.
     */
    #[Computed]
    public function leadingBlanks(): int
    {
        $firstDay = $this->currentMonth();

        return (int) $firstDay->diffInDays($firstDay->startOfWeek());
    }

    /**
     * Get the localised weekday headings, starting on the locale's first weekday.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function weekdays(): array
    {
        $start = Date::today()->toImmutable()->startOfWeek();

        return collect(range(0, 6))
            ->map(fn (int $offset): string => $start->addDays($offset)->isoFormat('dd'))
            ->all();
    }

    /**
     * Determine whether an earlier month is reachable.
     */
    #[Computed]
    public function canGoBack(): bool
    {
        return $this->currentMonth()->greaterThan($this->period()->firstMonth());
    }

    /**
     * Determine whether a later month is reachable.
     */
    #[Computed]
    public function canGoForward(): bool
    {
        return $this->currentMonth()->lessThan($this->period()->lastMonth());
    }

    /**
     * Get the displayed month as a date instance.
     */
    protected function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    /**
     * Get the tracked period.
     */
    protected function period(): TrackerPeriod
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
        return $this->view()->title(__("habits.calendar.title"));
    }
}; ?>

<div>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('habits.calendar.title') }}</flux:heading>
            <flux:subheading>{{ __('habits.calendar.subtitle') }}</flux:subheading>
        </div>

        <flux:card class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg" data-test="calendar-month">
                    {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $month)->isoFormat('MMMM YYYY') }}
                </flux:heading>

                <div class="flex items-center gap-1">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        :disabled="! $this->canGoBack"
                        wire:click="previousMonth"
                        :aria-label="__('habits.calendar.previous_month')"
                        data-test="previous-month"
                    />
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        :disabled="! $this->canGoForward"
                        wire:click="nextMonth"
                        :aria-label="__('habits.calendar.next_month')"
                        data-test="next-month"
                    />
                </div>
            </div>

            @if ($this->isFutureMonth)
                <flux:text data-test="calendar-summary" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('habits.calendar.month_future') }}
                </flux:text>
            @else
                <flux:text data-test="calendar-summary">
                    {{ __($this->isCurrentMonth ? 'habits.calendar.month_summary_elapsed' : 'habits.calendar.month_summary', [
                        'completed' => $this->summary->completedDays,
                        'total' => $this->summary->totalDays,
                    ]) }}
                </flux:text>
            @endif

            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                @foreach ($this->weekdays as $weekday)
                    <div class="pb-1 text-center">
                        <flux:text class="text-xs font-medium uppercase">{{ $weekday }}</flux:text>
                    </div>
                @endforeach

                @for ($blank = 0; $blank < $this->leadingBlanks; $blank++)
                    <div aria-hidden="true" wire:key="blank-{{ $month }}-{{ $blank }}"></div>
                @endfor

                @foreach ($this->days as $key => $day)
                    <x-habits.day-tile
                        :progress="$day"
                        :href="route('habits.check-in', ['date' => $key])"
                    />
                @endforeach
            </div>

            <x-habits.progress-legend />
        </flux:card>

        <div>
            <flux:button
                variant="subtle"
                size="sm"
                icon="squares-2x2"
                :href="route('habits.year')"
                wire:navigate
            >
                {{ __('habits.nav.year_overview') }}
            </flux:button>
        </div>
    </div>
</div>
