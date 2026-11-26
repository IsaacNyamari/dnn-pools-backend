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

];
