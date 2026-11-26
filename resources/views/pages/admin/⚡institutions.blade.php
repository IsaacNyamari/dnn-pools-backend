<?php

use App\Actions\Institutions\CreateInstitution;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Livewire\Concerns\AuthorizesSystemAdmin;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;
use App\Support\InstitutionSummaries;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Institutions')] class extends Component {
    use AuthorizesSystemAdmin, PasswordValidationRules, ProfileValidationRules;

    public string $institutionName = '';
    public bool $useExistingAccount = false;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    #[Computed]
    public function institutions()
    {
        return InstitutionSummaries::all();
    }

    public function create(CreateInstitution $createInstitution): void
    {
        $this->email = Str::lower(trim($this->email));
        $institutionRules = ['institutionName' => ['required', 'string', 'max:255', new TeamName]];

        if ($this->useExistingAccount) {
            $this->validate([...$institutionRules, 'email' => ['required', 'string', 'email']]);

            $director = User::query()->where('email', $this->email)->first();

            if ($director === null) {
                throw ValidationException::withMessages(['email' => __('No account has this email.')]);
            }

            if ($director->is_system_admin) {
                throw ValidationException::withMessages(['email' => __('A system administrator cannot be a director.')]);
            }
        } else {
            $validated = $this->validate([
                ...$institutionRules,
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ], [
                'email.unique' => __('This email already has an account. Tick "existing account" to make that person the director.'),
            ]);

            $director = ['name' => $validated['name'], 'email' => $validated['email'], 'password' => $validated['password']];
        }

        $createInstitution->handle(trim($this->institutionName), $director);

        $this->reset();
        unset($this->institutions);

        Flux::toast(variant: 'success', text: __('Institution created.'));
    }

    public function suspend(int $institutionId): void
    {
        Team::query()->institutions()->findOrFail($institutionId)->delete();

        unset($this->institutions);
        Flux::toast(variant: 'success', text: __('Institution suspended.'));
    }

    public function restore(int $institutionId): void
    {
        Team::onlyTrashed()->institutions()->findOrFail($institutionId)->restore();

        unset($this->institutions);
        Flux::toast(variant: 'success', text: __('Institution restored.'));
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Institutions') }}</flux:heading>
        <flux:subheading>{{ __('A suspended institution has no app or dashboard access. Its data is kept.') }}</flux:subheading>
    </div>

    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Institution') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Directors') }}</flux:table.column>
                <flux:table.column>{{ __('Attendants') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->institutions as $row)
                    <flux:table.row :key="'institution-'.$row['team']->id">
                        <flux:table.cell>
                            <flux:link :href="route('admin.institutions.show', $row['team']->id)" wire:navigate>{{ $row['team']->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$row['team']->trashed() ? 'red' : 'green'">{{ $row['team']->trashed() ? __('Suspended') : __('Active') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['directors'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['attendants'] }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end">
                                @if ($row['team']->trashed())
                                    <flux:button size="sm" wire:click="restore({{ $row['team']->id }})">{{ __('Restore') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="danger" wire:click="suspend({{ $row['team']->id }})" wire:confirm="{{ __('Suspend this institution? Nobody in it can use the app or dashboard until you restore it.') }}">{{ __('Suspend') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($this->institutions->isEmpty())
            <flux:text class="py-6 text-center">{{ __('No institutions yet.') }}</flux:text>
        @endif
    </div>

    <flux:separator />

    <form wire:submit="create" class="flex max-w-lg flex-col gap-5">
        <flux:heading size="lg">{{ __('New institution') }}</flux:heading>
        <flux:input wire:model="institutionName" :label="__('Institution name')" required />

        <flux:heading>{{ __('Its director') }}</flux:heading>
        <flux:checkbox wire:model.live="useExistingAccount" :label="__('The director has an existing account')" :description="__('They keep their name and password.')" />

        @unless ($useExistingAccount)
            <flux:input wire:model="name" :label="__('Director name')" required autocomplete="off" />
        @endunless

        <flux:input wire:model="email" type="email" :label="__('Director email')" required autocomplete="off" />

        @unless ($useExistingAccount)
            <flux:input wire:model="password" type="password" :label="__('Password')" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />
        @endunless

        <div>
            <flux:button type="submit" variant="primary" data-test="create-institution">{{ __('Create institution') }}</flux:button>
        </div>
    </form>
</div>
