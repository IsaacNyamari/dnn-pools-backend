<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

function login(User $user, array $overrides = [])
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pool phone',
        ...$overrides,
    ]);
}

/**
 * Turn on two-factor authentication with a real secret, so TOTP codes can be generated.
 *
 * @return array{secret: string, recovery: array<int, string>}
 */
function enable_two_factor(User $user): array
{
    $secret = app(Google2FA::class)->generateSecretKey();
    $recovery = ['aaaaaaaaaa-bbbbbbbbbb', 'cccccccccc-dddddddddd'];

    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recovery)),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return ['secret' => $secret, 'recovery' => $recovery];
}

test('an attendant signs in and gets a working token and their institutions', function () {
    $team = institution('DNN Sports Bar');
    $user = attendant($team);

    $response = login($user)->assertOk()->assertExactJsonStructure([
        'token', 'user' => ['id', 'name', 'email'], 'institutions' => [['id', 'name', 'slug', 'role']],
    ]);

    expect($response->json('user'))->toBe(['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
        ->and($response->json('institutions'))->toBe([
            ['id' => $team->id, 'name' => 'DNN Sports Bar', 'slug' => 'dnn-sports-bar', 'role' => 'attendant'],
        ])
        ->and($user->tokens()->sole()->name)->toBe('Pool phone')
        ->and($user->tokens()->sole()->abilities)->toBe(['*']);

    with_token($response->json('token'))->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertExactJson(['user' => $response->json('user'), 'institutions' => $response->json('institutions')]);
});

test('owners and admins are directors in the app', function (TeamRole $role) {
    $user = join_institution(institution(), $role);

    login($user)->assertOk()->assertJsonPath('institutions.0.role', 'director');
})->with([TeamRole::Owner, TeamRole::Admin]);

test('the email is not case sensitive', function () {
    $user = attendant(institution(), User::factory()->create(['email' => 'jane@example.com']));

    login($user, ['email' => 'Jane@Example.com'])->assertOk();
});

test('only active memberships of real, unsuspended institutions are listed', function () {
    $user = User::factory()->create();
    $active = institution('Active');
    attendant($active, $user);
    attendant(institution('Deactivated here'), $user, active: false);
    $suspended = institution('Suspended');
    attendant($suspended, $user);
    $suspended->delete();
    director(Team::factory()->personal()->create(), $user);

    $response = login($user)->assertOk();

    expect($response->json('institutions.*.id'))->toBe([$active->id]);
});

test('a wrong password and an unknown email look the same', function () {
    $user = attendant(institution());
    $expected = ['message' => 'The email or password is incorrect.', 'code' => 'invalid_credentials'];

    login($user, ['password' => 'wrong'])->assertStatus(422)->assertExactJson($expected);
    login($user, ['email' => 'nobody@example.com'])->assertStatus(422)->assertExactJson($expected);
});

test('missing fields give the normal validation response', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('a user without app access gets no_institution and no token', function (Closure $makeUser) {
    $user = $makeUser();

    login($user)->assertForbidden()->assertJsonPath('code', 'no_institution');

    expect($user->tokens()->count())->toBe(0);
})->with([
    'no membership' => [fn () => User::factory()->create()],
    'bare system admin' => [fn () => User::factory()->systemAdmin()->create()],
    'deactivated attendant' => [fn () => attendant(institution(), active: false)],
    'only a personal team' => [fn () => director(Team::factory()->personal()->create())],
]);

test('a suspended institution gives no app access', function () {
    $team = institution();
    $user = director($team);
    $team->delete();

    login($user)->assertForbidden()->assertJsonPath('code', 'no_institution');
});

test('sign-in attempts are limited to five a minute per email and address', function () {
    $user = attendant(institution());

    foreach (range(1, 5) as $attempt) {
        login($user, ['password' => 'wrong'])->assertStatus(422);
    }

    login($user)->assertStatus(429)->assertJsonPath('code', 'too_many_attempts')->assertHeader('Retry-After');
    // The case of the email does not open a new bucket, but another account has its own.
    login($user, ['email' => strtoupper($user->email)])->assertStatus(429);
    login(attendant(institution()))->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);
    login($user)->assertOk();

    $this->travel(61)->seconds();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    login($user)->assertOk();
});

test('two-factor users must send a code', function () {
    $user = attendant(institution());
    enable_two_factor($user);

    login($user)->assertStatus(422)->assertJsonPath('code', 'two_factor_required');
    login($user, ['two_factor_code' => ''])->assertStatus(422)->assertJsonPath('code', 'two_factor_required');

    expect($user->tokens()->count())->toBe(0);
});

test('the second factor is not revealed without the right password', function () {
    $user = attendant(institution());
    enable_two_factor($user);

    login($user, ['password' => 'wrong'])->assertJsonPath('code', 'invalid_credentials');
});

test('a current authenticator code works once', function () {
    $user = attendant(institution());
    $code = app(Google2FA::class)->getCurrentOtp(enable_two_factor($user)['secret']);

    login($user, ['two_factor_code' => $code])->assertOk();
    login($user, ['two_factor_code' => $code])->assertStatus(422)->assertJsonPath('code', 'two_factor_invalid');
});

test('a wrong two-factor code is refused', function (string $code) {
    $user = attendant(institution());
    enable_two_factor($user);

    login($user, ['two_factor_code' => $code])->assertStatus(422)->assertJsonPath('code', 'two_factor_invalid');

    expect($user->tokens()->count())->toBe(0);
})->with(['not-a-code', 'zzzzzzzzzz-zzzzzzzzzz', '12345']);

test('a recovery code works once', function () {
    $user = attendant(institution());
    $recovery = enable_two_factor($user)['recovery'];

    login($user, ['two_factor_code' => $recovery[0]])->assertOk();

    expect($user->fresh()->recoveryCodes())->not->toContain($recovery[0])->toContain($recovery[1]);

    login($user, ['two_factor_code' => $recovery[0]])->assertStatus(422)->assertJsonPath('code', 'two_factor_invalid');
});

test('a recovery code is not spent when sign-in is refused for lack of an institution', function () {
    $user = User::factory()->create();
    $recovery = enable_two_factor($user)['recovery'];

    login($user, ['two_factor_code' => $recovery[0]])->assertForbidden();

    expect($user->fresh()->recoveryCodes())->toContain($recovery[0]);
});

test('two-factor that was never confirmed is not asked for', function () {
    $user = attendant(institution());
    enable_two_factor($user);
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    login($user)->assertOk();
});
