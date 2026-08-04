<?php

use App\Data\TrackerPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The daily check-in page for a single selected date.
 *
 * The date is part of the URL so a day can be linked to from the weekly cards, the
 * monthly calendar and the year overview. The form itself lives in the reusable
 * habits.check-in-form component.
 */
new class extends Component {
    /**
     * The selected date, as "Y-m-d".
     */
    public string $date = '';

    public function mount(?string $date = null): void
    {
        $this->date = ($date ? CarbonImmutable::parse($date) : Date::today()->toImmutable())
            ->startOfDay()
            ->toDateString();
    }

    /**
     * Move to the previous day.
     */
    public function previousDay(): void
    {
        $this->goTo($this->selectedDate()->subDay());
    }

    /**
     * Move to the next day.
     */
    public function nextDay(): void
    {
        $this->goTo($this->selectedDate()->addDay());
    }

    /**
     * Jump back to today.
     */
    public function goToToday(): void
    {
        $this->goTo(Date::today()->toImmutable());
    }

    /**
     * Determine whether the next day is still inside the tracked period.
     */
    #[Computed]
    public function canGoForward(): bool
    {
        return $this->selectedDate()->addDay()->lessThanOrEqualTo($this->period()->end);
    }

    /**
     * Determine whether the selected date is today.
     */
    #[Computed]
    public function isToday(): bool
    {
        return $this->selectedDate()->isSameDay(Date::today()->toImmutable());
    }

    /**
     * Get the month this date belongs to, for the "back to calendar" link.
     */
    #[Computed]
    public function month(): string
    {
        return $this->selectedDate()->format('Y-m');
    }

    /**
     * Navigate to another date, keeping the URL and the nested form in sync.
     */
    protected function goTo(CarbonImmutable $date): void
    {
        $this->redirectRoute('habits.check-in', ['date' => $date->toDateString()], navigate: true);
    }

    /**
     * Get the selected date as a date instance.
     */
    protected function selectedDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date)->startOfDay();
    }

    /**
     * Get the tracked period.
     */
    protected function period(): TrackerPeriod
    {
        return TrackerPeriod::fromConfig();
    }

    /**
     * Render the page with a translated title.
     */
    public function render(): View
    {
        return $this->view()->title(__('habits.check_in.title'));
    }
}; ?>

<div>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:button
                variant="ghost"
                icon="chevron-left"
                wire:click="previousDay"
                data-test="previous-day"
            >
                {{ __('habits.check_in.previous_day') }}
            </flux:button>

            <div class="flex items-center gap-2">
                @unless ($this->isToday)
                    <flux:button variant="subtle" size="sm" wire:click="goToToday" data-test="go-to-today">
                        {{ __('habits.check_in.go_to_today') }}
                    </flux:button>
                @endunless

                <flux:button
                    variant="ghost"
                    icon-trailing="chevron-right"
                    wire:click="nextDay"
                    :disabled="! $this->canGoForward"
                    data-test="next-day"
                >
                    {{ __('habits.check_in.next_day') }}
                </flux:button>
            </div>
        </div>

        <flux:card>
            <livewire:habits.check-in-form :date="$date" :wire:key="'check-in-form-'.$date" />
        </flux:card>

        <div class="flex flex-wrap gap-2">
            <flux:button
                variant="subtle"
                size="sm"
                icon="calendar-days"
                :href="route('habits.calendar', ['month' => $this->month])"
                wire:navigate
                data-test="back-to-calendar"
            >
                {{ __('habits.nav.calendar') }}
            </flux:button>

            <flux:button
                variant="subtle"
                size="sm"
                icon="squares-2x2"
                :href="route('habits.year')"
                wire:navigate
                data-test="back-to-year"
            >
                {{ __('habits.nav.year_overview') }}
            </flux:button>
        </div>
    </div>
</div>
