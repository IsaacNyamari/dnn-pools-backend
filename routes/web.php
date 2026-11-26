<?php

use App\Http\Middleware\EnsureDirector;
use App\Http\Middleware\EnsureSystemAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'app.home' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Sends each role to its own landing page.
    Route::get('home', fn (Request $request) => redirect($request->user()->homePath()))->name('app.home');

    // For people without a web dashboard, such as attendants. It shows no institution data.
    Route::view('account', 'pages::account')->name('account');

    Route::prefix('admin')->name('admin.')->middleware(EnsureSystemAdmin::class)->group(function () {
        Route::livewire('/', 'pages::admin.overview')->name('overview');
        Route::livewire('institutions', 'pages::admin.institutions')->name('institutions.index');
        Route::livewire('institutions/{institutionId}', 'pages::admin.institution')
            ->whereNumber('institutionId')
            ->name('institutions.show');
    });
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureDirector::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::director.dashboard')->name('dashboard');
        Route::livewire('attendants', 'pages::director.attendants')->name('institution.attendants');
        Route::livewire('games', 'pages::director.games')->name('institution.games');
        Route::livewire('shifts', 'pages::director.shifts')->name('institution.shifts');
    });

require __DIR__.'/settings.php';
