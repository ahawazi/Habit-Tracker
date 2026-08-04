<?php

use App\Data\DailyProgress;
use App\Models\DailyCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('guests are redirected away from every habit page', function (string $route, array $parameters) {
    $this->get(route($route, [...$parameters, 'current_team' => $this->team->slug]))
        ->assertRedirect(route('login'));
})->with([
    'dashboard' => ['dashboard', []],
    'calendar' => ['habits.calendar', []],
    'year overview' => ['habits.year', []],
    'check-in' => ['habits.check-in', ['date' => '2026-07-15']],
]);

test('the dashboard renders the tracker', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('habits.dashboard.title'))
        ->assertSee(__('habits.dashboard.intro'))
        ->assertSee(__('habits.names.exercise'))
        ->assertSee(__('habits.names.reading'))
        ->assertSee(__('habits.names.journaling'))
        ->assertSeeHtml('data-test="check-in-form"')
        ->assertSeeHtml('data-test="progress-stats"')
        ->assertSeeHtml('data-test="weekly-overview"')
        ->assertSeeHtml('data-test="dashboard-calendar-link"')
        ->assertSeeHtml('data-test="dashboard-year-link"');
});

test('the check-in page renders for the selected date', function () {
    $this->actingAs($this->user)
        ->get(route('habits.check-in', ['date' => '2026-07-12']))
        ->assertOk()
        ->assertSee(__('habits.check_in.title'))
        ->assertSeeHtml('data-test="previous-day"')
        ->assertSeeHtml('data-test="next-day"')
        ->assertSeeHtml('data-test="save-check-in"')
        ->assertSeeHtml('data-test="mood-1"')
        ->assertSeeHtml('data-test="mood-10"')
        ->assertSeeHtml('data-test="notes"');
});

test('the check-in page defaults to today', function () {
    $this->actingAs($this->user)
        ->get(route('habits.check-in'))
        ->assertOk();

    Livewire::actingAs($this->user)
        ->test('pages::habits.check-in')
        ->assertSet('date', '2026-07-15');
});

test('the calendar page renders the current month', function () {
    $this->actingAs($this->user)
        ->get(route('habits.calendar'))
        ->assertOk()
        ->assertSee(__('habits.calendar.title'))
        ->assertSeeHtml('data-test="previous-month"')
        ->assertSeeHtml('data-test="next-month"')
        ->assertSeeHtml('data-test="progress-legend"');
});

test('the calendar renders one tile per day of the month', function () {
    $days = Livewire::actingAs($this->user)
        ->test('pages::habits.calendar')
        ->get('days');

    expect($days)->toHaveCount(31)
        ->and($days->keys()->first())->toBe('2026-07-01')
        ->and($days->keys()->last())->toBe('2026-07-31');
});

test('the calendar shows progress states for each day', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-14')->completed()->create();
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-13')->partiallyCompleted(1)->create();

    $html = $this->actingAs($this->user)
        ->get(route('habits.calendar'))
        ->assertOk()
        ->getContent();

    // Attributes are rendered across lines, so match them with whitespace tolerance.
    expect($html)->toMatch('/data-date="2026-07-14"\s+data-level="3"/')
        ->and($html)->toMatch('/data-date="2026-07-13"\s+data-level="1"/')
        ->and($html)->toMatch('/data-date="2026-07-20"\s+data-level="future"/');
});

test('the current month calendar summary reports elapsed days only, not the whole month', function () {
    $component = Livewire::actingAs($this->user)->test('pages::habits.calendar');

    expect($component->get('isCurrentMonth'))->toBeTrue()
        ->and($component->get('isFutureMonth'))->toBeFalse();

    // Today is 2026-07-15, so only the 15 elapsed days of July should be counted.
    $component->assertSee(__('habits.calendar.month_summary_elapsed', [
        'completed' => 0,
        'total' => 15,
    ]));
});

test('a future month hides completion statistics entirely', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::habits.calendar')
        ->call('nextMonth')
        ->assertSet('month', '2026-08');

    expect($component->get('isFutureMonth'))->toBeTrue();

    $component->assertSee(__('habits.calendar.month_future'));
});

test('the calendar highlights today distinctly from every other day', function () {
    $html = $this->actingAs($this->user)
        ->get(route('habits.calendar'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/aria-current="date"[^>]*data-date="2026-07-15"/');
});

test('a future day is labelled as upcoming rather than showing false progress', function () {
    $html = $this->actingAs($this->user)
        ->get(route('habits.calendar'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('habits.day_summary.future', [
        'date' => CarbonImmutable::parse('2026-07-20')->isoFormat('LL'),
    ]));
});

test('the calendar cannot navigate before the tracked period starts', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.calendar')
        ->assertSet('month', '2026-07')
        ->assertSet('canGoBack', false)
        ->call('previousMonth')
        ->assertSet('month', '2026-07');
});

test('the calendar cannot navigate past the end of the tracked period', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.calendar', ['month' => '2026-12'])
        ->assertSet('month', '2026-12')
        ->assertSet('canGoForward', false)
        ->call('nextMonth')
        ->assertSet('month', '2026-12');
});

test('the calendar navigates between months inside the period', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.calendar')
        ->call('nextMonth')
        ->assertSet('month', '2026-08')
        ->call('nextMonth')
        ->assertSet('month', '2026-09')
        ->call('previousMonth')
        ->assertSet('month', '2026-08');
});

