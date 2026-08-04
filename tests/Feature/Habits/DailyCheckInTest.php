<?php

use App\Actions\Habits\SaveDailyCheckIn;
use App\Models\DailyCheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    // Freeze the clock so "today", "future" and week boundaries stay deterministic.
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->habits = $this->team->habits()->activeOrdered()->get()->keyBy('slug');
});

test('guests cannot reach the daily check-in page', function () {
    $this->get(route('habits.check-in', ['current_team' => $this->team->slug]))
        ->assertRedirect(route('login'));
});

test('a check-in is created on first save', function () {
    expect(DailyCheckIn::count())->toBe(0);

    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('completions.'.$this->habits['exercise']->id, true)
        ->set('moodScore', 7)
        ->set('notes', 'Felt strong today.')
        ->call('save')
        ->assertHasNoErrors();

    $checkIn = DailyCheckIn::sole();

    expect($checkIn->user_id)->toBe($this->user->id)
        ->and($checkIn->team_id)->toBe($this->team->id)
        ->and($checkIn->date->toDateString())->toBe('2026-07-15')
        ->and($checkIn->mood_score)->toBe(7)
        ->and($checkIn->notes)->toBe('Felt strong today.');
});

test('habit completions are persisted for every habit', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('completions.'.$this->habits['exercise']->id, true)
        ->set('completions.'.$this->habits['reading']->id, true)
        ->set('completions.'.$this->habits['journaling']->id, false)
        ->call('save')
        ->assertHasNoErrors();

    $checkIn = DailyCheckIn::with('completions')->sole();

    expect($checkIn->completions)->toHaveCount(3)
        ->and($checkIn->completedCount())->toBe(2);

    $completed = $checkIn->completions->where('completed', true)->pluck('habit_id')->all();

    expect($completed)->toEqualCanonicalizing([
        $this->habits['exercise']->id,
        $this->habits['reading']->id,
    ]);
});

test('saving again updates the existing check-in instead of creating another', function () {
    $component = Livewire::actingAs($this->user)->test('habits.check-in-form');

    $component->set('moodScore', 4)->call('save')->assertHasNoErrors();
    $component->set('moodScore', 9)->set('notes', 'Better by evening.')->call('save')->assertHasNoErrors();

    expect(DailyCheckIn::count())->toBe(1);

    $checkIn = DailyCheckIn::sole();

    expect($checkIn->mood_score)->toBe(9)
        ->and($checkIn->notes)->toBe('Better by evening.');
});

test('completion rows are updated rather than duplicated on re-save', function () {
    $component = Livewire::actingAs($this->user)->test('habits.check-in-form');

    $component->set('completions.'.$this->habits['reading']->id, true)->call('save');
    $component->set('completions.'.$this->habits['reading']->id, false)->call('save');

    $checkIn = DailyCheckIn::with('completions')->sole();

    expect($checkIn->completions)->toHaveCount(3)
        ->and($checkIn->completedCount())->toBe(0);
});

test('the database rejects a duplicate check-in for the same user, team and date', function () {
    DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->create();

    expect(fn () => DailyCheckIn::factory()->forUser($this->user, $this->team)->on('2026-07-15')->create())
        ->toThrow(QueryException::class);
});

test('notes are persisted and can be cleared', function () {
    $component = Livewire::actingAs($this->user)->test('habits.check-in-form');

    $component->set('notes', 'A note worth keeping.')->call('save');

    expect(DailyCheckIn::sole()->notes)->toBe('A note worth keeping.');

    $component->set('notes', '')->call('save');

    expect(DailyCheckIn::sole()->notes)->toBeNull();
});

test('the mood score is optional', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('moodScore', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyCheckIn::sole()->mood_score)->toBeNull();
});

test('the mood score must fall between one and ten', function (int $mood) {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('moodScore', $mood)
        ->call('save')
        ->assertHasErrors(['moodScore' => 'between']);

    expect(DailyCheckIn::count())->toBe(0);
})->with([
    'below the range' => [0],
    'negative' => [-3],
    'above the range' => [11],
    'far above the range' => [99],
]);

