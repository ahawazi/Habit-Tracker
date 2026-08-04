<?php

use App\Actions\Habits\GetHabitProgress;
use App\Actions\Habits\SaveDailyCheckIn;
use App\Data\DailyProgress;
use App\Models\DailyCheckIn;
use App\Models\Habit;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The single check-in editor, reused by the dashboard and the daily check-in page.
 *
 * All reads and writes are scoped to the authenticated user and their current team,
 * and every save is authorised through the DailyCheckIn policy.
 */
new class extends Component {
    /**
     * The date being edited, as "Y-m-d".
     */
    public string $date = '';

    /**
     * Whether to render the compact dashboard heading instead of the page heading.
     */
    public bool $compact = false;

    /**
     * Completion state keyed by habit ID.
     *
     * @var array<int, bool>
     */
    public array $completions = [];

    public ?int $moodScore = null;

    public string $notes = '';

    public function mount(?string $date = null, bool $compact = false): void
    {
        $this->compact = $compact;
        $this->date = ($date ? CarbonImmutable::parse($date) : Date::today()->toImmutable())
            ->startOfDay()
            ->toDateString();

        $this->loadCheckIn();
    }

    /**
     * Persist the check-in for the selected date.
     */
    public function save(SaveDailyCheckIn $saveDailyCheckIn): void
    {
        $team = $this->team();

        Gate::authorize('create', [DailyCheckIn::class, $team, $this->selectedDate()]);

        $validated = $this->validate();

        $saveDailyCheckIn->handle(
            user: Auth::user(),
            team: $team,
            date: $this->selectedDate(),
            completions: $this->normalizedCompletions(),
            moodScore: $validated['moodScore'],
            notes: filled($validated['notes']) ? $validated['notes'] : null,
        );

        unset($this->progress);

        Flux::toast(variant: 'success', text: __('habits.check_in.saved'));

        // Let the weekly cards and stats tiles on the same page pick up the change.
        $this->dispatch('habit-check-in-saved');
    }

    /**
     * Clear the recorded mood for the selected date.
     */
    public function clearMood(): void
    {
        $this->moodScore = null;
    }

    /**
     * Get the team's active habits.
     *
     * @return Collection<int, Habit>
     */
    #[Computed]
    public function habits(): Collection
    {
        return app(GetHabitProgress::class)->habits($this->team());
    }

    /**
     * Get the saved progress for the selected date.
     */
    #[Computed]
    public function progress(): DailyProgress
    {
        return app(GetHabitProgress::class)->forDate(Auth::user(), $this->team(), $this->selectedDate());
    }

    /**
     * Get the number of habits currently ticked in the form.
     */
    #[Computed]
    public function pendingCompletedCount(): int
    {
        return count(array_filter($this->normalizedCompletions()));
    }

    /**
     * Get the completion percentage of the form's current state.
     */
    #[Computed]
    public function pendingPercentage(): int
    {
        return DailyProgress::percentageFor($this->pendingCompletedCount, $this->habits->count());
    }

    /**
     * Determine whether the selected date is still ahead of today.
     */
    #[Computed]
    public function isFuture(): bool
    {
        return $this->selectedDate()->greaterThan(Date::today()->toImmutable());
    }

    /**
     * Get the selected date as a date instance.
     */
    protected function selectedDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date)->startOfDay();
    }

    /**
     * Get the user's current team.
     */
    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    /**
     * Load the stored check-in into the form.
     */
    protected function loadCheckIn(): void
    {
        $progress = $this->progress;

        $this->moodScore = $progress->moodScore;
        $this->notes = $progress->notes ?? '';

        $checkIn = DailyCheckIn::query()
            ->ownedBy(Auth::user(), $this->team())
            ->whereDate('date', $this->selectedDate()->toDateString())
            ->with('completions')
            ->first();

        $completed = $checkIn
            ? $checkIn->completions->where('completed', true)->pluck('habit_id')->all()
            : [];

        $this->completions = $this->habits
            ->mapWithKeys(fn (Habit $habit): array => [$habit->id => in_array($habit->id, $completed, true)])
            ->all();
    }

    /**
     * Get the form's completion state with integer habit IDs and boolean values.
     *
     * Only habits belonging to the current team are considered, so a habit ID
     * injected from another team is discarded rather than saved.
     *
     * @return array<int, bool>
     */
    protected function normalizedCompletions(): array
    {
        $normalized = [];

        foreach ($this->habits as $habit) {
            $normalized[$habit->id] = (bool) ($this->completions[$habit->id] ?? false);
        }

        return $normalized;
    }

    /**
     * Get the validation rules that apply to the form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'moodScore' => ['nullable', 'integer', 'between:'.DailyCheckIn::MOOD_MIN.','.DailyCheckIn::MOOD_MAX],
            'notes' => ['nullable', 'string', 'max:2000'],
            'completions' => ['array'],
            'completions.*' => ['boolean'],
        ];
    }

    /**
     * Get the validation messages that apply to the form.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'moodScore.integer' => __('habits.validation.mood_range', [
                'min' => DailyCheckIn::MOOD_MIN,
                'max' => DailyCheckIn::MOOD_MAX,
            ]),
            'moodScore.between' => __('habits.validation.mood_range', [
                'min' => DailyCheckIn::MOOD_MIN,
                'max' => DailyCheckIn::MOOD_MAX,
            ]),
            'notes.max' => __('habits.validation.notes_max', ['max' => 2000]),
        ];
    }
}; ?>

<div data-test="check-in-form">
    <form wire:submit="save" class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading :size="$compact ? 'lg' : 'xl'">
                    {{ $compact ? __('habits.check_in.today_title') : __('habits.check_in.title') }}
                </flux:heading>
                <flux:subheading>{{ $this->progress->date->isoFormat('dddd, LL') }}</flux:subheading>
            </div>

            <flux:badge :color="$this->pendingPercentage === 100 ? 'emerald' : 'zinc'" data-test="check-in-percentage">
                {{ __('habits.check_in.progress_value', ['percentage' => $this->pendingPercentage]) }}
            </flux:badge>
        </div>

        @if ($this->isFuture)
            <flux:callout variant="warning" icon="clock" data-test="future-notice">
                <flux:callout.text>{{ __('habits.check_in.future_notice') }}</flux:callout.text>
            </flux:callout>
        @endif

        @error('date')
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @enderror

        @if ($this->habits->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>{{ __('habits.empty.no_habits') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:fieldset>
                <flux:legend>{{ __('habits.check_in.habits_label') }}</flux:legend>

                <div class="space-y-2">
                    @foreach ($this->habits as $habit)
                        <flux:checkbox
                            wire:key="habit-{{ $habit->id }}"
                            wire:model.live="completions.{{ $habit->id }}"
                            :label="$habit->displayName()"
                            :disabled="$this->isFuture"
                            :data-test="'habit-'.$habit->slug"
                        />
                    @endforeach
                </div>
            </flux:fieldset>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <flux:text class="text-sm font-medium">{{ __('habits.check_in.progress') }}</flux:text>
                    <flux:text class="text-sm tabular-nums">
                        {{ __('habits.check_in.habits_completed', [
                            'completed' => $this->pendingCompletedCount,
                            'total' => $this->habits->count(),
                        ]) }}
                    </flux:text>
                </div>

                <flux:progress
                    :value="$this->pendingPercentage"
                    :color="$this->pendingPercentage === 100 ? 'emerald' : 'sky'"
                    :aria-label="__('habits.check_in.progress')"
                />
            </div>

            <flux:field>
                <div class="flex items-center justify-between gap-2">
                    <flux:label>{{ __('habits.check_in.mood_label') }}</flux:label>

                    @if ($moodScore !== null && ! $this->isFuture)
                        <flux:button
                            type="button"
                            variant="subtle"
                            size="xs"
                            wire:click="clearMood"
                            data-test="clear-mood"
                        >
                            {{ __('habits.check_in.mood_clear') }}
                        </flux:button>
                    @endif
                </div>

                <flux:description>{{ __('habits.check_in.mood_description') }}</flux:description>

                {{-- Ten options do not fit a segmented control on a phone, so this is an
                     accessible radio group that reflows from 5 to 10 columns. --}}
                <div
                    role="radiogroup"
                    aria-label="{{ __('habits.check_in.mood_label') }}"
                    class="mt-1.5 grid grid-cols-5 gap-1 sm:grid-cols-10"
                >
                    @foreach (range(App\Models\DailyCheckIn::MOOD_MIN, App\Models\DailyCheckIn::MOOD_MAX) as $score)
                        <label class="relative">
                            <input
                                type="radio"
                                wire:model="moodScore"
                                value="{{ $score }}"
                                @disabled($this->isFuture)
                                class="peer sr-only"
                                data-test="mood-{{ $score }}"
                            />
                            <span
                                aria-hidden="true"
                                class="flex h-8 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-medium text-zinc-700 tabular-nums transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-foreground peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-disabled:cursor-not-allowed peer-disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200"
                            >
                                {{ $score }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <flux:error name="moodScore" />
            </flux:field>

            <flux:textarea
                wire:model="notes"
                :label="__('habits.check_in.notes_label')"
                :placeholder="__('habits.check_in.notes_placeholder')"
                :description="__('habits.check_in.notes_description')"
                :disabled="$this->isFuture"
                rows="2"
                data-test="notes"
            />

            <div class="flex justify-end">
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                    :disabled="$this->isFuture"
                    data-test="save-check-in"
                >
                    {{ __('habits.check_in.save') }}
                </flux:button>
            </div>
        @endif
    </form>
</div>
