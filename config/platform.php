<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Mode
    |--------------------------------------------------------------------------
    |
    | This application runs as a standalone Laravel 12 application.
    |
    */
    'mode' => 'standalone',

    /*
    |--------------------------------------------------------------------------
    | Standalone Platform Configuration
    |--------------------------------------------------------------------------
    */
    'standalone' => [
        'enabled' => true,
        'frontend_url' => env('FRONTEND_URL', env('APP_URL')),
        'api_url' => env('APP_URL') . '/api',
        'routes_enabled' => true,
        
        // Authentication configuration
        'auth' => [
            'guard' => 'web',
            'provider' => 'users',
            'session_lifetime' => 120, // minutes
            'admin_2fa' => [
                'enabled_default' => env('ADMIN_2FA_ENFORCED_DEFAULT', false),
                'totp_window' => (int) env('ADMIN_2FA_TOTP_WINDOW', 1),
                'session_ttl_minutes' => (int) env('ADMIN_2FA_SESSION_TTL_MINUTES', 120),
            ],
        ],
        
        // Features available in standalone mode
        'features' => [
            'registration' => true,
            'password_reset' => true,
            'email_verification' => true,
            'two_factor' => false, // TODO: Enable in Phase 2
            'api_tokens' => true,
            'teams' => false, // TODO: Enable for enterprise
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'standalone' => [
            'auth' => 'auth:sanctum',
            'verified' => 'verified',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    |
    | Service provider class names for dependency injection.
    |
    */
    'services' => [
        'notification' => [
            'standalone' => \App\Services\StandardNotificationService::class,
        ],
        'auth' => [
            'standalone' => \App\Services\StandardAuthService::class,
        ],
    ],

];
