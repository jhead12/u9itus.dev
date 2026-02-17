<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Mode
    |--------------------------------------------------------------------------
    |
    | Determines which platform(s) are enabled for this instance:
    | - 'wix'       : Only Wix App Extension routes/features enabled
    | - 'standalone': Only standalone application routes/features enabled
    | - 'dual'      : Both platforms enabled (default for development)
    |
    */
    'mode' => env('PLATFORM_MODE', 'dual'),

    /*
    |--------------------------------------------------------------------------
    | Wix Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to the Wix App Extension platform.
    |
    */
    'wix' => [
        'enabled' => env('PLATFORM_MODE', 'dual') !== 'standalone',
        'app_id' => env('WIX_APP_ID'),
        'app_secret' => env('WIX_APP_SECRET'),
        'routes_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Standalone Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to the standalone application platform.
    |
    */
    'standalone' => [
        'enabled' => env('PLATFORM_MODE', 'dual') !== 'wix',
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
    | Platform Detection Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware that helps detect and enforce platform-specific rules.
    |
    */
    'middleware' => [
        'wix' => [
            'verify' => \App\Http\Middleware\VerifyWixInstance::class,
        ],
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
    | Platform-specific service provider class names for dependency injection.
    |
    */
    'services' => [
        'notification' => [
            'wix' => \App\Services\WixNotificationService::class,
            'standalone' => \App\Services\StandardNotificationService::class,
        ],
        'auth' => [
            'wix' => \App\Services\WixAuthService::class,
            'standalone' => \App\Services\StandardAuthService::class,
        ],
    ],

];
