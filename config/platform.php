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
            // Generic (voter/politician/citizen) TOTP 2FA session TTL. Read by
            // EnsureTwoFactorVerified — kept separate from admin_2fa so the two
            // policies can be tuned independently.
            'two_factor' => [
                'session_ttl_minutes' => (int) env('TWO_FACTOR_SESSION_TTL_MINUTES', 120),
                // Self-service SMS recovery: lets a user with a verified phone
                // disable their own stuck 2FA without needing the
                // auth:reset-2fa artisan command run over SSH.
                'recovery_sms' => [
                    'code_ttl_minutes' => (int) env('TWO_FACTOR_RECOVERY_SMS_CODE_TTL_MINUTES', 10),
                    'max_attempts' => (int) env('TWO_FACTOR_RECOVERY_SMS_MAX_ATTEMPTS', 5),
                    'resend_cooldown_seconds' => (int) env('TWO_FACTOR_RECOVERY_SMS_RESEND_COOLDOWN_SECONDS', 60),
                ],
            ],
        ],
        
        // Features available in standalone mode
        'features' => [
            'registration' => false,
            'password_reset' => true,
            'email_verification' => true,
            'two_factor' => true, // Enforced per role via PlatformSettingsService
            'api_tokens' => true,
            'teams' => false, // TODO: Enable for enterprise
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Middleware
    |--------------------------------------------------------------------------
    |
    | The real middleware aliases are registered in bootstrap/app.php. This
    | section is reserved for feature-flag or environment-driven middleware
    | configuration only. Web routes use Laravel's session 'auth' middleware
    | directly; Sanctum is only used for politician/admin API routes.
    |
    */
    'middleware' => [
        'standalone' => [
            // Intentionally empty — aliases live in bootstrap/app.php.
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
        // Auth is handled by the standalone route/controllers directly.
        // No platform-swap auth service is currently registered.
    ],

];
