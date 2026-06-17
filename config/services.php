<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret'          => env('STRIPE_SECRET'),
        'public'          => env('STRIPE_KEY'),
        'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),
        'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL', env('APP_URL') . '/payout'),
        'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL', env('APP_URL') . '/payout'),
    ],

    'paypal' => [
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        // true = sandbox (api-m.sandbox.paypal.com), false = live (api-m.paypal.com)
        'sandbox'       => env('PAYPAL_SANDBOX', true),
        'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        // Strict signature verification should stay on in production.
        'strict_webhook_verification' => env('PAYPAL_STRICT_WEBHOOK_VERIFICATION', env('APP_ENV') === 'production'),
    ],

    'cashapp' => [
        'api_key' => env('CASHAPP_API_KEY'),
        'merchant_id' => env('CASHAPP_MERCHANT_ID'),
        'base_url' => env('CASHAPP_BASE_URL', 'https://sandbox.api.cash.app'),
        'payments_endpoint' => env('CASHAPP_PAYMENTS_ENDPOINT', '/network/v1/payments'),
        'default_grant_id' => env('CASHAPP_DEFAULT_GRANT_ID'),
        'region' => env('CASHAPP_REGION', 'US'),
        'signature' => env('CASHAPP_SIGNATURE'),
        'user_agent' => env('CASHAPP_USER_AGENT', 'u9itus-cashapp/1.0'),
        'timeout' => env('CASHAPP_TIMEOUT', 30),
    ],

    'fcm' => [
        'project_id'        => env('FCM_PROJECT_ID'),
        'credentials_path'  => env('FCM_CREDENTIALS_PATH'),
    ],

    'twilio' => [
        'account_sid'  => env('TWILIO_ACCOUNT_SID'),
        'auth_token'   => env('TWILIO_AUTH_TOKEN'),
        'from_number'  => env('TWILIO_FROM_NUMBER'),
    ],

    // Phase 16: Public Data Integration Services — Election Candidates & Officials

    'ballotpedia' => [
        'api_key' => env('BALLOTPEDIA_API_KEY'),
    ],

    'opensecrets' => [
        'api_key' => env('OPENSECRETS_API_KEY'),
    ],

    'votesmart' => [
        'api_key' => env('VOTESMART_API_KEY'),
    ],

    'fec' => [
        'api_key' => env('FEC_API_KEY'),
    ],

    'google' => [
        'civic_api_key' => env('GOOGLE_CIVIC_API_KEY'),
    ],

    'congress' => [
        'api_key' => env('CONGRESS_API_KEY'),
        'base_url' => env('CONGRESS_API_BASE_URL', 'https://api.congress.gov/v3'),
    ],

    'idme' => [
        'client_id' => env('IDME_CLIENT_ID'),
        'client_secret' => env('IDME_CLIENT_SECRET'),
        'redirect_uri' => env('IDME_REDIRECT_URI', env('APP_URL') . '/verification/idme/callback'),
        'auth_url' => env('IDME_AUTH_URL', 'https://api.id.me/oauth/authorize'),
        'token_url' => env('IDME_TOKEN_URL', 'https://api.id.me/oauth/token'),
        'attributes_url' => env('IDME_ATTRIBUTES_URL', 'https://api.id.me/api/public/v3/attributes.json'),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('IDME_SCOPES', 'identity,email'))))),
    ],

    // AI Enrichment — optional, used by politicians:enrich-statewide as Tier 3 fallback
    // when Ballotpedia and Wikipedia both fail to extract a current officeholder name.
    // Add ANTHROPIC_API_KEY to GitHub Actions secrets and Railway env vars to enable.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_ENRICH_MODEL', 'claude-haiku-4-5'),
    ],

];
