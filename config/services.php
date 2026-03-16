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
    ],

    'paypal' => [
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        // true = sandbox (api-m.sandbox.paypal.com), false = live (api-m.paypal.com)
        'sandbox'       => env('PAYPAL_SANDBOX', true),
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

];
