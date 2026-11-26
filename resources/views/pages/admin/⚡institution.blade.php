<?php

use App\Actions\Users\RevokeAccess;
use App\Concerns\PasswordValidationRules;
use App\Enums\TeamRole;
use App\Livewire\Concerns\AuthorizesSystemAdmin;
use App\Models\Membership;
use App\Models\Team;
use App\Support\Reporting;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use AuthorizesSystemAdmin, PasswordValidationRules;

    #[Locked]
    public int $institutionId;

    public string $password = '';
    public string $password_confirmation = '';
    public bool $showPassword = false;

    #[Locked]
    public ?int $membershipId = null;

    public function mount(int $institutionId): void
    {
        $this->institutionId = Team::withTrashed()->institutions()->findOrFail($institutionId)->id;
    }

    #[Computed]
    public function institution(): Team
    {
        return Team::withTrashed()->institutions()->findOrFail($this->institutionId);
    }

    #[Computed]
    public function memberships()
    {
        return $this->institution->memberships()
            ->with('user')
            ->get()
            ->sortBy(fn ($membership) => [$membership->role->isDirector() ? 0 : 1, Str::lower($membership->user->name)]);
    }

    #[Computed]
    public function today(): array
    {
        $today = Reporting::today();

        return Reporting::totals(Reporting::between($this->institution->gameRecords()->getQuery(), 'local_timestamp', $today, $today));
    }

    #[Computed]
    public function allTime(): array
    {
        return Reporting::totals($this->institution->gameRecords()->getQuery());
    }

    #[Computed]
    public function syncDelay(): array
    {
        return Reporting::syncDelay($this->institution->gameRecords()->getQuery());
    }

    #[Computed]
    public function recentGames()
    {
        return $this->institution->gameRecords()
            ->with(['attendant:id,name', 'uploader:id,name'])
            ->latest('local_timestamp')
            ->latest('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function recentShifts()
    {
        return $this->institution->shifts()
            ->with('attendant:id,name')
            ->latest('start_time')
            ->limit(10)
            ->get();
    }

    public function suspend(): void
    {
        if (! $this->institution->trashed()) {
            $this->institution->delete();
            Flux::toast(variant: 'success', text: __('Institution suspended.'));
        }
    }

    public function restore(): void
    {
        if ($this->institution->trashed()) {
            $this->institution->restore();
            Flux::toast(variant: 'success', text: __('Institution restored.'));
        }
    }

    public function startPassword(int $membershipId): void
    {
        $membership = $this->director($membershipId);

        $this->resetPasswordForm();
        $this->membershipId = $membership->id;
        $this->showPassword = true;
    }

    /**
     * No email is needed: the operator hands the new password to the director.
     */
    public function setPassword(RevokeAccess $revokeAccess): void
    {
        $user = $this->director((int) $this->membershipId)->user;

        $validated = $this->validate(['password' => $this->passwordRules()]);

        $user->update(['password' => $validated['password']]);
        $revokeAccess->handle($user);

        $this->resetPasswordForm();

        Flux::toast(variant: 'success', text: __('Password set. The director must sign in again.'));
    }

    /**
     * A director of this institution whose password may be set here. System administrator
     * accounts are changed with the app:system-admin command only.
     */
    private function director(int $membershipId): Membership
    {
        $membership = $this->institution->memberships()
            ->whereIn('role', TeamRole::directorValues())
            ->findOrFail($membershipId);

        abort_if($membership->user->is_system_admin, 403);

        return $membership;
    }

    private function resetPasswordForm(): void
    {
        $this->reset('password', 'password_confirmation', 'showPassword', 'membershipId');
        $this->resetValidation();
    }

    public function render()
    {
        return $this->view()->title($this->institution->name);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:link :href="route('admin.institutions.index')" wire:navigate>{{ __('All institutions') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $this->institution->name }}</flux:heading>
            <flux:badge size="sm" :color="$this->institution->trashed() ? 'red' : 'green'">{{ $this->institution->trashed() ? __('Suspended') : __('Active') }}</flux:badge>
        </div>

        @if ($this->institution->trashed())
            <flux:button wire:click="restore">{{ __('Restore') }}</flux:button>
        @else
            <flux:button variant="danger" wire:click="suspend" wire:confirm="{{ __('Suspend this institution? Nobody in it can use the app or dashboard until you restore it.') }}">{{ __('Suspend') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('Games today')" :value="$this->today['games']"
            :hint="__('Cash :cash · M-Pesa :mpesa', ['cash' => $this->today['cash'], 'mpesa' => $this->today['mpesa']])" />
        <x-stat :label="__('Games all time')" :value="$this->allTime['games']"
            :hint="__('Cash :cash · M-Pesa :mpesa', ['cash' => $this->allTime['cash'], 'mpesa' => $this->allTime['mpesa']])" />
        <x-stat :label="__('Failed / expired')" :value="$this->allTime['failed'].' / '.$this->allTime['expired']" :hint="__('All time')" />
        <x-stat :label="__('Typical sync delay (7 days)')" :value="\App\Support\Reporting::duration($this->syncDelay['median'])"
            :hint="__('Worst :max · :count uploads', ['max' => \App\Support\Reporting::duration($this->syncDelay['max']), 'count' => $this->syncDelay['count']])" />
    </div>

    <div class="overflow-x-auto">
        <flux:heading size="lg" class="mb-2">{{ __('People') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->memberships as $membership)
                    <flux:table.row :key="'membership-'.$membership->id">
                        <flux:table.cell>{{ $membership->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $membership->user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $membership->role->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $membership->is_active ? __('Active') : __('Deactivated') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($membership->role->isDirector() && ! $membership->user->is_system_admin)
                                <flux:button size="sm" variant="ghost" icon="key" wire:click="startPassword({{ $membership->id }})">{{ __('Set password') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model.self="showPassword" class="w-full max-w-lg">
        <form wire:submit="setPassword" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Set director password') }}</flux:heading>
                <flux:subheading>{{ __('This signs the director out of the app and the website. It also applies to any other institution they belong to.') }}</flux:subheading>
            </div>
            <flux:input wire:model="password" type="password" :label="__('New password')" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Set password') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <div>
        <flux:heading size="lg" class="mb-2">{{ __('Latest games') }}</flux:heading>
        <x-games-table :records="$this->recentGames" />
    </div>

    <div class="overflow-x-auto">
        <flux:heading size="lg" class="mb-2">{{ __('Latest shifts') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column>{{ __('Ended') }}</flux:table.column>
                <flux:table.column>{{ __('Attendant') }}</flux:table.column>
                <flux:table.column>{{ __('Cash') }}</flux:table.column>
                <flux:table.column>{{ __('M-Pesa') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->recentShifts as $shift)
                    <flux:table.row :key="'shift-'.$shift->id">
                        <flux:table.cell>{{ \App\Support\Reporting::dateTime($shift->start_time) }}</flux:table.cell>
                        <flux:table.cell>{{ \App\Support\Reporting::dateTime($shift->end_time) }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->attendant->name }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->total_cash }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->total_mpesa }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($this->recentShifts->isEmpty())
            <flux:text class="py-6 text-center">{{ __('No shifts yet.') }}</flux:text>
        @endif
    </div>
</div>
