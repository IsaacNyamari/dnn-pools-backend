<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every API call must carry the shared key built into the Android app (`X-App-Key`).
 * It is one key for the whole app, not one per institution.
 *
 * The key can be extracted from the APK, so it only filters out callers that are not the
 * app. It never replaces the user's own sign-in token or the role checks.
 *
 * Refusals are 403, never 401: the app treats 401 as "your session has ended" and would
 * sign people out over a wrong build.
 */
class EnsureAppKey
{
    public const HEADER = 'X-App-Key';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $keys */
        $keys = config('pool.app_keys');

        if ($keys === []) {
            // Convenient on a developer machine; closed everywhere else.
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            return new JsonResponse([
                'message' => 'The server has no app key configured. Contact the administrator.',
                'code' => 'app_key_not_configured',
            ], 503);
        }

        $sent = (string) $request->header(self::HEADER, '');
        $valid = false;

        foreach ($keys as $key) {
            // No early exit: the time taken does not reveal which key came close.
            $valid = hash_equals($key, $sent) || $valid;
        }

        if (! $valid) {
            return new JsonResponse([
                'message' => 'This copy of the app is not allowed to use the server. Install the latest version.',
                'code' => 'invalid_app_key',
            ], 403);
        }

        return $next($request);
    }
}
