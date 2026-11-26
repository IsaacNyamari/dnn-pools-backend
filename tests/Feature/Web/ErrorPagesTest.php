<?php

use Illuminate\Support\Facades\Route;

/**
 * A web route that fails with the given status, with debug mode off as in production.
 */
function failing_page(int $status)
{
    config(['app.debug' => false]);
    Route::middleware('web')->get('test-error', fn () => abort($status, 'internal detail'));

    return test()->get('test-error');
}

test('every error status has the application\'s own page', function (int $status, string $title) {
    failing_page($status)
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee((string) $status)
        ->assertDontSee('internal detail');
})->with([
    [401, 'Sign in required'],
    [402, 'Payment required'],
    [403, 'Access denied'],
    [404, 'Page not found'],
    [419, 'Page expired'],
    [429, 'Too many requests'],
    [500, 'Something went wrong'],
    [503, 'Down for maintenance'],
    [418, 'Request not accepted'],
    [502, 'Something went wrong'],
]);

test('an unknown address and a wrong method use these pages', function () {
    config(['app.debug' => false]);

    $this->get('/no/such/page')->assertNotFound()->assertSee('Page not found');
    $this->delete('/home')->assertStatus(405)->assertSee('Request not accepted');
});

test('a role refusal shows the access denied page', function () {
    $this->actingAs(attendant(institution()))->get(route('admin.overview'))->assertForbidden()->assertSee('Access denied');
});

test('an unexpected exception shows no internals in production', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('test-crash', fn () => throw new RuntimeException('database password leaked'));

    $this->get('test-crash')
        ->assertStatus(500)
        ->assertSee('Something went wrong')
        ->assertDontSee('database password leaked')
        ->assertDontSee('RuntimeException');
});

test('error pages need no built assets or database', function () {
    $html = view('errors.500')->render();

    expect($html)->toContain('Something went wrong')->not->toContain('/build/')->not->toContain('livewire');
});

test('the api keeps answering errors in json', function () {
    config(['app.debug' => false]);

    $this->get('/api/v1/nothing-here')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    $this->get('/api/v1/auth/login')->assertStatus(405)->assertHeader('Content-Type', 'application/json');
});
