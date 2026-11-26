<?php

use App\Models\User;
use App\Support\Deployment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    // A private folder, so tests never leave a request where a real runner would find it.
    $this->folder = storage_path('framework/testing/deploy-'.Str::random(12));
    File::ensureDirectoryExists($this->folder);
    config(['pool.deploy.path' => $this->folder, 'pool.deploy.git_path' => "{$this->folder}/git"]);
    $this->admin = User::factory()->systemAdmin()->create();
});

afterEach(fn () => File::deleteDirectory($this->folder));

function cron_checked_in(int $minutesAgo = 0): void
{
    touch(test()->folder.'/heartbeat', now()->subMinutes($minutesAgo)->getTimestamp());
}

function deployment_page()
{
    return Livewire::actingAs(test()->admin)->test('pages::admin.deployment');
}

test('only system admins see the deployment page and its menu entry', function (Closure $makeUser) {
    $user = $makeUser();

    $this->actingAs($user)->get(route('admin.deployment'))->assertForbidden();
    Livewire::actingAs($user)->test('pages::admin.deployment')->assertForbidden();

    // Follow the user to their own landing page: no link to deployment there.
    $this->get($user->homePath())->assertOk()->assertDontSee(route('admin.deployment'));
})->with([
    'director' => [fn () => director(institution())],
    'attendant' => [fn () => attendant(institution())],
    'no membership' => [fn () => User::factory()->create()],
]);

test('a guest is sent to sign in', function () {
    $this->get(route('admin.deployment'))->assertRedirect(route('login'));
});

test('the admin sees the page and the menu entry', function () {
    $this->actingAs($this->admin)->get(route('admin.deployment'))->assertOk()->assertSee('Deploy latest');
    $this->get(route('admin.overview'))->assertSee(route('admin.deployment'));
});

test('someone who loses admin rights cannot use an open page', function () {
    cron_checked_in();
    $page = deployment_page();

    $this->admin->forceFill(['is_system_admin' => false])->save();

    $page->call('deploy')->assertForbidden();

    expect(app(Deployment::class)->pending())->toBeNull();
});

test('the right password leaves a request for cron', function () {
    cron_checked_in();

    deployment_page()
        ->call('startDeploy')
        ->set('current_password', 'password')
        ->call('deploy')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSee('Waiting for the cron job');

    expect(app(Deployment::class)->pending()['requested_by'])->toBe($this->admin->name)
        ->and(File::get("{$this->folder}/request"))->not->toContain('password');
});

test('a wrong password requests nothing', function () {
    cron_checked_in();

    deployment_page()
        ->set('current_password', 'wrong')
        ->call('deploy')
        ->assertHasErrors(['current_password'])
        ->assertSet('current_password', '');

    expect(app(Deployment::class)->pending())->toBeNull();
});

test('password guesses are limited', function () {
    cron_checked_in();
    $page = deployment_page();

    foreach (range(1, 5) as $attempt) {
        $page->set('current_password', 'wrong')->call('deploy');
    }

    // Even the right password waits now.
    $page->set('current_password', 'password')->call('deploy')->assertHasErrors(['current_password']);

    expect(app(Deployment::class)->pending())->toBeNull();

    $this->travel(61)->seconds();
    cron_checked_in();
    $page->set('current_password', 'password')->call('deploy')->assertHasNoErrors();

    expect(app(Deployment::class)->pending())->not->toBeNull();
});

test('nothing can be requested while cron is not running', function (?int $minutesAgo) {
    if ($minutesAgo !== null) {
        cron_checked_in($minutesAgo);
    }

    deployment_page()
        ->assertSee('Set up the cron job')
        ->assertSee('scripts/cpanel-deploy-runner.sh')
        ->set('current_password', 'password')
        ->call('deploy');

    expect(app(Deployment::class)->pending())->toBeNull();
})->with(['never' => [null], 'long ago' => [20]]);

test('only one deployment can wait or run at a time', function () {
    cron_checked_in();
    $deployment = app(Deployment::class);

    expect($deployment->request($this->admin))->toBeTrue()
        ->and($deployment->request($this->admin))->toBeFalse();

    $deployment->cancel();
    mkdir("{$this->folder}/lock");

    expect($deployment->request($this->admin))->toBeFalse()
        ->and($deployment->pending())->toBeNull();

    deployment_page()->assertSee('Deploying now')->assertDontSee('Deploy latest');
});

test('a waiting request can be cancelled', function () {
    cron_checked_in();
    app(Deployment::class)->request($this->admin);

    deployment_page()->call('cancel')->assertSee('Deploy latest');

    expect(app(Deployment::class)->pending())->toBeNull();
});

test('the last result and its log are shown as text', function () {
    cron_checked_in();
    File::put("{$this->folder}/status", "state=failed\nmessage=Could not pull from GitHub. Nothing was changed.\nstarted_at=2026-11-26T10:00:00Z\nfinished_at=2026-11-26T10:01:00Z\nfrom=abc1234\nto=abc1234\n");
    File::put("{$this->folder}/last.log", "\e[32mfatal: <script>alert(1)</script>\e[0m\n");

    deployment_page()
        ->assertSee('Failed')
        ->assertSee('Could not pull from GitHub. Nothing was changed.')
        ->assertSee('abc1234')
        ->assertSee('fatal: &lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)', false)
        ->assertDontSee('[32m');
});

test('a run that was killed is reported as failed, not as running', function () {
    File::put("{$this->folder}/status", "state=running\nmessage=Deploying.\nstarted_at=2026-11-26T10:00:00Z\n");

    expect(app(Deployment::class)->lastRun()['state'])->toBe('failed');

    mkdir("{$this->folder}/lock");

    expect(app(Deployment::class)->lastRun()['state'])->toBe('running');
});

test('the version on the server is read from the git files', function () {
    $git = "{$this->folder}/git";
    $hash = str_repeat('a1b2c3d4', 5);
    File::ensureDirectoryExists("{$git}/refs/heads");
    File::put("{$git}/HEAD", "ref: refs/heads/main\n");

    expect(app(Deployment::class)->currentCommit())->toBeNull();

    File::put("{$git}/packed-refs", "# pack-refs\n{$hash} refs/heads/main\n");
    expect(app(Deployment::class)->currentCommit())->toBe('a1b2c3d');

    File::put("{$git}/refs/heads/main", str_repeat('0f', 20)."\n");
    expect(app(Deployment::class)->currentCommit())->toBe('0f0f0f0');
});

test('the web side never runs a command', function () {
    $source = file_get_contents(app_path('Support/Deployment.php')).file_get_contents(resource_path('views/pages/admin/⚡deployment.blade.php'));

    expect($source)->not->toMatch('/\b(exec|shell_exec|passthru|proc_open|popen|system)\s*\(|Process::|Artisan::/');
});
