<?php

use App\Actions\Habits\GetHabitProgress;
use App\Models\DailyCheckIn;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Streak rules under test:
 *  - a day counts only when all three habits are completed;
 *  - the current streak is the unbroken run ending today, and an incomplete today
 *    does not break it (the run then ends at yesterday);
 *  - the best streak is the longest unbroken run in the user's whole history.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->progress = app(GetHabitProgress::class);
});

/**
 * Record a fully completed day.
 */
function completeDay(User $user, Team $team, string $date): DailyCheckIn
{
    return DailyCheckIn::factory()->forUser($user, $team)->on($date)->completed()->create();
}

/**
 * Record a day with only some habits completed.
 */
function partialDay(User $user, Team $team, string $date, int $completed): DailyCheckIn
{
    return DailyCheckIn::factory()->forUser($user, $team)->on($date)->partiallyCompleted($completed)->create();
}

test('no completed days means no streak', function () {
    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(0)
        ->and($streaks->best)->toBe(0);
});

test('days with no check-in at all mean no streak', function () {
    expect(DailyCheckIn::count())->toBe(0);

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(0)
        ->and($streaks->best)->toBe(0);
});

test('a single completed day today is a streak of one', function () {
    completeDay($this->user, $this->team, '2026-07-15');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(1)
        ->and($streaks->best)->toBe(1);
});

test('consecutive completed days ending today build the current streak', function () {
    foreach (['2026-07-11', '2026-07-12', '2026-07-13', '2026-07-14', '2026-07-15'] as $date) {
        completeDay($this->user, $this->team, $date);
    }

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(5)
        ->and($streaks->best)->toBe(5);
});

test('an incomplete today does not break a streak ending yesterday', function () {
    completeDay($this->user, $this->team, '2026-07-13');
    completeDay($this->user, $this->team, '2026-07-14');
    partialDay($this->user, $this->team, '2026-07-15', 2);

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(2)
        ->and($streaks->best)->toBe(2);
});

test('a gap breaks the streak', function () {
    completeDay($this->user, $this->team, '2026-07-10');
    completeDay($this->user, $this->team, '2026-07-11');
    // 12 July missed entirely.
    completeDay($this->user, $this->team, '2026-07-13');
    completeDay($this->user, $this->team, '2026-07-14');
    completeDay($this->user, $this->team, '2026-07-15');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(3)
        ->and($streaks->best)->toBe(3);
});

test('a partially completed day breaks the streak', function () {
    completeDay($this->user, $this->team, '2026-07-12');
    partialDay($this->user, $this->team, '2026-07-13', 2);
    completeDay($this->user, $this->team, '2026-07-14');
    completeDay($this->user, $this->team, '2026-07-15');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(2)
        ->and($streaks->best)->toBe(2);
});

test('a completed streak entirely in the past does not count as current', function () {
    foreach (['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04'] as $date) {
        completeDay($this->user, $this->team, $date);
    }

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(0)
        ->and($streaks->best)->toBe(4);
});

test('the best streak is the longest run even when a shorter run is current', function () {
    foreach (['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-05', '2026-07-06'] as $date) {
        completeDay($this->user, $this->team, $date);
    }

    completeDay($this->user, $this->team, '2026-07-14');
    completeDay($this->user, $this->team, '2026-07-15');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(2)
        ->and($streaks->best)->toBe(6);
});

test('a streak ending yesterday is still current when today is untouched', function () {
    completeDay($this->user, $this->team, '2026-07-13');
    completeDay($this->user, $this->team, '2026-07-14');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(2)
        ->and($streaks->best)->toBe(2);
});

test('a streak that ended two days ago is not current', function () {
    completeDay($this->user, $this->team, '2026-07-12');
    completeDay($this->user, $this->team, '2026-07-13');

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(0)
        ->and($streaks->best)->toBe(2);
});

test('another user\'s completed days never contribute to a streak', function () {
    $other = User::factory()->create();
    $other->teams()->attach($this->team, ['role' => 'member']);

    foreach (['2026-07-13', '2026-07-14', '2026-07-15'] as $date) {
        completeDay($other, $this->team, $date);
    }

    $streaks = $this->progress->streaks($this->user, $this->team);

    expect($streaks->current)->toBe(0)
        ->and($streaks->best)->toBe(0);
});
