<?php

use App\Actions\Fortify\ResetUserPassword;
use Livewire\Livewire;

test('changing your own password signs the app out', function () {
    $user = attendant(institution());
    token_for($user);

    Livewire::actingAs($user)->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'a-different-password')
        ->set('password_confirmation', 'a-different-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

test('resetting a forgotten password signs the app out', function () {
    $user = attendant(institution());
    token_for($user);

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'a-different-password',
        'password_confirmation' => 'a-different-password',
    ]);

    expect($user->tokens()->count())->toBe(0);
});
