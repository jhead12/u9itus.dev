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
