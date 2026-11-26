<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates a system admin with a prompted password', function () {
    $this->artisan('app:system-admin')
        ->expectsQuestion('Email address', 'Ops@Example.com')
        ->expectsQuestion('Name', 'Operator')
        ->expectsQuestion('Password', 'a-long-password')
        ->expectsQuestion('Confirm password', 'a-long-password')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('ops@example.com')
        ->and($user->is_system_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-password', $user->password))->toBeTrue()
        ->and(Team::count())->toBe(0);
});

test('it promotes an existing account and can keep its password', function () {
    $user = User::factory()->create();
    token_for($user);

    $this->artisan('app:system-admin', ['email' => $user->email])
        ->expectsQuestion('New password (leave empty to keep the current one)', '')
        ->assertSuccessful();

    expect($user->fresh()->is_system_admin)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

test('a new password on promotion signs the account out', function () {
    $user = User::factory()->create();
    token_for($user);

    $this->artisan('app:system-admin', ['email' => $user->email])
        ->expectsQuestion('New password (leave empty to keep the current one)', 'a-new-password')
        ->expectsQuestion('Confirm password', 'a-new-password')
        ->assertSuccessful();

    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('it changes nothing when the input is bad', function (string $password, string $confirmation) {
    $this->artisan('app:system-admin', ['email' => 'ops@example.com'])
        ->expectsQuestion('Name', 'Operator')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'mismatch' => ['a-long-password', 'another-password'],
    'too short' => ['short', 'short'],
    'empty' => ['', ''],
]);

test('it refuses an invalid email', function () {
    $this->artisan('app:system-admin', ['email' => 'nope'])->assertFailed();
});

test('the demo seeder does nothing outside the local environment', function () {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::count())->toBe(0)->and(Team::count())->toBe(0);
});
