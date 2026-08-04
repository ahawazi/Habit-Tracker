<?php

use App\Actions\Habits\EnsureDefaultHabits;
use App\Enums\DefaultHabit;
use App\Models\Habit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

test('creating a team creates the three default habits', function () {
    $team = Team::factory()->create();

    expect($team->habits()->pluck('slug')->sort()->values()->all())
        ->toBe(['exercise', 'journaling', 'reading']);
});

test('default habits are created for a user\'s personal team', function () {
    $user = User::factory()->create();

    expect($user->currentTeam->habits()->count())->toBe(3);
});

test('default habits are stored in a stable display order', function () {
    $team = Team::factory()->create();

    expect($team->habits()->activeOrdered()->pluck('slug')->all())
        ->toBe(['exercise', 'reading', 'journaling']);
});

test('ensuring default habits is idempotent', function () {
    $team = Team::factory()->create();
    $action = app(EnsureDefaultHabits::class);

    $action->handle($team);
    $action->handle($team);
    $action->handle($team);

    expect(Habit::where('team_id', $team->id)->count())->toBe(3);
});

test('ensuring default habits backfills a team that has none', function () {
    $team = Team::factory()->create();

    Habit::where('team_id', $team->id)->delete();

    expect(Habit::where('team_id', $team->id)->count())->toBe(0);

    $habits = app(EnsureDefaultHabits::class)->handle($team);

    expect($habits)->toHaveCount(3);
});

test('ensuring default habits leaves a customised habit untouched', function () {
    $team = Team::factory()->create();

    $team->habits()->where('slug', 'reading')->update(['name' => 'Deep Reading', 'sort_order' => 9]);

    app(EnsureDefaultHabits::class)->handle($team);

    $reading = $team->habits()->where('slug', 'reading')->sole();

    expect($reading->name)->toBe('Deep Reading')
        ->and($reading->sort_order)->toBe(9)
        ->and(Habit::where('team_id', $team->id)->count())->toBe(3);
});

test('habit slugs are unique per team but shared across teams', function () {
    $first = Team::factory()->create();
    $second = Team::factory()->create();

    expect($first->habits()->where('slug', 'exercise')->exists())->toBeTrue()
        ->and($second->habits()->where('slug', 'exercise')->exists())->toBeTrue();

    expect(fn () => $first->habits()->create([
        'name' => 'Exercise',
        'slug' => 'exercise',
    ]))->toThrow(QueryException::class);
});

test('the sync defaults command ensures habits for every existing team', function () {
    $teams = Team::factory()->count(3)->create();

    Habit::query()->delete();

    $this->artisan('habits:sync-defaults')->assertSuccessful();

    $teams->each(fn (Team $team) => expect($team->habits()->count())->toBe(3));
});

test('the sync defaults command is safe to run repeatedly', function () {
    Team::factory()->count(2)->create();

    $this->artisan('habits:sync-defaults')->assertSuccessful();
    $this->artisan('habits:sync-defaults')->assertSuccessful();

    expect(Habit::count())->toBe(6);
});

test('habits render their translated name and fall back to the stored name', function () {
    $team = Team::factory()->create();

    $exercise = $team->habits()->where('slug', DefaultHabit::Exercise->value)->sole();

    expect($exercise->displayName())->toBe(__('habits.names.exercise'));

    $custom = Habit::factory()->for($team)->create(['name' => 'Meditation', 'slug' => 'meditation']);

    expect($custom->displayName())->toBe('Meditation');
});