test('the mood score accepts both range boundaries', function (int $mood) {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('moodScore', $mood)
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyCheckIn::sole()->mood_score)->toBe($mood);
})->with([[1], [10]]);

test('the mood score can be cleared from the form', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('moodScore', 8)
        ->call('save')
        ->call('clearMood')
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyCheckIn::sole()->mood_score)->toBeNull();
});

test('notes longer than the allowed length are rejected', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('notes', str_repeat('a', 2001))
        ->call('save')
        ->assertHasErrors(['notes' => 'max']);

    expect(DailyCheckIn::count())->toBe(0);
});

test('a past date can be edited', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form', ['date' => '2026-07-10'])
        ->set('completions.'.$this->habits['exercise']->id, true)
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyCheckIn::sole()->date->toDateString())->toBe('2026-07-10');
});

test('a future date cannot be saved through the form', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form', ['date' => '2026-07-16'])
        ->set('completions.'.$this->habits['exercise']->id, true)
        ->call('save')
        ->assertForbidden();

    expect(DailyCheckIn::count())->toBe(0);
});

test('no check-in row is written for a date far in the future', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form', ['date' => '2026-08-01'])
        ->call('save')
        ->assertForbidden();

    expect(DailyCheckIn::count())->toBe(0);
});

test('the save action itself refuses a future date', function () {
    app(SaveDailyCheckIn::class)->handle(
        user: $this->user,
        team: $this->team,
        date: CarbonImmutable::parse('2026-07-16'),
        completions: [],
    );
})->throws(ValidationException::class);

test('the form disables its controls for a future date', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form', ['date' => '2026-07-20'])
        ->assertSet('date', '2026-07-20')
        ->assertSee('future', escape: false)
        ->assertSeeHtml('data-test="future-notice"');
});

test('the form loads previously saved values', function () {
    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('completions.'.$this->habits['reading']->id, true)
        ->set('moodScore', 6)
        ->set('notes', 'Kept at it.')
        ->call('save');

    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->assertSet('moodScore', 6)
        ->assertSet('notes', 'Kept at it.')
        ->assertSet('completions.'.$this->habits['reading']->id, true)
        ->assertSet('completions.'.$this->habits['exercise']->id, false);
});

test('the form reports the live percentage as habits are ticked', function () {
    $component = Livewire::actingAs($this->user)->test('habits.check-in-form');

    expect($component->get('pendingPercentage'))->toBe(0);

    $component->set('completions.'.$this->habits['exercise']->id, true);
    expect($component->get('pendingPercentage'))->toBe(33);

    $component->set('completions.'.$this->habits['reading']->id, true);
    expect($component->get('pendingPercentage'))->toBe(67);

    $component->set('completions.'.$this->habits['journaling']->id, true);
    expect($component->get('pendingPercentage'))->toBe(100);
});

test('the check-in page navigates between days', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.check-in', ['date' => '2026-07-15'])
        ->call('previousDay')
        ->assertRedirect(route('habits.check-in', [
            'current_team' => $this->team->slug,
            'date' => '2026-07-14',
        ]));

    Livewire::actingAs($this->user)
        ->test('pages::habits.check-in', ['date' => '2026-07-10'])
        ->call('nextDay')
        ->assertRedirect(route('habits.check-in', [
            'current_team' => $this->team->slug,
            'date' => '2026-07-11',
        ]));
});

test('the check-in page cannot navigate past the end of the tracked period', function () {
    Livewire::actingAs($this->user)
        ->test('pages::habits.check-in', ['date' => '2026-12-31'])
        ->assertSet('canGoForward', false);
});

test('a habit id from another team is ignored when saving', function () {
    $otherUser = User::factory()->create();
    $foreignHabit = $otherUser->currentTeam->habits()->activeOrdered()->first();

    Livewire::actingAs($this->user)
        ->test('habits.check-in-form')
        ->set('completions.'.$foreignHabit->id, true)
        ->call('save')
        ->assertHasNoErrors();

    $checkIn = DailyCheckIn::with('completions')->sole();

    expect($checkIn->completions->pluck('habit_id')->all())
        ->not->toContain($foreignHabit->id)
        ->and($checkIn->completedCount())->toBe(0);
});
