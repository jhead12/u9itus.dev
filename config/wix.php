<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wix Application Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are provided when you register your app on the
    | Wix Developers Center (https://dev.wix.com).
    |
    */
    'app_id' => env('WIX_APP_ID'),
    'app_secret' => env('WIX_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Wix OAuth URLs
    |--------------------------------------------------------------------------
    */
    'oauth_url' => 'https://www.wix.com/installer/install',
    'token_url' => 'https://www.wixapis.com/oauth/access',

    /*
    |--------------------------------------------------------------------------
    | Wix API Base URL
    |--------------------------------------------------------------------------
    */
    'api_base_url' => 'https://www.wixapis.com',

    /*
    |--------------------------------------------------------------------------
    | Wix Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Used to verify incoming webhook signatures from Wix.
    |
    */
    'webhook_secret' => env('WIX_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | App URLs (your server endpoints)
    |--------------------------------------------------------------------------
    */
    'app_url' => env('WIX_APP_URL', env('APP_URL')),
    'redirect_url' => env('WIX_REDIRECT_URL', '/wix/oauth/callback'),
    'signup_url' => env('WIX_SIGNUP_URL', '/wix/signup'),

    /*
    |--------------------------------------------------------------------------
    | Wix Dashboard Component URLs
    |--------------------------------------------------------------------------
    |
    | These are the page URLs that load inside the Wix Dashboard iframe.
    |
    */
    'dashboard' => [
        'politician' => '/wix/dashboard/politician',
        'voter' => '/wix/dashboard/voter',
        'admin' => '/wix/dashboard/admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions / Scopes requested during installation
    |--------------------------------------------------------------------------
    */
    'scopes' => [
        'SCOPE.DC-MEMBERS.MANAGE-MEMBERS',     // Access voter contact info
        'SCOPE.DC-PAIDPLANS.MANAGE-PLANS',     // Manage subscriptions
        'SCOPE.WIX.EVENTS.READ-WRITE',         // Triggered emails
        'SCOPE.WIX.NOTIFICATIONS',             // Push notifications
        'SCOPE.WIX.AUTOMATIONS',               // Marketing automations
        'SCOPE.WIX.MARKETING.SEND-MESSAGES',   // SMS notifications
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'rate_limit' => [
            'hours' => 24,
            'max_ads' => 10,  // Max 10 ad notifications per voter per 24 hours
        ],
        'token_expiry_hours' => 24,
        'default_method' => 'email',  // email | push | sms
        'fallback_methods' => ['push', 'email'], // Fallback order if primary fails
        'batch_size' => 100,  // Max voters to notify in single batch
    ],
];
