<?php

use App\Actions\Attendants\CreateAttendant;
use App\Actions\Institutions\CreateInstitution;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('accounts created on the dashboards can sign in at once, with no email involved', function () {
    Mail::fake();
    Notification::fake();

    $team = app(CreateInstitution::class)->handle('No Mail Bar', [
        'name' => 'Dina', 'email' => 'dina@example.com', 'password' => 'secret-pass-1',
    ]);
    app(CreateAttendant::class)->handle($team, 'Jane', 'jane@example.com', 'secret-pass-1');

    foreach (['dina@example.com' => '/no-mail-bar/dashboard', 'jane@example.com' => '/account'] as $email => $home) {
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'secret-pass-1', 'device_name' => 'phone'])
            ->assertOk();

        app('auth')->forgetGuards();
        $this->post(route('login.store'), ['email' => $email, 'password' => 'secret-pass-1'])->assertRedirect($home);
        $this->get($home)->assertOk();
        $this->post(route('logout'));
    }

    expect($team->members()->whereNull('email_verified_at')->count())->toBe(0);
    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
});

test('an unverified address never blocks access', function () {
    $team = institution();
    $user = director($team);
    $user->forceFill(['email_verified_at' => null])->save();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'phone'])
        ->assertOk();

    app('auth')->forgetGuards();
    $this->actingAs($user)->get(route('dashboard', ['current_team' => $team->slug]))->assertOk();
});
