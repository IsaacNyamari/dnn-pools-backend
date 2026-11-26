<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

trait RedirectsToHome
{
    /**
     * Where this user's role lands after signing in.
     */
    protected function homePath(Request $request): string
    {
        $user = $request->user();

        abort_if(! $user, 403);

        return $user->homePath();
    }
}
