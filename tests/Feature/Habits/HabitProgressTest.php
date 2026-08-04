<?php

use App\Actions\Habits\GetHabitProgress;
use App\Models\DailyCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->progress = app(GetHabitProgress::class);
});

test('daily progress is derived from completion records, not stored', function (int $completed, int $percentage) {
    DailyCheckIn::factory()
        ->forUser($this->user, $this->team)
        ->on('2026-07-15')
        ->partiallyCompleted($completed)
        ->create();

    $day = $this->progress->forDate($this->user, $this->team, CarbonImmutable::parse('2026-07-15'));

    expect($day->completedCount)->toBe($completed)
        ->and($day->totalCount)->toBe(3)
        ->and($day->percentage)->toBe($percentage);
})->with([
    [0, 0],
    [1, 33],
    [2, 67],
    [3, 100],
]);

test('a single day range finds that day\'s check-in', function () {
    DailyCheckIn::factory()
        ->forUser($this->user, $this->team)
        ->on('2026-07-15')
        ->completed()
        ->create(['mood_score' => 9]);

    $days = $this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($days)->toHaveCount(1)
        ->and($days->get('2026-07-15')->hasCheckIn)->toBeTrue()
        ->and($days->get('2026-07-15')->percentage)->toBe(100)
        ->and($days->get('2026-07-15')->moodScore)->toBe(9);
});

test('a range includes check-ins on both of its boundary days', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-10')->completed()->create();
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-14')->completed()->create();

    $days = $this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-14'),
    );

    expect($days->get('2026-07-10')->percentage)->toBe(100)
        ->and($days->get('2026-07-14')->percentage)->toBe(100)
        ->and($days->get('2026-07-12')->percentage)->toBe(0);
});

test('a date without a check-in reports zero progress', function () {
    $day = $this->progress->forDate($this->user, $this->team, CarbonImmutable::parse('2026-07-09'));

    expect($day->hasCheckIn)->toBeFalse()
        ->and($day->percentage)->toBe(0)
        ->and($day->moodScore)->toBeNull();

    expect(DailyCheckIn::count())->toBe(0);
});

test('a range returns one entry per day without creating rows', function () {
    $days = $this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-13'),
        CarbonImmutable::parse('2026-07-19'),
    );

    expect($days)->toHaveCount(7)
        ->and($days->keys()->all())->toBe([
            '2026-07-13', '2026-07-14', '2026-07-15',
            '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
        ]);

    expect(DailyCheckIn::count())->toBe(0);
});

test('a range flags future days and today', function () {
    $days = $this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-14'),
        CarbonImmutable::parse('2026-07-16'),
    );

    expect($days->get('2026-07-14')->isFuture)->toBeFalse()
        ->and($days->get('2026-07-15')->isToday)->toBeTrue()
        ->and($days->get('2026-07-15')->isFuture)->toBeFalse()
        ->and($days->get('2026-07-16')->isFuture)->toBeTrue();
});

test('a summary ignores future days so percentages stay honest', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-14')->completed()->create();
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->completed()->create();

    // 13 to 19 July: only 13, 14 and 15 have happened.
    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-13'),
        CarbonImmutable::parse('2026-07-19'),
    ));

    expect($summary->totalDays)->toBe(3)
        ->and($summary->completedDays)->toBe(2)
        ->and($summary->completedHabits)->toBe(6)
        ->and($summary->possibleHabits)->toBe(9)
        ->and($summary->percentage)->toBe(67);
});

test('a fully past month counts every day, not just elapsed ones', function () {
    // Today is 2026-07-15, so June is entirely in the past: all 30 days have
    // already elapsed and should be counted, not just some subset of them.
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-06-01')->completed()->create();

    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-06-01'),
        CarbonImmutable::parse('2026-06-30'),
    ));

    expect($summary->totalDays)->toBe(30)
        ->and($summary->completedDays)->toBe(1);
});

test('a fully future range has nothing to summarise yet', function () {
    // Today is 2026-07-15, so August is entirely in the future.
    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-31'),
    ));

    expect($summary->totalDays)->toBe(0)
        ->and($summary->completedDays)->toBe(0)
        ->and($summary->percentage)->toBe(0);
});

test('the monthly average mood ignores days without a mood score', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-10')->create(['mood_score' => 8]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-11')->create(['mood_score' => 6]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-12')->withoutMood()->create();

    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-07-31'),
    ));

    expect($summary->averageMood)->toBe(7.0);
});

test('the average mood is null when no mood was ever recorded', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-10')->withoutMood()->create();

    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-07-31'),
    ));

    expect($summary->averageMood)->toBeNull();
});

test('the average mood is rounded to one decimal place', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-10')->create(['mood_score' => 8]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-11')->create(['mood_score' => 7]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-12')->create(['mood_score' => 6]);
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-13')->create(['mood_score' => 6]);

    $summary = $this->progress->summarize($this->progress->forRange(
        $this->user,
        $this->team,
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-07-31'),
    ));

    expect($summary->averageMood)->toBe(6.8);
});

test('an inactive habit is excluded from the daily total', function () {
    $this->team->habits()->where('slug', 'journaling')->update(['is_active' => false]);

    DailyCheckIn::factory()
        ->forUser($this->user, $this->team)
        ->on('2026-07-15')
        ->completed()
        ->create();

    $day = app(GetHabitProgress::class)->forDate($this->user, $this->team, CarbonImmutable::parse('2026-07-15'));

    expect($day->totalCount)->toBe(2)
        ->and($day->completedCount)->toBe(2)
        ->and($day->percentage)->toBe(100);
});
