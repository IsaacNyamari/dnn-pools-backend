<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display time zone
    |--------------------------------------------------------------------------
    |
    | Data is stored in UTC. The web dashboards show times, and decide what
    | "today" and the date filters mean, in this time zone.
    |
    */

    'timezone' => env('POOL_TIMEZONE', 'Africa/Nairobi'),

    /*
    |--------------------------------------------------------------------------
    | App keys
    |--------------------------------------------------------------------------
    |
    | The shared key built into the Android app, sent as X-App-Key. One key for
    | the whole app. List two, comma separated, while replacing a key so that
    | phones on the old build keep working. Empty closes the API in production.
    |
    */

    'app_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('POOL_APP_KEYS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Deploy from the admin dashboard
    |--------------------------------------------------------------------------
    |
    | The dashboard leaves a request in "path", where the cron script
    | scripts/cpanel-deploy-runner.sh looks for it. The script has that folder
    | built in, so change "path" only in tests. The button is disabled when the
    | script has not started for "cron_stale_minutes".
    |
    */

    'deploy' => [
        'path' => storage_path('app/deploy'),
        'git_path' => base_path('.git'),
        'cron_stale_minutes' => 15,
    ],

];
