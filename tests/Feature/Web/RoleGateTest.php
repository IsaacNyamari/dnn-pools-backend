<?php

use App\Enums\TeamRole;
use App\Http\Middleware\EnsureDirector;
use App\Http\Middleware\EnsureSystemAdmin;
use App\Models\GameRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

const DIRECTOR_ROUTES = ['dashboard', 'institution.attendants', 'institution.games', 'institution.shifts'];
const DIRECTOR_PAGES = ['pages::director.dashboard', 'pages::director.attendants', 'pages::director.games', 'pages::director.shifts'];
const ADMIN_PAGES = ['pages::admin.overview', 'pages::admin.institutions'];

test('guests are sent to sign in', function () {
    $team = institution();

    foreach (DIRECTOR_ROUTES as $route) {
        $this->get(route($route, ['current_team' => $team->slug]))->assertRedirect(route('login'));
    }

    $this->get(route('admin.overview'))->assertRedirect(route('login'));
    $this->get(route('admin.institutions.index'))->assertRedirect(route('login'));
    $this->get(route('admin.institutions.show', $team->id))->assertRedirect(route('login'));
    $this->get(route('account'))->assertRedirect(route('login'));
});

test('owners and admins reach every dashboard page', function (string $factory) {
    $team = institution();
    $user = $factory === 'owner' ? director($team) : join_institution($team, TeamRole::Admin);

    foreach (DIRECTOR_ROUTES as $route) {
        $this->actingAs($user)->get(route($route, ['current_team' => $team->slug]))->assertOk();
    }
})->with(['owner', 'admin']);

test('nobody else reaches an institution\'s dashboard', function (Closure $makeUser) {
    $team = institution();
    $user = $makeUser($team);

    foreach (DIRECTOR_ROUTES as $route) {
        $this->actingAs($user)->get(route($route, ['current_team' => $team->slug]))->assertForbidden();
    }

    foreach (DIRECTOR_PAGES as $page) {
        Livewire::actingAs($user)->test($page, ['current_team' => $team->slug])->assertForbidden();
    }
})->with([
    'its attendant' => [fn (Team $team) => attendant($team)],
    'another director' => [fn () => director(institution())],
    'its deactivated director' => [fn (Team $team) => join_institution($team, TeamRole::Owner, active: false)],
    'an attendant who directs elsewhere' => [fn (Team $team) => attendant($team, director(institution()))],
    'a system admin without a membership' => [fn () => User::factory()->systemAdmin()->create()],
]);

test('personal teams and suspended institutions have no dashboard', function (Team $team) {
    $user = director($team);

    $this->actingAs($user)->get(route('dashboard', ['current_team' => $team->slug]))->assertForbidden();
    Livewire::actingAs($user)->test('pages::director.dashboard', ['current_team' => $team->slug])->assertForbidden();
})->with([
    'personal' => [fn () => Team::factory()->personal()->create()],
    'suspended' => [fn () => Team::factory()->trashed()->create()],
]);

test('a director loses an open page as soon as access ends', function (Closure $revoke) {
    $team = institution();
    $user = director($team);
    $page = Livewire::actingAs($user)->test('pages::director.attendants', ['current_team' => $team->slug])->assertOk();

    $revoke($team, $user);

    $page->call('startCreate')->assertForbidden();
})->with([
    'deactivated' => [fn (Team $team, User $user) => membership($team, $user)->update(['is_active' => false])],
    'demoted' => [fn (Team $team, User $user) => membership($team, $user)->update(['role' => TeamRole::Member])],
    'suspended' => [fn (Team $team) => $team->delete()],
]);

test('the institution of an open page cannot be swapped from the browser', function () {
    $team = institution();
    $other = institution();

    Livewire::actingAs(director($team))
        ->test('pages::director.games', ['current_team' => $team->slug])
        ->set('institutionId', $other->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('role checks also apply to livewire update requests', function () {
    expect(Livewire::getPersistentMiddleware())
        ->toContain(EnsureDirector::class)
        ->toContain(EnsureSystemAdmin::class);
});

test('only system admins reach the admin pages', function (Closure $makeUser) {
    $team = institution();
    $user = $makeUser($team);

    $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.institutions.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.institutions.show', $team->id))->assertForbidden();

    foreach (ADMIN_PAGES as $page) {
        Livewire::actingAs($user)->test($page)->assertForbidden();
    }

    Livewire::actingAs($user)->test('pages::admin.institution', ['institutionId' => $team->id])->assertForbidden();
})->with([
    'director' => [fn (Team $team) => director($team)],
    'attendant' => [fn (Team $team) => attendant($team)],
    'no membership' => [fn () => User::factory()->create()],
]);

test('is_system_admin cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->fill(['is_system_admin' => true])->save();

    expect($user->fresh()->is_system_admin)->toBeFalse();
});

test('each role lands on its own page after signing in', function (Closure $makeUser) {
    [$user, $path] = $makeUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
    $this->get(route('app.home'))->assertRedirect($path);
})->with([
    'system admin' => [fn () => [User::factory()->systemAdmin()->create(), '/admin']],
    'director' => [fn () => [director(institution('Alpha Pool')), '/alpha-pool/dashboard']],
    'attendant' => [fn () => [attendant(institution()), '/account']],
]);

test('attendants see no dashboard data on the web', function () {
    $team = institution('Secret Numbers Bar');
    $user = attendant($team);
    GameRecord::factory()->create(['institution_id' => $team->id, 'attendant_id' => $user->id, 'code' => 'QQQ999']);

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('mobile app')
        ->assertDontSee('QQQ999')
        ->assertDontSee('Secret Numbers Bar')
        ->assertDontSee(route('admin.overview'));

    foreach (['profile.edit', 'appearance.edit'] as $route) {
        $this->get(route($route))->assertOk()->assertDontSee('Secret Numbers Bar');
    }
});

test('there is no way to create a team or manage one through the starter kit screens', function () {
    $this->actingAs(director(institution()));

    $this->get('/settings/teams')->assertNotFound();

    expect(collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn (string $name) => str_starts_with($name, 'teams.')))->toBeEmpty()
        ->and(Gate::allows('create', Team::class))->toBeFalse()
        ->and(view()->exists('pages::teams.index'))->toBeFalse()
        ->and(view()->exists('components.⚡create-team-modal'))->toBeFalse();
});

test('the team switcher only switches between directed institutions', function () {
    [$first, $second, $foreign, $attending] = [institution(), institution(), institution(), institution()];
    $user = director($first);
    director($second, $user);
    attendant($attending, $user);

    foreach ([$foreign, $attending] as $team) {
        Livewire::actingAs($user)->test('team-switcher')->call('switchTeam', $team->slug)->assertForbidden();
    }

    Livewire::actingAs($user)->test('team-switcher')
        ->assertDontSee($attending->name)
        ->call('switchTeam', $second->slug)
        ->assertRedirect(route('dashboard', ['current_team' => $second->slug]));

    expect($user->fresh()->current_team_id)->toBe($second->id);
});

test('users cannot delete their own account, which game records depend on', function () {
    $this->actingAs(attendant(institution()))->get(route('profile.edit'))->assertOk()->assertDontSee('Delete account');

    expect(view()->exists('pages::settings.delete-user-modal'))->toBeFalse();
});
