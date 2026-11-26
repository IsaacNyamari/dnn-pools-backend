<?php

use App\Actions\Attendants\CreateAttendant;
use App\Actions\Attendants\SetAttendantActive;
use App\Actions\Users\RevokeAccess;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\TeamRole;
use App\Livewire\Concerns\InteractsWithInstitution;
use App\Models\Membership;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendants')] class extends Component {
    use InteractsWithInstitution, PasswordValidationRules, ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $showCreate = false;
    public bool $showEdit = false;
    public bool $showPassword = false;

    #[Locked]
    public ?int $membershipId = null;

    #[Computed]
    public function attendants()
    {
        return $this->institution->memberships()
            ->where('role', TeamRole::Member)
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $membership) => Str::lower($membership->user->name));
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showCreate = true;
    }

    public function create(CreateAttendant $createAttendant): void
    {
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ], [
            'email.unique' => __('This email already has an account, so it cannot be added here.'),
        ]);

        $createAttendant->handle($this->institution, $validated['name'], $validated['email'], $validated['password']);

        $this->resetForm();
        unset($this->attendants);

        Flux::toast(variant: 'success', text: __('Attendant added.'));
    }

    public function startEdit(int $membershipId): void
    {
        $membership = $this->manageableAttendant($membershipId);

        $this->resetForm();
        $this->membershipId = $membership->id;
        $this->name = $membership->user->name;
        $this->email = $membership->user->email;
        $this->showEdit = true;
    }

    public function update(): void
    {
        $user = $this->manageableAttendant((int) $this->membershipId)->user;
        $this->email = Str::lower(trim($this->email));

        $user->fill($this->validate($this->profileRules($user->id)))->save();

        $this->resetForm();
        unset($this->attendants);

        Flux::toast(variant: 'success', text: __('Attendant updated.'));
    }

    public function startPassword(int $membershipId): void
    {
        $membership = $this->manageableAttendant($membershipId);

        $this->resetForm();
        $this->membershipId = $membership->id;
        $this->showPassword = true;
    }

    public function setPassword(RevokeAccess $revokeAccess): void
    {
        $user = $this->manageableAttendant((int) $this->membershipId)->user;

        $validated = $this->validate(['password' => $this->passwordRules()]);

        $user->update(['password' => $validated['password']]);
        $revokeAccess->handle($user);

        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Password set. The attendant must sign in again.'));
    }

    public function setActive(int $membershipId, bool $active, SetAttendantActive $setActive): void
    {
        $setActive->handle($this->attendant($membershipId), $active);

        unset($this->attendants);

        Flux::toast(variant: 'success', text: $active ? __('Attendant reactivated.') : __('Attendant deactivated.'));
    }

    /**
     * An attendant of this institution. Ids come from the browser, so they are always
     * looked up inside the institution.
     */
    private function attendant(int $membershipId): Membership
    {
        return $this->institution->memberships()
            ->where('role', TeamRole::Member)
            ->findOrFail($membershipId);
    }

    /**
     * An attendant whose account details this director may change.
     */
    private function manageableAttendant(int $membershipId): Membership
    {
        $membership = $this->attendant($membershipId);

        abort_unless($membership->accountBelongsOnlyToThisInstitution(), 403);

        return $membership;
    }

    private function resetForm(): void
    {
        $this->reset('name', 'email', 'password', 'password_confirmation', 'membershipId', 'showCreate', 'showEdit', 'showPassword');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Attendants') }}</flux:heading>
            <flux:subheading>{{ __('Attendants sign in to the mobile app to start games.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="startCreate" data-test="add-attendant">{{ __('Add attendant') }}</flux:button>
    </div>

    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->attendants as $membership)
                    @php($manageable = $membership->accountBelongsOnlyToThisInstitution())
                    <flux:table.row :key="'membership-'.$membership->id">
                        <flux:table.cell>{{ $membership->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $membership->user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$membership->is_active ? 'green' : 'zinc'">
                                {{ $membership->is_active ? __('Active') : __('Deactivated') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($manageable)
                                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $membership->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="key" wire:click="startPassword({{ $membership->id }})">{{ __('Set password') }}</flux:button>
                                @else
                                    <flux:badge size="sm" color="blue">{{ __('Shared account') }}</flux:badge>
                                @endif

                                @if ($membership->is_active)
                                    <flux:button size="sm" variant="danger" wire:click="setActive({{ $membership->id }}, false)" wire:confirm="{{ __('Deactivate this attendant? They lose app access.') }}">{{ __('Deactivate') }}</flux:button>
                                @else
                                    <flux:button size="sm" wire:click="setActive({{ $membership->id }}, true)">{{ __('Reactivate') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($this->attendants->isEmpty())
            <flux:text class="py-6 text-center">{{ __('No attendants yet.') }}</flux:text>
        @endif
    </div>

    <flux:modal wire:model.self="showCreate" class="w-full max-w-lg">
        <form wire:submit="create" class="space-y-5">
            <flux:heading size="lg">{{ __('Add attendant') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="off" />
            <flux:input wire:model="password" type="password" :label="__('Password')" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEdit" class="w-full max-w-lg">
        <form wire:submit="update" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit attendant') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required autocomplete="off" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showPassword" class="w-full max-w-lg">
        <form wire:submit="setPassword" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Set password') }}</flux:heading>
                <flux:subheading>{{ __('This signs the attendant out of the app and the website.') }}</flux:subheading>
            </div>
            <flux:input wire:model="password" type="password" :label="__('New password')" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Set password') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
