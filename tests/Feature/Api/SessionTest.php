<?php

use App\Actions\Attendants\SetAttendantActive;

test('the api needs a bearer token', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonStructure(['message']);
})->with([
    ['GET', '/api/v1/auth/me'],
    ['POST', '/api/v1/auth/logout'],
    ['POST', '/api/v1/game-records'],
    ['POST', '/api/v1/shifts'],
]);

test('a request without an accept header still gets json, not a redirect', function () {
    $this->get('/api/v1/auth/me')->assertUnauthorized()->assertJsonStructure(['message']);
});

test('a web session does not authenticate the api', function () {
    $this->actingAs(director(institution()))->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('the old placeholder routes are gone', function (string $uri) {
    $this->getJson($uri)->assertNotFound();
})->with(['/api/user', '/api/login', '/api/tokens/create']);

test('logout revokes only the token used', function () {
    $user = attendant(institution());
    [$first, $second] = [token_for($user), token_for($user)];

    with_token($first)->postJson('/api/v1/auth/logout')->assertNoContent();

    with_token($first)->getJson('/api/v1/auth/me')->assertUnauthorized();
    with_token($second)->getJson('/api/v1/auth/me')->assertOk();
});

test('deactivating the only membership revokes the tokens', function () {
    $team = institution();
    $user = attendant($team);
    $token = token_for($user);
    $other = attendant($team);
    $otherToken = token_for($other);

    app(SetAttendantActive::class)->handle(membership($team, $user), false);

    expect($user->tokens()->count())->toBe(0);
    with_token($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    with_token($otherToken)->getJson('/api/v1/auth/me')->assertOk();
});

test('deactivating one of two memberships keeps the tokens but removes that institution', function () {
    [$first, $second] = [institution(), institution()];
    $user = attendant($first);
    attendant($second, $user);
    $token = token_for($user);

    app(SetAttendantActive::class)->handle(membership($first, $user), false);

    with_token($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('institutions.*.id', [$second->id]);
    with_token($token)->postJson('/api/v1/game-records', ['records' => [game_row($first, $user)]])
        ->assertForbidden()->assertJsonPath('code', 'forbidden_institution');
});

test('another membership that grants no access does not keep the tokens', function () {
    [$team, $suspended] = [institution(), institution()];
    $user = attendant($team);
    attendant($suspended, $user);
    $suspended->delete();
    token_for($user);

    app(SetAttendantActive::class)->handle(membership($team, $user), false);

    expect($user->tokens()->count())->toBe(0);
});

test('me answers no_institution when no active membership is left', function () {
    $team = institution();
    $user = director($team);
    $token = token_for($user);

    $team->delete();

    with_token($token)->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('code', 'no_institution');
    with_token($token)->postJson('/api/v1/game-records', ['records' => [game_row($team, $user)]])
        ->assertForbidden()->assertJsonPath('code', 'forbidden_institution');

    // Restoring the institution brings the same token back, so a queued upload can go through.
    $team->restore();

    with_token($token)->getJson('/api/v1/auth/me')->assertOk();
});
