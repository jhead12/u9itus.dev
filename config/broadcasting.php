<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Set to 'reverb' in all environments. Override to 'log' in CI/testing
    | by setting BROADCAST_CONNECTION=log in .env.testing.
    |
    */
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [

        /*
        |----------------------------------------------------------------------
        | Laravel Reverb (self-hosted WebSocket server — Phase 11)
        |
        | Development:  php artisan reverb:start
        | Production:   php artisan reverb:start --host=0.0.0.0 --port=8080
        | Railway:      add REVERB_* env vars; expose port 8080 in railway.json
        |
        | Environment variables required:
        |   REVERB_APP_ID       — arbitrary string, e.g. "u9itus-app"
        |   REVERB_APP_KEY      — random 32-char key (php artisan key:generate --show)
        |   REVERB_APP_SECRET   — random 32-char secret
        |   REVERB_HOST         — hostname of Reverb server (localhost / Railway domain)
        |   REVERB_PORT         — 8080 (or 443 with TLS proxy)
        |   REVERB_SCHEME       — http (dev) | https (production behind TLS proxy)
        |----------------------------------------------------------------------
        */
        'reverb' => [
            'driver'  => 'reverb',
            'key'     => env('REVERB_APP_KEY'),
            'secret'  => env('REVERB_APP_SECRET'),
            'app_id'  => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST', '0.0.0.0'),
                'port'   => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                // useTLS is derived automatically from scheme=https
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'timeout' => env('REVERB_TIMEOUT', 5),
        ],

        /*
        |----------------------------------------------------------------------
        | Log driver — used in CI / local when no Reverb server is running
        |----------------------------------------------------------------------
        */
        'log' => [
            'driver' => 'log',
        ],

        /*
        |----------------------------------------------------------------------
        | Null driver — silent discard (useful in unit tests)
        |----------------------------------------------------------------------
        */
        'null' => [
            'driver' => 'null',
        ],

    ],

];
