<?php

use App\Data\DailyProgress;
use Carbon\CarbonImmutable;

test('percentage is calculated from the number of completed habits', function (int $completed, int $total, int $expected) {
    expect(DailyProgress::percentageFor($completed, $total))->toBe($expected);
})->with([
    'none of three' => [0, 3, 0],
    'one of three' => [1, 3, 33],
    'two of three' => [2, 3, 67],
    'all three' => [3, 3, 100],
    'no habits at all' => [0, 0, 0],
]);

test('a day is only complete when every habit is completed', function (int $completed, bool $expected) {
    $progress = new DailyProgress(
        date: CarbonImmutable::parse('2026-07-15'),
        completedCount: $completed,
        totalCount: 3,
        percentage: DailyProgress::percentageFor($completed, 3),
    );

    expect($progress->isComplete())->toBe($expected);
})->with([
    [0, false],
    [1, false],
    [2, false],
    [3, true],
]);

test('a day with no habits never counts as complete', function () {
    $progress = new DailyProgress(
        date: CarbonImmutable::parse('2026-07-15'),
        completedCount: 0,
        totalCount: 0,
        percentage: 0,
    );

    expect($progress->isComplete())->toBeFalse();
});

test('an untouched date reports zero progress without a database row', function () {
    $progress = DailyProgress::empty(
        CarbonImmutable::parse('2026-07-15'),
        3,
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($progress->hasCheckIn)->toBeFalse()
        ->and($progress->percentage)->toBe(0)
        ->and($progress->completedCount)->toBe(0)
        ->and($progress->moodScore)->toBeNull()
        ->and($progress->isFuture)->toBeFalse();
});

test('dates after today are flagged as future', function () {
    $progress = DailyProgress::empty(
        CarbonImmutable::parse('2026-07-25'),
        3,
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($progress->isFuture)->toBeTrue();
});
