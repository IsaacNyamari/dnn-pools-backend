<?php

namespace App\Actions\Institutions;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateInstitution
{
    /**
     * Create an institution and make the given person its director. An existing account
     * keeps its name and password; otherwise a new account is created.
     *
     * @param  array{name: string, email: string, password: string}|User  $director
     */
    public function handle(string $name, array|User $director): Team
    {
        return DB::transaction(function () use ($name, $director) {
            $team = Team::create(['name' => $name, 'is_personal' => false]);

            if (! $director instanceof User) {
                $director = new User([
                    'name' => $director['name'],
                    'email' => Str::lower($director['email']),
                    'password' => $director['password'],
                ]);
                $director->forceFill(['email_verified_at' => now()])->save();
            }

            $team->memberships()->create([
                'user_id' => $director->id,
                'role' => TeamRole::Owner,
            ]);

            if ($director->dashboardTeam()?->id === $team->id) {
                $director->update(['current_team_id' => $team->id]);
            }

            return $team;
        });
    }
}
