<?php

return [
    /*
    |--------------------------------------------------------------------------
    | U9itus – Political Loyalty Ads Configuration
    |--------------------------------------------------------------------------
    |
    | This app connects politicians and local governance officials directly
    | with potential voters through paid video/live-feed messages.
    | Viewers earn money for watching the full political message.
    |
    */

    /**
     * Head Enterprises platform fee percentage charged on each campaign.
     */
    'head_enterprises_fee_percent' => env('HEAD_ENTERPRISES_FEE_PERCENT', 15.0),

    /*
    |--------------------------------------------------------------------------
    | Pay-Per-View Pricing (per single view)
    |--------------------------------------------------------------------------
    |
    | Revenue from politician per view:          $0.60
    | Direct viewer/voter payout:                $0.25
    | Referral commission (10 % of $0.25):       $0.025
    | Politician-procurement commission (10 %):  $0.06 (one-time, amortised)
    |
    */
    'revenue_per_view'              => env('REVENUE_PER_VIEW', 0.60),
    'viewer_payout_per_view'        => env('VIEWER_PAYOUT_PER_VIEW', 0.25),
    'referral_commission_percent'   => env('REFERRAL_COMMISSION_PERCENT', 10),
    'procurement_commission_percent'=> env('PROCUREMENT_COMMISSION_PERCENT', 10),

    /**
     * Number of hours before a viewing assignment expires.
     */
    'assignment_expiry_hours' => env('ASSIGNMENT_EXPIRY_HOURS', 24),

    /**
     * Minimum watch-time percentage required for payment (must watch full message).
     */
    'min_watch_time_percent' => env('MIN_WATCH_TIME_PERCENT', 100),

    /**
     * Minimum payout amount to process voter/viewer payments.
     */
    'min_payout_amount' => env('MIN_PAYOUT_AMOUNT', 5.00),

    /**
     * Default payment per view if not specified in campaign.
     */
    'default_payment_per_view' => env('DEFAULT_PAYMENT_PER_VIEW', 0.25),

    /*
    |--------------------------------------------------------------------------
    | Video / Live-Feed Constraints
    |--------------------------------------------------------------------------
    |
    | Business rule: political ad videos must be 10–20 seconds (strict).
    | This drives form validation (CreateCampaignRequest), upload hints,
    | and — when ffprobe/getID3 is available — server-side duration checks.
    |
    */
    'max_video_duration'  => env('MAX_VIDEO_DURATION', 20),    // 20 seconds (hard cap)
    'min_video_duration'  => env('MIN_VIDEO_DURATION', 10),    // 10 seconds minimum
    'max_video_size_mb'   => env('MAX_VIDEO_SIZE_MB', 100),    // 100 MB upload limit
    'allow_live_feed'     => env('ALLOW_LIVE_FEED', true),

    /*
    |--------------------------------------------------------------------------
    | Fraud Prevention
    |--------------------------------------------------------------------------
    */
    'fraud' => [
        'max_views_per_voter_per_day'   => env('MAX_VIEWS_PER_VOTER_PER_DAY', 50),
        'device_fingerprint_required'   => env('DEVICE_FINGERPRINT_REQUIRED', true),
        'payout_hold_hours'             => env('PAYOUT_HOLD_HOURS', 48),
        'suspicious_activity_threshold' => env('SUSPICIOUS_ACTIVITY_THRESHOLD', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Payout Settings
    |--------------------------------------------------------------------------
    |
    | Batch payouts reduce per-transaction fees. Payouts are grouped and
    | processed on a schedule (e.g. weekly or when threshold is met).
    |
    */
    'payout' => [
        'batch_frequency'  => env('PAYOUT_BATCH_FREQUENCY', 'weekly'),
        'threshold_amount' => env('PAYOUT_THRESHOLD_AMOUNT', 10.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories / Governance Levels
    |--------------------------------------------------------------------------
    */
    'governance_levels' => [
        'federal'     => 'Federal / National',
        'state'       => 'State',
        'county'      => 'County',
        'city'        => 'City / Municipal',
        'school'      => 'School Board',
        'special'     => 'Special District',
    ],

    /*
    |--------------------------------------------------------------------------
    | US States
    |--------------------------------------------------------------------------
    */
    'us_states' => [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Political Offices
    |--------------------------------------------------------------------------
    */
    'political_offices' => [
        'mayor',
        'city_council',
        'county_commissioner',
        'state_representative',
        'state_senator',
        'governor',
        'us_representative',
        'us_senator',
        'school_board',
        'district_attorney',
        'sheriff',
        'judge',
        'other',
    ],
];
