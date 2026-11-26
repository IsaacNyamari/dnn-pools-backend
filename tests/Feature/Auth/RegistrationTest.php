<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::count())->toBe(0)
        ->and(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('the sign-in page does not offer registration', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('Sign up');
});
