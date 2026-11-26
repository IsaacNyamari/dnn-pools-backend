<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Concerns\AuthorizesSystemAdmin;
use App\Support\Deployment;
use App\Support\Reporting;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Deployment')] class extends Component {
    use AuthorizesSystemAdmin, PasswordValidationRules;

    public string $current_password = '';
    public bool $showConfirm = false;

    public function startDeploy(): void
    {
        $this->reset('current_password');
        $this->resetValidation();
        $this->showConfirm = true;
    }

    /**
     * The password is asked every time: an unattended session must not be able to change
     * what the live site runs.
     */
    public function deploy(Deployment $deployment): void
    {
        $key = 'deploy-password:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->reset('current_password');

            throw ValidationException::withMessages([
                'current_password' => __('Too many attempts. Wait a minute and try again.'),
            ]);
        }

        RateLimiter::hit($key, 60);

        try {
            $this->validate(['current_password' => $this->currentPasswordRules()]);
        } finally {
            $this->reset('current_password');
        }

        RateLimiter::clear($key);
        $this->showConfirm = false;

        if (! $deployment->cronIsActive()) {
            Flux::toast(variant: 'danger', text: __('The cron job is not running, so nothing was requested.'));
        } elseif ($deployment->request(Auth::user())) {
            Flux::toast(variant: 'success', text: __('Deployment requested.'));
        } else {
            Flux::toast(variant: 'warning', text: __('A deployment is already waiting or running.'));
        }
    }

    public function cancel(Deployment $deployment): void
    {
        $deployment->cancel();
    }

    public function with(Deployment $deployment): array
    {
        return [
            'commit' => $deployment->currentCommit(),
            'cronActive' => $deployment->cronIsActive(),
            'cronSeenAt' => $deployment->cronSeenAt(),
            'pending' => $deployment->pending(),
            'running' => $deployment->isRunning(),
            'lastRun' => $deployment->lastRun(),
            'log' => $deployment->logTail(),
            'cronCommand' => '/bin/sh '.base_path('scripts/cpanel-deploy-runner.sh').' >/dev/null 2>&1',
        ];
    }

    public function time(?string $value): string
    {
        return Reporting::dateTime($value ? CarbonImmutable::parse($value) : null);
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6" @if ($pending || $running) wire:poll.10s @endif>
    <div>
        <flux:heading size="xl">{{ __('Deployment') }}</flux:heading>
        <flux:subheading>{{ __('Put the latest code from the main branch on GitHub onto this server.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-stat :label="__('Version on this server')" :value="$commit ?? '—'" />
        <x-stat :label="__('Cron job')" :value="$cronActive ? __('Running') : __('Not running')"
            :hint="$cronSeenAt ? __('Last check :time', ['time' => \App\Support\Reporting::dateTime($cronSeenAt)]) : __('It has never checked in')" />
    </div>

    @unless ($cronActive)
        <flux:callout icon="exclamation-triangle" color="amber" data-test="cron-setup">
            <flux:callout.heading>{{ __('Set up the cron job') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('In cPanel, open Cron Jobs, choose "Once Per Minute" and enter this command. This page notices it within a minute.') }}
            </flux:callout.text>
            <pre class="mt-2 whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 text-xs select-all dark:bg-zinc-900">{{ $cronCommand }}</pre>
        </flux:callout>
    @endunless

    @if ($running)
        <flux:callout icon="arrow-path" color="blue">
            <flux:callout.heading>{{ __('Deploying now') }}</flux:callout.heading>
            <flux:callout.text>{{ __('The site goes into maintenance for a short while. If you see the maintenance message, wait a minute and reload the page.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($pending)
        <flux:callout icon="clock" color="blue">
            <flux:callout.heading>{{ __('Waiting for the cron job') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Requested by :name, :time.', ['name' => $pending['requested_by'], 'time' => $this->time($pending['requested_at'])]) }}</flux:callout.text>
            <div class="mt-3">
                <flux:button size="sm" wire:click="cancel">{{ __('Cancel request') }}</flux:button>
            </div>
        </flux:callout>
    @else
        <div>
            <flux:button variant="primary" icon="cloud-arrow-down" wire:click="startDeploy" :disabled="! $cronActive" data-test="deploy-latest">
                {{ __('Deploy latest') }}
            </flux:button>
        </div>
    @endif

    @if ($lastRun && ! $running)
        <flux:card class="!p-4">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading>{{ __('Last deployment') }}</flux:heading>
                <flux:badge size="sm" :color="($lastRun['state'] ?? '') === 'success' ? 'green' : 'red'">
                    {{ ($lastRun['state'] ?? '') === 'success' ? __('Succeeded') : __('Failed') }}
                </flux:badge>
            </div>
            <flux:text class="mt-2">{{ $lastRun['message'] ?? '' }}</flux:text>
            <flux:text size="sm" class="mt-2">
                {{ __('Started :start', ['start' => $this->time($lastRun['started_at'] ?? null)]) }}
                @if (! empty($lastRun['finished_at']))
                    · {{ __('finished :end', ['end' => $this->time($lastRun['finished_at'])]) }}
                @endif
                @if (! empty($lastRun['from']))
                    · {{ $lastRun['from'] }} → {{ $lastRun['to'] ?? $lastRun['from'] }}
                @endif
            </flux:text>

            @if ($log !== '')
                <details class="mt-3" wire:ignore.self>
                    <summary class="cursor-pointer text-sm">{{ __('Show the log') }}</summary>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-md bg-zinc-100 p-3 text-xs dark:bg-zinc-900">{{ $log }}</pre>
                </details>
            @endif
        </flux:card>
    @endif

    <flux:text size="sm">
        {{ __('After changing pages or styles, run "npm run build" and commit public/build before you push: the server does not build them.') }}
    </flux:text>

    <flux:modal wire:model.self="showConfirm" class="w-full max-w-lg">
        <form wire:submit="deploy" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Deploy the latest code?') }}</flux:heading>
                <flux:subheading>{{ __('Everything on the main branch goes live. Enter your password to confirm.') }}</flux:subheading>
            </div>
            <flux:input wire:model="current_password" type="password" :label="__('Your password')" required autocomplete="current-password" viewable />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Deploy') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
