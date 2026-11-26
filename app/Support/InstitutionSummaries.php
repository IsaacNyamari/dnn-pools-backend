<?php

namespace App\Support;

use App\Enums\TeamRole;
use App\Models\GameRecord;
use App\Models\Membership;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One line of figures per institution, for the platform operators.
 */
class InstitutionSummaries
{
    /**
     * Every institution, suspended ones included.
     *
     * @return Collection<int, array{team: Team, directors: int, attendants: int, today: int, games: int, last_upload: CarbonImmutable|null}>
     */
    public static function all(): Collection
    {
        $today = Reporting::today();

        $todayGames = self::gamesPerInstitution(
            Reporting::between(GameRecord::query(), 'local_timestamp', $today, $today),
        );
        $allGames = self::gamesPerInstitution(GameRecord::query());

        $lastUploads = GameRecord::query()
            ->selectRaw('institution_id, max(created_at) as last_upload')
            ->groupBy('institution_id')
            ->toBase()
            ->pluck('last_upload', 'institution_id');

        return Team::withTrashed()
            ->institutions()
            ->withCount([
                'memberships as directors_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereIn('role', TeamRole::directorValues()),
                'memberships as attendants_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('role', TeamRole::Member),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'team' => $team,
                'directors' => (int) $team->getAttribute('directors_count'),
                'attendants' => (int) $team->getAttribute('attendants_count'),
                'today' => (int) ($todayGames[$team->id] ?? 0),
                'games' => (int) ($allGames[$team->id] ?? 0),
                'last_upload' => isset($lastUploads[$team->id])
                    ? CarbonImmutable::parse($lastUploads[$team->id], 'UTC')
                    : null,
            ]);
    }

    /**
     * People with an active membership of the given roles in an active institution.
     *
     * @param  array<int, string>  $roles
     */
    public static function activePeople(array $roles): int
    {
        return Membership::query()
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->whereHas('team', fn (Builder $query) => $query->where('is_personal', false))
            ->distinct()
            ->count('user_id');
    }

    /**
     * @param  Builder<GameRecord>  $query
     * @return Collection<int, mixed> institution id => games
     */
    private static function gamesPerInstitution(Builder $query): Collection
    {
        return Reporting::selectTotals($query)
            ->addSelect('institution_id')
            ->groupBy('institution_id')
            ->toBase()
            ->pluck('games', 'institution_id');
    }
}