test('a month requested outside the period is clamped into it', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.calendar', ['month' => '2026-01'])
        ->assertSet('month', '2026-07');
});

test('the year overview page renders', function () {
    $this->actingAs($this->user)
        ->get(route('habits.year'))
        ->assertOk()
        ->assertSee(__('habits.year.title'))
        ->assertSeeHtml('data-test="year-percentage"')
        ->assertSeeHtml('data-test="year-completed-days"')
        ->assertSeeHtml('data-test="year-current-streak"')
        ->assertSeeHtml('data-test="year-best-streak"')
        ->assertSeeHtml('data-test="progress-legend"');
});

test('the year overview covers today through the end of the period', function () {
    $days = Livewire::actingAs($this->user)
        ->test('pages::habits.year')
        ->get('days');

    expect($days->keys()->first())->toBe('2026-07-15')
        ->and($days->keys()->last())->toBe('2026-12-31')
        ->and($days)->toHaveCount(170);
});

test('the year overview groups days into aligned week columns with month labels', function () {
    $weeks = Livewire::actingAs($this->user)
        ->test('pages::habits.year')
        ->get('weeks');

    expect($weeks->every(fn (array $week): bool => count($week['days']) === 7))->toBeTrue();

    $labels = $weeks->pluck('label')->filter()->values()->all();

    expect($labels)->toContain('Jul')
        ->and($labels)->toContain('Dec');
});

test('the year overview never labels or renders a date beyond 31 December 2026', function () {
    $component = Livewire::actingAs($this->user)->test('pages::habits.year');

    $labels = $component->get('weeks')->pluck('label')->filter()->values();
    expect($labels)->not->toContain('Jan');

    $years = $component->get('weeks')
        ->flatMap(fn (array $week): array => $week['days'])
        ->filter()
        ->map(fn (DailyProgress $day): string => $day->date->format('Y'))
        ->unique()
        ->values()
        ->all();
    expect($years)->toBe(['2026']);

    $html = $this->actingAs($this->user)->get(route('habits.year'))->assertOk()->getContent();
    expect($html)->not->toContain('data-date="2027');
});

test('the year overview links every day to its check-in', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->completed()->create();

    $response = $this->actingAs($this->user)
        ->get(route('habits.year'))
        ->assertOk()
        ->assertSeeHtml(route('habits.check-in', ['date' => '2026-07-15'], absolute: false));

    expect($response->getContent())->toMatch('/data-date="2026-07-15"\s+data-level="3"/');
});

test('the weekly overview shows seven day cards for the current week', function () {
    $days = Livewire::actingAs($this->user)
        ->test('habits.weekly-overview')
        ->get('days');

    expect($days)->toHaveCount(7)
        ->and($days->keys()->first())->toBe('2026-07-13')
        ->and($days->keys()->last())->toBe('2026-07-19');
});

test('the weekly overview reports mood and progress per day', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-14')
        ->partiallyCompleted(2)
        ->create(['mood_score' => 8]);

    $component = Livewire::actingAs($this->user)->test('habits.weekly-overview');

    $day = $component->get('days')->get('2026-07-14');

    expect($day->percentage)->toBe(67)
        ->and($day->moodScore)->toBe(8);

    $component->assertSeeHtml('data-date="2026-07-14"');
});

test('the dashboard stats reflect saved check-ins', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-14')->completed()->create(['mood_score' => 8]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->completed()->create(['mood_score' => 6]);

    $component = Livewire::actingAs($this->user)->test('habits.progress-stats');

    expect($component->get('streaks')->current)->toBe(2)
        ->and($component->get('streaks')->best)->toBe(2)
        ->and($component->get('month')->completedDays)->toBe(2)
        ->and($component->get('month')->averageMood)->toBe(7.0);
});

test('the sidebar links to every tracker page', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('habits.nav.calendar'))
        ->assertSee(__('habits.nav.year_overview'))
        ->assertSeeHtml(route('habits.calendar', absolute: false))
        ->assertSeeHtml(route('habits.year', absolute: false));
});

test('existing team and settings pages still work', function () {
    $this->actingAs($this->user)->get(route('teams.index'))->assertOk();
    $this->actingAs($this->user)->get(route('profile.edit'))->assertOk();
    $this->actingAs($this->user)->get(route('teams.edit', ['team' => $this->team->slug]))->assertOk();
});
