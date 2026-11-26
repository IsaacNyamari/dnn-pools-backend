<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = institution();
    $this->director = director($this->team);
});

function attendants_page()
{
    return Livewire::actingAs(test()->director)
        ->test('pages::director.attendants', ['current_team' => test()->team->slug]);
}

test('a director adds an attendant who can then use the app', function () {
    attendants_page()
        ->set('name', 'Jane')
        ->set('email', ' Jane@Example.com ')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('jane@example.com');

    $user = User::where('email', 'jane@example.com')->sole();
    expect(membership($this->team, $user)->role)->toBe(TeamRole::Member)
        ->and(membership($this->team, $user)->is_active)->toBeTrue()
        ->and($user->is_system_admin)->toBeFalse()
        ->and($user->teams()->count())->toBe(1);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'secret-pass-1', 'device_name' => 'p'])
        ->assertOk()->assertJsonPath('institutions.0.role', 'attendant');
});

test('an existing account cannot be pulled into an institution', function () {
    $foreign = attendant(institution());

    attendants_page()
        ->set('name', 'Taken over')
        ->set('email', $foreign->email)
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'new-password-1')
        ->call('create')
        ->assertHasErrors(['email']);

    expect($foreign->fresh()->name)->not->toBe('Taken over')
        ->and(Hash::check('password', $foreign->fresh()->password))->toBeTrue()
        ->and($foreign->teams()->count())->toBe(1);
});

test('a director edits an attendant', function () {
    $user = attendant($this->team);

    attendants_page()
        ->call('startEdit', membership($this->team, $user)->id)
        ->assertSet('email', $user->email)
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('update')
        ->assertHasNoErrors();

    expect($user->fresh()->only(['name', 'email']))->toBe(['name' => 'New Name', 'email' => 'new@example.com']);
});

test('setting a password signs the attendant out everywhere', function () {
    config(['session.driver' => 'database']);
    $user = attendant($this->team);
    $token = token_for($user);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $rememberToken = $user->remember_token;

    attendants_page()
        ->call('startPassword', membership($this->team, $user)->id)
        ->set('password', 'brand-new-pass')
        ->set('password_confirmation', 'brand-new-pass')
        ->call('setPassword')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe($rememberToken);

    with_token($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('a director deactivates and reactivates an attendant without deleting anything', function () {
    $user = attendant($this->team);
    $token = token_for($user);
    $id = membership($this->team, $user)->id;

    attendants_page()->call('setActive', $id, false)->assertSee('Deactivated');

    expect(membership($this->team, $user)->is_active)->toBeFalse();
    with_token($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

    attendants_page()->call('setActive', $id, true);

    expect(membership($this->team, $user)->is_active)->toBeTrue()
        ->and(membership($this->team, $user)->id)->toBe($id);
});

test('another institution\'s membership ids are out of reach', function (string $action, array $arguments) {
    $foreignTeam = institution();
    $foreign = attendant($foreignTeam);

    attendants_page()->call($action, membership($foreignTeam, $foreign)->id, ...$arguments)->assertNotFound();

    expect(membership($foreignTeam, $foreign)->is_active)->toBeTrue();
})->with([
    ['startEdit', []],
    ['startPassword', []],
    ['setActive', [false]],
]);

test('directors cannot be managed from the attendants page', function (string $action, array $arguments) {
    $colleague = director($this->team);

    attendants_page()->call($action, membership($this->team, $colleague)->id, ...$arguments)->assertNotFound();

    expect(membership($this->team, $colleague)->is_active)->toBeTrue();
})->with([
    ['startEdit', []],
    ['startPassword', []],
    ['setActive', [false]],
]);

test('a shared account can be deactivated here but not taken over', function (Closure $share) {
    $user = attendant($this->team);
    $share($user);
    $id = membership($this->team, $user)->id;

    attendants_page()->assertSee('Shared account')->call('startPassword', $id)->assertForbidden();
    attendants_page()->call('startEdit', $id)->assertForbidden();

    attendants_page()->call('setActive', $id, false)->assertOk();
    expect(membership($this->team, $user)->is_active)->toBeFalse();
})->with([
    'directs another institution' => [fn (User $user) => director(institution(), $user)],
    'attends a suspended institution' => [fn (User $user) => tap(institution(), fn (Team $team) => attendant($team, $user))->delete()],
    'deactivated elsewhere' => [fn (User $user) => attendant(institution(), $user, active: false)],
    'system admin' => [fn (User $user) => $user->forceFill(['is_system_admin' => true])->save()],
]);

test('an account that became shared after the form opened is still protected', function () {
    $user = attendant($this->team);
    $page = attendants_page()->call('startPassword', membership($this->team, $user)->id);

    director(institution(), $user);

    $page->set('password', 'brand-new-pass')->set('password_confirmation', 'brand-new-pass')->call('setPassword')->assertForbidden();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('an old personal team does not make an account shared', function () {
    $user = attendant($this->team);
    director(Team::factory()->personal()->create(), $user);

    attendants_page()->call('startPassword', membership($this->team, $user)->id)->assertOk();
});
