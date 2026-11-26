<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out everywhere, for example after someone else replaced their password.
 */
class RevokeAccess
{
    public function handle(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
