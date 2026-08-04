<?php

use App\Actions\Habits\GetHabitProgress;
use App\Actions\Teams\CreateTeam;
use App\Models\DailyCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->progress = app(GetHabitProgress::class);
});

test('a user never sees another user\'s check-ins in the same team', function () {
    $teammate = User::factory()->create();
    $teammate->teams()->attach($this->team, ['role' => 'member']);

    DailyCheckIn::factory()->forUser($teammate, $this->team)->on('2026-07-15')->completed()->create();

    $day = $this->progress->forDate($this->user, $this->team, CarbonImmutable::parse('2026-07-15'));

    expect($day->hasCheckIn)->toBeFalse()
        ->and($day->percentage)->toBe(0);
});

test('a user never sees their own check-ins from a different team', function () {
    $otherTeam = app(CreateTeam::class)->handle($this->user, 'Side Project');

    DailyCheckIn::factory()->forUser($this->user, $otherTeam)->on('2026-07-15')->completed()->create();

    $day = $this->progress->forDate($this->user, $this->team, CarbonImmutable::parse('2026-07-15'));

    expect($day->hasCheckIn)->toBeFalse()
        ->and($day->percentage)->toBe(0);

    $otherDay = $this->progress->forDate($this->user, $otherTeam, CarbonImmutable::parse('2026-07-15'));

    expect($otherDay->percentage)->toBe(100);
});

test('saving a check-in writes to the current team only', function () {
    $otherTeam = app(CreateTeam::class)->handle($this->user, 'Side Project');

    $this->user->switchTeam($this->team);

    Livewire::actingAs($this->user->fresh())
        ->test('habits.check-in-form')
        ->set('moodScore', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyCheckIn::where('team_id', $this->team->id)->count())->toBe(1)
        ->and(DailyCheckIn::where('team_id', $otherTeam->id)->count())->toBe(0);
});

test('the check-in policy refuses another user\'s check-in', function () {
    $teammate = User::factory()->create();
    $teammate->teams()->attach($this->team, ['role' => 'member']);

    $checkIn = DailyCheckIn::factory()->forUser($teammate, $this->team)->on('2026-07-15')->create();

    expect(Gate::forUser($this->user)->allows('view', $checkIn))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('update', $checkIn))->toBeFalse()
        ->and(Gate::forUser($teammate)->allows('update', $checkIn))->toBeTrue();
});

test('the check-in policy refuses a user who left the team', function () {
    $checkIn = DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->create();

    expect(Gate::forUser($this->user)->allows('update', $checkIn))->toBeTrue();

    $this->team->memberships()->where('user_id', $this->user->id)->delete();

    expect(Gate::forUser($this->user->fresh())->allows('update', $checkIn))->toBeFalse();
});

test('the check-in policy refuses a future date', function () {
    $checkIn = DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-20')->create();

    expect(Gate::forUser($this->user)->allows('update', $checkIn))->toBeFalse();
});

test('the habit policy refuses a habit from a team the user does not belong to', function () {
    $stranger = User::factory()->create();
    $foreignHabit = $stranger->currentTeam->habits()->first();

    expect(Gate::forUser($this->user)->allows('view', $foreignHabit))->toBeFalse()
        ->and(Gate::forUser($stranger)->allows('view', $foreignHabit))->toBeTrue();
});

test('a user cannot reach habit pages for a team they do not belong to', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->user)
        ->get(route('habits.calendar', ['current_team' => $stranger->currentTeam->slug]))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('habits.year', ['current_team' => $stranger->currentTeam->slug]))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('habits.check-in', [
            'current_team' => $stranger->currentTeam->slug,
            'date' => '2026-07-15',
        ]))
        ->assertForbidden();
});

test('streaks are scoped per team', function () {
    $otherTeam = app(CreateTeam::class)->handle($this->user, 'Side Project');

    foreach (['2026-07-13', '2026-07-14', '2026-07-15'] as $date) {
        DailyCheckIn::factory()->forUser($this->user, $otherTeam)->on($date)->completed()->create();
    }

    expect(app(GetHabitProgress::class)->streaks($this->user, $this->team)->current)->toBe(0)
        ->and(app(GetHabitProgress::class)->streaks($this->user, $otherTeam)->current)->toBe(3);
});
