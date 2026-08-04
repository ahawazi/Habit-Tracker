<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('the appearance settings page offers light, dark and system options', function () {
    $this->actingAs($this->user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSeeHtml('data-test="appearance-switcher"')
        ->assertSeeHtml('data-test="appearance-light"')
        ->assertSeeHtml('data-test="appearance-dark"')
        ->assertSeeHtml('data-test="appearance-system"')
        ->assertSee(__('Light'))
        ->assertSee(__('Dark'))
        ->assertSee(__('System'));
});

test('the appearance switcher is reachable from the user menu on every authenticated page', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeHtml('data-test="appearance-switcher"')
        ->assertSeeHtml('data-test="appearance-light"')
        ->assertSeeHtml('data-test="appearance-dark"')
        ->assertSeeHtml('data-test="appearance-system"');
});

test('no page hardcodes a dark class on the html element', function (string $route, array $parameters = []) {
    $html = $this->actingAs($this->user)
        ->get(route($route, [...$parameters, 'current_team' => $this->team->slug]))
        ->assertOk()
        ->getContent();

    // The appearance must be applied client-side (flash-prevention script +
    // Flux's $flux.appearance), never baked into the server-rendered markup —
    // otherwise light mode and system mode can never actually take effect.
    expect($html)->toMatch('/<html lang="[^"]*">/')
        ->and($html)->not->toContain('<html lang="'.app()->getLocale().'" class="dark">');
})->with([
    'dashboard' => ['dashboard'],
    'calendar' => ['habits.calendar'],
    'year overview' => ['habits.year'],
]);

test('the document head applies the appearance before render to avoid a flash', function () {
    $html = $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    $headStart = strpos($html, '<head');
    $headEnd = strpos($html, '</head>');
    $appearanceScript = strpos($html, 'appearance');

    expect($headStart)->not->toBeFalse()
        ->and($headEnd)->not->toBeFalse()
        ->and($appearanceScript)->not->toBeFalse()
        ->and($appearanceScript)->toBeGreaterThan($headStart)
        ->and($appearanceScript)->toBeLessThan($headEnd);
});
