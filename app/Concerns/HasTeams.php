<?php

namespace App\Concerns;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this, Membership, 'pivot'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->using(Membership::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Memberships that currently grant access: active, in an institution that is not
     * suspended (soft deleted). Personal teams are never institutions.
     *
     * @return HasMany<Membership, $this>
     */
    public function activeInstitutionMemberships(): HasMany
    {
        return $this->teamMemberships()
            ->where('team_members.is_active', true)
            ->whereHas('team', fn (Builder $query) => $query->where('is_personal', false));
    }

    /**
     * Institutions the user may run from the web dashboard.
     *
     * @return Collection<int, Team>
     */
    public function directedInstitutions(): Collection
    {
        return Team::query()
            ->institutions()
            ->whereHas('memberships', fn (Builder $query) => $query
                ->where('user_id', $this->id)
                ->where('is_active', true)
                ->whereIn('role', TeamRole::directorValues()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Determine whether the user is an active director of the institution.
     */
    public function directs(Team $team): bool
    {
        if ($team->is_personal || $team->trashed()) {
            return false;
        }

        return $this->teamMemberships()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->whereIn('role', TeamRole::directorValues())
            ->exists();
    }

    /**
     * The institution whose dashboard the user lands on, if any.
     */
    public function dashboardTeam(): ?Team
    {
        $directed = $this->directedInstitutions();

        return $directed->firstWhere('id', $this->current_team_id) ?? $directed->first();
    }

    /**
     * The page the user is sent to after signing in.
     */
    public function homePath(): string
    {
        if ($this->is_system_admin) {
            return route('admin.overview', absolute: false);
        }

        $team = $this->dashboardTeam();

        return $team
            ? route('dashboard', ['current_team' => $team->slug], absolute: false)
            : route('account', absolute: false);
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first()
            ?->role;
    }
}
