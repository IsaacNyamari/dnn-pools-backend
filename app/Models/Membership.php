<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property TeamRole $role
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'role', 'is_active'])]
class Membership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the team that the membership belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this institution may change the account's name, email or password. An
     * account that also reaches another institution (even a suspended one or a
     * deactivated membership) or the platform admin pages is shared: otherwise one
     * director could take it over and see someone else's data.
     */
    public function accountBelongsOnlyToThisInstitution(): bool
    {
        return ! $this->user->is_system_admin
            && static::query()
                ->where('user_id', $this->user_id)
                ->where('team_id', '!=', $this->team_id)
                ->whereHas('team', fn (Builder $query) => $query
                    ->withoutGlobalScope(SoftDeletingScope::class)
                    ->where('is_personal', false))
                ->doesntExist();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'is_active' => 'boolean',
        ];
    }
}
