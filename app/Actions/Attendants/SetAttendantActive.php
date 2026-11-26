<?php

namespace App\Actions\Attendants;

use App\Models\Membership;
use Illuminate\Support\Facades\DB;

class SetAttendantActive
{
    /**
     * Deactivate or reactivate one membership. The membership itself is never deleted,
     * because game records and shifts refer to it.
     */
    public function handle(Membership $membership, bool $active): void
    {
        DB::transaction(function () use ($membership, $active) {
            $membership->update(['is_active' => $active]);

            if (! $active && $membership->user->activeInstitutionMemberships()->doesntExist()) {
                $membership->user->tokens()->delete();
            }
        });
    }
}
