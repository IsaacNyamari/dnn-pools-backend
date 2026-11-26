<?php

namespace App\Actions\Attendants;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAttendant
{
    /**
     * Create a new account that belongs to this institution only. The caller validates the
     * input; in particular the email must not belong to an existing account.
     */
    public function handle(Team $institution, string $name, string $email, string $password): Membership
    {
        return DB::transaction(function () use ($institution, $name, $email, $password) {
            $user = new User([
                'name' => $name,
                'email' => Str::lower($email),
                'password' => $password,
                'current_team_id' => $institution->id,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return $institution->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Member,
            ]);
        });
    }
}
