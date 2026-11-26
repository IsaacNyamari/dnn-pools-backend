<?php

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function institution(?string $name = null): Team
{
    return Team::factory()->create($name === null ? [] : ['name' => $name, 'slug' => Str::slug($name)]);
}

/**
 * A user with a membership in the institution.
 */
function join_institution(Team $team, TeamRole $role = TeamRole::Member, ?User $user = null, bool $active = true): User
{
    $user ??= User::factory()->create();
    $team->memberships()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => $active]);

    return $user;
}

function director(Team $team, ?User $user = null): User
{
    return join_institution($team, TeamRole::Owner, $user);
}

function attendant(Team $team, ?User $user = null, bool $active = true): User
{
    return join_institution($team, TeamRole::Member, $user, $active);
}

function membership(Team $team, User $user): Membership
{
    return $team->memberships()->where('user_id', $user->id)->firstOrFail();
}

/**
 * Make the next requests with this bearer token. The guard is reset because one test
 * runs many requests in the same application instance.
 */
function with_token(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function token_for(User $user): string
{
    return $user->createToken('test phone')->plainTextToken;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function game_row(Team $team, User $attendant, array $overrides = []): array
{
    return [
        'institution_id' => $team->id,
        'attendant_id' => $attendant->id,
        'device_id' => '',
        'table_id' => 'T01',
        'location_id' => 'MAIN',
        'code' => 'ABC234',
        'payment_method' => 'Cash',
        'status' => 'used',
        'local_timestamp' => now()->utc()->subMinutes(5)->startOfSecond()->format('Y-m-d\TH:i:s.v\Z'),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shift_row(Team $team, User $attendant, array $overrides = []): array
{
    return [
        'institution_id' => $team->id,
        'attendant_id' => $attendant->id,
        'device_id' => '',
        'location_id' => 'MAIN',
        'start_time' => '2026-11-26T06:00:00.000Z',
        'end_time' => '2026-11-26T14:00:00.000Z',
        'total_cash' => 12,
        'total_mpesa' => 7,
        ...$overrides,
    ];
}
