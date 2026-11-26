<?php

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The institutions this user directs. There is deliberately no way to create one here.
     */
    #[Computed]
    public function institutions()
    {
        return Auth::user()->directedInstitutions();
    }

    public function switchTeam(string $slug): void
    {
        $user = Auth::user();
        $team = Team::where('slug', $slug)->firstOrFail();

        abort_unless($user->directs($team), 403);

        $user->switchTeam($team);

        $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);
    }
}; ?>

<div>
    @php($current = $this->institutions->firstWhere('id', auth()->user()->current_team_id) ?? $this->institutions->first())

    @if ($this->institutions->count() > 1)
        <flux:dropdown position="bottom" align="start">
            <flux:button variant="ghost" class="group w-full justify-start in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="team-switcher-trigger">
                <flux:icon name="users" class="hidden size-4 in-data-flux-sidebar-collapsed-desktop:block" />
                <span class="truncate font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">{{ $current->name }}</span>
                <flux:icon name="chevrons-up-down" variant="micro" class="ms-auto size-4 in-data-flux-sidebar-collapsed-desktop:hidden" />
            </flux:button>

            <flux:menu class="min-w-56">
                <flux:menu.heading>{{ __('Institutions') }}</flux:menu.heading>

                @foreach ($this->institutions as $team)
                    <flux:menu.item wire:click="switchTeam('{{ $team->slug }}')" class="cursor-pointer" data-test="team-switcher-item">
                        <div class="flex w-full items-center justify-between">
                            <span>{{ $team->name }}</span>
                            @if ($team->id === $current->id)
                                <flux:icon name="check" class="size-4" />
                            @endif
                        </div>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @elseif ($current)
        <flux:heading class="truncate px-2 in-data-flux-sidebar-collapsed-desktop:hidden">{{ $current->name }}</flux:heading>
    @endif
</div>
