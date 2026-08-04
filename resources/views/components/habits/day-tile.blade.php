@props([
    'progress',
    'href' => null,
    'variant' => 'calendar',
])

@php
    /** @var \App\Data\DailyProgress $progress */
    $level = $progress->level();

    // Each level differs in lightness as well as hue, so the states remain
    // distinguishable without colour perception. The accessible label below always
    // states the percentage outright.
    $fill = match (true) {
        $progress->isFuture => 'bg-white dark:bg-white/5 border-dashed border-zinc-300 dark:border-zinc-700',
        $level === 0 => 'bg-zinc-100 border-zinc-200 dark:bg-white/5 dark:border-white/10',
        $level === 1 => 'bg-amber-200 border-amber-300 dark:bg-amber-500/30 dark:border-amber-500/40',
        $level === 2 => 'bg-sky-300 border-sky-400 dark:bg-sky-500/40 dark:border-sky-400/50',
        default => 'bg-emerald-500 border-emerald-600 dark:bg-emerald-500/70 dark:border-emerald-400/60',
    };

    $label = $progress->isFuture
        ? __('habits.day_summary.future', ['date' => $progress->date->isoFormat('LL')])
        : ($progress->moodScore !== null
            ? __('habits.day_summary.with_mood', [
                'date' => $progress->date->isoFormat('LL'),
                'completed' => $progress->completedCount,
                'total' => $progress->totalCount,
                'percentage' => $progress->percentage,
                'mood' => $progress->moodScore,
                'max' => \App\Models\DailyCheckIn::MOOD_MAX,
            ])
            : __('habits.day_summary.without_mood', [
                'date' => $progress->date->isoFormat('LL'),
                'completed' => $progress->completedCount,
                'total' => $progress->totalCount,
                'percentage' => $progress->percentage,
            ]));

    // The ring highlight already marks today visually; the label states it outright
    // too, so the cue never depends on colour or border alone.
    if ($progress->isToday) {
        $label = __('habits.calendar.today_badge').' — '.$label;
    }
@endphp

@if ($variant === 'heatmap')
    <a
        href="{{ $href }}"
        wire:navigate
        wire:key="year-{{ $progress->date->toDateString() }}"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        aria-current="{{ $progress->isToday ? 'date' : 'false' }}"
        data-test="year-day"
        data-date="{{ $progress->date->toDateString() }}"
        data-level="{{ $progress->isFuture ? 'future' : $level }}"
        @class([
            'block size-3.5 rounded-[3px] border transition hover:scale-110 hover:ring-2 hover:ring-zinc-500 focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:outline-hidden dark:hover:ring-zinc-300 dark:focus-visible:ring-white sm:size-4',
            $fill,
            'ring-2 ring-zinc-900 ring-offset-1 ring-offset-white dark:ring-white dark:ring-offset-zinc-900' => $progress->isToday,
        ])
    ></a>
@else
    <a
        href="{{ $href }}"
        wire:navigate
        wire:key="calendar-{{ $progress->date->toDateString() }}"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        aria-current="{{ $progress->isToday ? 'date' : 'false' }}"
        data-test="calendar-day"
        data-date="{{ $progress->date->toDateString() }}"
        data-level="{{ $progress->isFuture ? 'future' : $level }}"
        @class([
            'relative flex aspect-square flex-col items-center justify-center gap-0.5 rounded-lg border p-1 transition hover:z-10 hover:scale-105 hover:shadow-sm hover:ring-2 hover:ring-zinc-500 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:outline-hidden dark:hover:ring-zinc-300 dark:focus-visible:ring-white',
            $fill,
            'ring-2 ring-zinc-900 dark:ring-white' => $progress->isToday,
        ])
    >
        <span @class([
            'text-sm font-semibold leading-none',
            'text-white dark:text-white' => $level === 3 && ! $progress->isFuture,
            'text-zinc-900 dark:text-white' => $level !== 3 || $progress->isFuture,
        ])>
            {{ $progress->date->format('j') }}
        </span>

        <span @class([
            'text-[10px] leading-none tabular-nums',
            'text-white/90' => $level === 3 && ! $progress->isFuture,
            'text-zinc-600 dark:text-zinc-300' => $level !== 3 || $progress->isFuture,
        ])>
            @if ($progress->isFuture)
                &mdash;
            @else
                {{ $progress->percentage }}%
            @endif
        </span>

        @if ($level === 3 && ! $progress->isFuture)
            <flux:icon.check-circle variant="micro" class="absolute right-0.5 bottom-0.5 text-white/90" />
        @endif

        @if ($progress->moodScore !== null)
            <span class="text-[10px] leading-none text-zinc-600 tabular-nums dark:text-zinc-300" aria-hidden="true">
                &#9829;{{ $progress->moodScore }}
            </span>
        @endif
    </a>
@endif
