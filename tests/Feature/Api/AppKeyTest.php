<?php

beforeEach(fn () => config(['pool.app_keys' => ['current-key', 'previous-key']]));

test('a request without the app key is refused before authentication', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'invalid_app_key');
});

test('sign-in is refused without the right app key', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'someone@example.com',
        'password' => 'irrelevant',
        'device_name' => 'test',
    ], ['X-App-Key' => 'wrong'])
        ->assertForbidden()
        ->assertJsonPath('code', 'invalid_app_key');
});

test('uploads are refused without the app key', function (string $path) {
    $this->postJson($path, [])
        ->assertForbidden()
        ->assertJsonPath('code', 'invalid_app_key');
})->with(['/api/v1/game-records', '/api/v1/shifts', '/api/v1/auth/logout']);

test('any configured key passes on to authentication', function (string $key) {
    // The app key is not a login: without a token the answer is still 401.
    $this->getJson('/api/v1/auth/me', ['X-App-Key' => $key])->assertUnauthorized();
})->with(['current-key', 'previous-key']);

test('production with no key configured is closed', function () {
    config(['pool.app_keys' => []]);
    app()->detectEnvironment(fn () => 'production');

    $this->getJson('/api/v1/auth/me')
        ->assertStatus(503)
        ->assertJsonPath('code', 'app_key_not_configured');
});

test('a developer machine with no key configured stays open', function () {
    config(['pool.app_keys' => []]);

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('the web dashboards do not need the app key', function () {
    $this->get('/login')->assertOk();
});
