<x-layouts::app :title="__('habits.dashboard.title')">
    <livewire:pages::teams.pending-invitations-modal />

    <div class="flex w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('habits.dashboard.title') }}</flux:heading>
                <flux:subheading>{{ __('habits.dashboard.intro') }}</flux:subheading>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="calendar-days"
                    :href="route('habits.calendar')"
                    wire:navigate
                    data-test="dashboard-calendar-link"
                >
                    {{ __('habits.dashboard.view_calendar') }}
                </flux:button>

                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="squares-2x2"
                    :href="route('habits.year')"
                    wire:navigate
                    data-test="dashboard-year-link"
                >
                    {{ __('habits.dashboard.view_year') }}
                </flux:button>
            </div>
        </div>

        <livewire:habits.progress-stats />

        <div class="grid items-start gap-6 xl:grid-cols-5">
            <flux:card class="xl:col-span-2">
                <livewire:habits.check-in-form :compact="true" />
            </flux:card>

            <div class="xl:col-span-3">
                <livewire:habits.weekly-overview />
            </div>
        </div>
    </div>
</x-layouts::app>
