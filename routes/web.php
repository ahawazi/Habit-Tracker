<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('habits/check-in/{date?}', 'pages::habits.check-in')
            ->name('habits.check-in')
            ->where('date', '\d{4}-\d{2}-\d{2}');

        Route::livewire('habits/calendar/{month?}', 'pages::habits.calendar')
            ->name('habits.calendar')
            ->where('month', '\d{4}-\d{2}');

        Route::livewire('habits/year', 'pages::habits.year')->name('habits.year');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
