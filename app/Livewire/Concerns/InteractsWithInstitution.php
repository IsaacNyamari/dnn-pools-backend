<?php

namespace App\Livewire\Concerns;

use App\Models\Membership;
use App\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * A director dashboard page. Every query of such a page must start from $this->institution.
 */
trait InteractsWithInstitution
{
    #[Locked]
    public int $institutionId;

    public function mountInteractsWithInstitution(?string $current_team = null): void
    {
        // A full page does not hand route parameters to trait hooks, so read the route.
        $slug = $current_team ?? request()->route('current_team');

        $this->institutionId = Team::query()->where('slug', $slug)->value('id') ?? 0;
    }

    /**
     * Runs after mount and on every later request, before any action.
     */
    public function bootedInteractsWithInstitution(): void
    {
        $team = Team::withTrashed()->find($this->institutionId);

        abort_unless($team && auth()->user()?->directs($team), 403);
    }

    #[Computed]
    public function institution(): Team
    {
        return Team::query()->institutions()->findOrFail($this->institutionId);
    }

    /**
     * Everyone with a membership, active or not, for filters and names.
     *
     * @return Collection<int, string> user id => name
     */
    #[Computed]
    public function people(): Collection
    {
        return $this->institution->memberships()
            ->with('user:id,name')
            ->get()
            ->mapWithKeys(fn (Membership $membership) => [$membership->user_id => $membership->user->name])
            ->sort();
    }
}
