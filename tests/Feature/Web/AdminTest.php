<?php

use App\Enums\GameStatus;
use App\Enums\TeamRole;
use App\Models\GameRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\Reporting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00:00', 'UTC'));
    $this->admin = User::factory()->systemAdmin()->create();
});

function admin_page(string $page, array $parameters = [])
{
    return Livewire::actingAs(test()->admin)->test("pages::admin.{$page}", $parameters);
}

test('the overview covers every institution', function () {
    [$alpha, $beta] = [institution('Alpha Pool'), institution('Beta Pool')];
    director($alpha);
    director($beta);
    $jane = attendant($alpha);
    attendant($beta, active: false);

    $make = fn (Team $team, array $attributes) => GameRecord::factory()->create(['institution_id' => $team->id, 'attendant_id' => $jane->id, ...$attributes]);
    $make($alpha, ['payment_method' => 'Cash', 'local_timestamp' => now()->subMinutes(10), 'created_at' => now(), 'code' => 'AAA222']);
    $make($beta, ['payment_method' => 'M-Pesa', 'local_timestamp' => now()->subDays(3), 'created_at' => now()]);
    $make($beta, ['status' => GameStatus::Failed, 'local_timestamp' => now()->subMinutes(30), 'created_at' => now()]);
    $make($beta, ['status' => GameStatus::Expired, 'local_timestamp' => now()->subDays(40), 'created_at' => now()->subDays(40)]);

    $page = admin_page('overview')->assertSee('Alpha Pool')->assertSee('Beta Pool')->assertSee('AAA222');
    $overview = $page->instance();

    expect($overview->directors())->toBe(2)
        ->and($overview->attendants())->toBe(1)
        ->and($overview->today())->toMatchArray(['games' => 1, 'cash' => 1, 'mpesa' => 0, 'failed' => 1])
        ->and($overview->allTime())->toMatchArray(['games' => 2, 'cash' => 1, 'mpesa' => 1, 'failed' => 1, 'expired' => 1])
        ->and($overview->recentProblems())->toHaveCount(2)
        // The 40 day old upload is outside the 7 day window: delays are 10 min, 30 min, 3 days.
        ->and($overview->syncDelay())->toBe(['count' => 3, 'median' => 1800, 'p95' => 259200, 'max' => 259200]);

    $this->actingAs($this->admin)->get(route('admin.overview'))->assertOk()->assertSee('Platform overview');
});

test('the overview works with no data', function () {
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertOk()->assertSee('No institutions yet.');

    expect(Reporting::syncDelay(GameRecord::query()))->toBe(['count' => 0, 'median' => null, 'p95' => null, 'max' => null]);
});

