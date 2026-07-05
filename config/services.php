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
        'domain'       => env('MAILGUN_DOMAIN'),
        'secret'       => env('MAILGUN_SECRET'),
        'endpoint'     => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'       => 'https',
        'mailing_list' => env('MAILGUN_MAILING_LIST_ADDRESS'), // e.g. waitlist@mg.yourdomain.com
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

    // Early-bank.com — sibling Railway service. Talks to u9itus over private
    // network. See app/Http/Middleware/EarlyBankApiAuth.php and
    // app/Services/EarlyBankWebhookService.php.
    'earlybank' => [
        // Public-facing URL of the Early-bank site (used in Blade links / CTAs).
        // Set EARLYBANK_PUBLIC_URL on Railway once DNS is live.
        'public_url'     => env('EARLYBANK_PUBLIC_URL', 'https://www.early-bank.com'),

        // Bearer token presented by Early-bank when calling /api/v1/earlybank/*.
        // Must match U9ITUS_API_TOKEN on the earlybank service.
        'api_token'      => env('EARLYBANK_API_TOKEN'),

        // Outbound webhook destination (Early-bank's u9itus webhook receiver).
        // Use Railway private URL in production.
        'webhook_url'    => env('EARLYBANK_WEBHOOK_URL'),

        // Shared secret used to sign outbound webhook payloads (HMAC-SHA256).
        'webhook_secret' => env('EARLYBANK_WEBHOOK_SECRET'),

        // Master switch — when false, no outbound webhooks fire and the API
        // group still authenticates but is effectively dormant. Useful while
        // Early-bank.com is being deployed.
        'enabled'        => env('EARLYBANK_ENABLED', false),
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

        // Google Analytics 4 measurement ID (e.g. "G-XXXXXXXXXX").
        // Falls back to the `google_analytics_id` platform setting at runtime.
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),

        // Google Tag Manager container ID (e.g. "GTM-XXXXXXX"). Optional.
        // Falls back to the `google_tag_manager_id` platform setting at runtime.
        'tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),

        // Google Ads conversion ID (e.g. "AW-123456789"). Optional.
        // Falls back to the `google_ads_conversion_id` platform setting at runtime.
        'ads_conversion_id' => env('GOOGLE_ADS_CONVERSION_ID'),
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

    // Sprint 7 — MeToken subgraph (Goldsky public endpoint) for read-only
    // on-chain loyalty stats on eligible politician profiles.
    // Kill-switched by the `web3_features_enabled` platform setting.
    'metokens' => [
        'subgraph_url' => env(
            'METOKENS_SUBGRAPH_URL',
            'https://api.goldsky.com/api/public/project_cmh0iv6s500dbw2p22vsxcfo6/subgraphs/metokens/1.0.2/gn'
        ),
        // Cache TTL in seconds; defaults to 24 h. Admin-only ?refresh=1 on
        // the politician public profile can bust this on demand.
        'cache_ttl' => (int) env('METOKENS_SUBGRAPH_CACHE_TTL', 86400),
        // Basescan token URL prefix — user-facing verification link.
        'basescan_prefix' => env('METOKENS_BASESCAN_PREFIX', 'https://basescan.org/token/'),
    ],

];
