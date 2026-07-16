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
                // SEC-6: admin TOTP enforcement defaults to ON. A platform setting
                // row (set via the admin settings page) still overrides this default.
                'enabled_default' => env('ADMIN_2FA_ENFORCED_DEFAULT', true),
                'totp_window' => (int) env('ADMIN_2FA_TOTP_WINDOW', 2),
                'session_ttl_minutes' => (int) env('ADMIN_2FA_SESSION_TTL_MINUTES', 120),
            ],
        ],
        
        // Features available in standalone mode
        'features' => [
            'registration' => false,
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
    | Map: Voter-Facing Features
    |--------------------------------------------------------------------------
    |
    | Feature flags for the auth-aware map experience. Default off in
    | production; flip on locally and per-cohort to ship dark.
    |
    | - voter_features_enabled: master switch for the entire auth-aware map
    | - sign_in_cta:            show the "Sign in to earn" header CTA to guests
    | - capture_ref_param:      drop the u9_referral_code cookie when /map is
    |                           loaded with ?ref=CODE (works for guests too)
    |
    */
    'map' => [
        'voter_features_enabled' => env('MAP_VOTER_FEATURES_ENABLED', false),
        'sign_in_cta'            => env('MAP_SIGN_IN_CTA', false),
        'capture_ref_param'      => env('MAP_CAPTURE_REF_PARAM', true),
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