test('the admin creates an institution with a new director', function () {
    admin_page('institutions')
        ->set('institutionName', 'DNN Sports Bar')
        ->set('name', 'Dina Director')
        ->set('email', 'Dina@Example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('create')
        ->assertHasNoErrors();

    $team = Team::where('slug', 'dnn-sports-bar')->sole();
    $director = User::where('email', 'dina@example.com')->sole();
    expect($team->is_personal)->toBeFalse()
        ->and(membership($team, $director)->role)->toBe(TeamRole::Owner)
        ->and($director->directs($team))->toBeTrue()
        ->and($director->current_team_id)->toBe($team->id)
        ->and($director->is_system_admin)->toBeFalse()
        ->and(Team::where('is_personal', true)->count())->toBe(0)
        ->and($this->admin->teams()->count())->toBe(0);
});

test('an existing account is only made a director on purpose and keeps its password', function () {
    $existing = attendant(institution());

    admin_page('institutions')
        ->set('institutionName', 'Second Bar')
        ->set('name', 'Someone')
        ->set('email', $existing->email)
        ->set('password', 'another-pass-1')
        ->set('password_confirmation', 'another-pass-1')
        ->call('create')
        ->assertHasErrors(['email']);

    expect(Team::where('name', 'Second Bar')->exists())->toBeFalse();

    admin_page('institutions')
        ->set('institutionName', 'Second Bar')
        ->set('useExistingAccount', true)
        ->set('email', $existing->email)
        ->call('create')
        ->assertHasNoErrors();

    expect($existing->directs(Team::where('name', 'Second Bar')->sole()))->toBeTrue()
        ->and(Hash::check('password', $existing->fresh()->password))->toBeTrue();
});

test('a system admin cannot be made a director', function () {
    admin_page('institutions')
        ->set('institutionName', 'Own Bar')
        ->set('useExistingAccount', true)
        ->set('email', $this->admin->email)
        ->call('create')
        ->assertHasErrors(['email']);

    expect(Team::count())->toBe(0);
});

test('names that would shadow a route are refused', function (string $name) {
    admin_page('institutions')
        ->set('institutionName', $name)
        ->set('name', 'D')
        ->set('email', 'd@example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('create')
        ->assertHasErrors(['institutionName']);

    expect(Team::count())->toBe(0)->and(User::where('email', 'd@example.com')->exists())->toBeFalse();
})->with(['admin', 'Admin!', 'API', 'Settings', 'account', 'home', '!!!']);

test('institutions with the same name get different slugs, even after suspension', function () {
    institution('Twin')->delete();

    admin_page('institutions')
        ->set('institutionName', 'Twin')
        ->set('name', 'D')
        ->set('email', 'd@example.com')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('create')
        ->assertHasNoErrors();

    expect(Team::withTrashed()->pluck('slug')->all())->toBe(['twin', 'twin-1']);
});

test('the admin suspends and restores an institution without losing data', function (string $page) {
    $team = institution();
    $director = director($team);
    $jane = attendant($team);
    GameRecord::factory()->create(['institution_id' => $team->id, 'attendant_id' => $jane->id]);

    $component = $page === 'institutions' ? admin_page('institutions') : admin_page('institution', ['institutionId' => $team->id]);
    $arguments = $page === 'institutions' ? [$team->id] : [];

    $component->call('suspend', ...$arguments)->assertSee('Suspended');

    expect($team->fresh()->trashed())->toBeTrue()->and(GameRecord::count())->toBe(1);
    $this->actingAs($director)->get(route('dashboard', ['current_team' => $team->slug]))->assertForbidden();
    // The platform operators can still look into it.
    $this->actingAs($this->admin)->get(route('admin.institutions.show', $team->id))->assertOk()->assertSee($jane->name);

    $component->call('restore', ...$arguments)->assertDontSee('Suspended');

    expect($team->fresh()->trashed())->toBeFalse();
    $this->actingAs($director)->get(route('dashboard', ['current_team' => $team->slug]))->assertOk();
})->with(['institutions', 'institution']);

test('drilling into an institution shows only that institution', function () {
    [$alpha, $beta] = [institution(), institution()];
    $jane = attendant($alpha, User::factory()->create(['name' => 'Jane In Alpha']));
    $omar = attendant($beta, User::factory()->create(['name' => 'Omar In Beta']));
    GameRecord::factory()->create(['institution_id' => $alpha->id, 'attendant_id' => $jane->id, 'code' => 'AAA222']);
    GameRecord::factory()->create(['institution_id' => $beta->id, 'attendant_id' => $omar->id, 'code' => 'BBB333']);

    admin_page('institution', ['institutionId' => $alpha->id])
        ->assertSee('Jane In Alpha')->assertSee('AAA222')
        ->assertDontSee('Omar In Beta')->assertDontSee('BBB333');
});

test('personal teams are not institutions', function () {
    $personal = Team::factory()->personal()->create(['name' => 'Somebody Personal Team']);

    admin_page('institutions')->assertDontSee('Somebody Personal Team');
    $this->actingAs($this->admin)->get(route('admin.institutions.show', $personal->id))->assertNotFound();
});

test('the admin sets a director password without any email', function () {
    $team = institution();
    $director = director($team);
    token_for($director);

    admin_page('institution', ['institutionId' => $team->id])
        ->call('startPassword', membership($team, $director)->id)
        ->set('password', 'brand-new-pass')
        ->set('password_confirmation', 'brand-new-pass')
        ->call('setPassword')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-pass', $director->fresh()->password))->toBeTrue()
        ->and($director->tokens()->count())->toBe(0);
});

test('a mistyped confirmation changes nothing', function () {
    $team = institution();
    $director = director($team);

    admin_page('institution', ['institutionId' => $team->id])
        ->call('startPassword', membership($team, $director)->id)
        ->set('password', 'brand-new-pass')
        ->set('password_confirmation', 'something-else')
        ->call('setPassword')
        ->assertHasErrors(['password']);

    expect(Hash::check('password', $director->fresh()->password))->toBeTrue();
});

test('only this institution\'s directors can get a password here', function (Closure $makeMembership, int $status) {
    $team = institution();
    [$membershipTeam, $user] = $makeMembership($team);

    admin_page('institution', ['institutionId' => $team->id])
        ->call('startPassword', membership($membershipTeam, $user)->id)
        ->assertStatus($status);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'its attendant' => [fn (Team $team) => [$team, attendant($team)], 404],
    'another institution\'s director' => [function () {
        $other = institution();

        return [$other, director($other)];
    }, 404],
    'a director who is a system admin' => [fn (Team $team) => [$team, director($team, User::factory()->systemAdmin()->create())], 403],
]);
