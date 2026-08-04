<?php

use Illuminate\Support\Facades\Lang;

test('the english habits translation file is registered', function () {
    expect(Lang::has('habits.dashboard.title'))->toBeTrue()
        ->and(app()->getLocale())->toBe('en');
});

test('every translation key used by the interface exists', function (string $key) {
    expect(Lang::has($key))->toBeTrue()
        ->and(__($key))->not->toBe($key);
})->with([
    'habits.names.exercise',
    'habits.names.reading',
    'habits.names.journaling',
    'habits.nav.dashboard',
    'habits.nav.calendar',
    'habits.nav.year_overview',
    'habits.nav.group',
    'habits.dashboard.title',
    'habits.dashboard.intro',
    'habits.dashboard.this_week',
    'habits.dashboard.this_month',
    'habits.dashboard.current_streak',
    'habits.dashboard.best_streak',
    'habits.dashboard.average_mood',
    'habits.dashboard.average_mood_hint',
    'habits.dashboard.view_calendar',
    'habits.dashboard.view_year',
    'habits.check_in.title',
    'habits.check_in.today_title',
    'habits.check_in.habits_label',
    'habits.check_in.mood_label',
    'habits.check_in.mood_description',
    'habits.check_in.mood_clear',
    'habits.check_in.mood_none',
    'habits.check_in.notes_label',
    'habits.check_in.notes_placeholder',
    'habits.check_in.save',
    'habits.check_in.saved',
    'habits.check_in.previous_day',
    'habits.check_in.next_day',
    'habits.check_in.go_to_today',
    'habits.check_in.future_notice',
    'habits.check_in.progress',
    'habits.week.title',
    'habits.week.subtitle',
    'habits.calendar.title',
    'habits.calendar.subtitle',
    'habits.calendar.previous_month',
    'habits.calendar.next_month',
    'habits.year.title',
    'habits.year.overall_completion',
    'habits.year.completed_days',
    'habits.year.current_streak',
    'habits.year.best_streak',
    'habits.year.legend',
    'habits.levels.0',
    'habits.levels.1',
    'habits.levels.2',
    'habits.levels.3',
    'habits.streak.rule',
    'habits.validation.future_date',
    'habits.validation.mood_range',
    'habits.validation.notes_max',
    'habits.empty.no_habits',
]);

test('placeholder driven strings interpolate their values', function () {
    expect(__('habits.check_in.progress_value', ['percentage' => 67]))->toContain('67')
        ->and(__('habits.dashboard.completed_days', ['completed' => 4, 'total' => 9]))
        ->toContain('4')
        ->toContain('9')
        ->and(__('habits.validation.mood_range', ['min' => 1, 'max' => 10]))
        ->toContain('1')
        ->toContain('10')
        ->and(__('habits.year.subtitle', ['end' => '31 December 2026']))
        ->toContain('31 December 2026');
});

test('streak counts are pluralised', function () {
    expect(trans_choice('habits.streak.days', 0, ['count' => 0]))->not->toContain(':count')
        ->and(trans_choice('habits.streak.days', 1, ['count' => 1]))->toContain('1')
        ->and(trans_choice('habits.streak.days', 5, ['count' => 5]))->toContain('5');
});

test('no habit tracker view hardcodes the default habit names', function () {
    $views = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file): bool => str_contains($file->getPathname(), 'habits')
            || $file->getFilename() === 'dashboard.blade.php');

    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        $contents = File::get($view->getPathname());

        // The English names must only ever come from the translation file.
        foreach (['>Exercise<', '>Reading<', '>Journaling<'] as $literal) {
            expect($contents)->not->toContain($literal, "{$view->getFilename()} hardcodes {$literal}");
        }
    }
});
