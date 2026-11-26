<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only active directors of the institution in the URL may pass. Attendants, outsiders,
 * personal teams and suspended institutions are refused.
 */
class EnsureDirector
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $slug = $request->route('current_team');
        $team = is_string($slug) ? Team::where('slug', $slug)->first() : null;

        abort_unless($user && $team && $user->directs($team), 403);

        if (! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }
}
