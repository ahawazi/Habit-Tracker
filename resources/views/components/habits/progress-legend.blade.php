@php
    $levels = [
        0 => 'bg-zinc-100 border-zinc-200 dark:bg-white/5 dark:border-white/10',
        1 => 'bg-amber-200 border-amber-300 dark:bg-amber-500/30 dark:border-amber-500/40',
        2 => 'bg-sky-300 border-sky-400 dark:bg-sky-500/40 dark:border-sky-400/50',
        3 => 'bg-emerald-500 border-emerald-600 dark:bg-emerald-500/70 dark:border-emerald-400/60',
    ];

    $percentages = [0 => 0, 1 => 33, 2 => 67, 3 => 100];
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-2') }} data-test="progress-legend">
    <flux:text class="text-xs font-medium">{{ __('habits.year.legend') }}</flux:text>

    @foreach ($levels as $level => $classes)
        <div class="flex items-center gap-1.5">
            <span class="block size-3 rounded-[3px] border {{ $classes }}" aria-hidden="true"></span>
            <flux:text class="text-xs tabular-nums">
                {{ $percentages[$level] }}%
                <span class="sr-only">&mdash; {{ __('habits.levels.'.$level) }}</span>
            </flux:text>
        </div>
    @endforeach
</div>
