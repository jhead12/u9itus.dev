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

    /**
     * Stripe card processing fee (%) applied on every credit top-up.
     * The politician is charged a gross-up amount so the platform always
     * receives the full credit value requested:
     *   gross_charge = requested_credits / (1 - stripe_fee_percent / 100)
     */
    'stripe_fee_percent' => env('STRIPE_FEE_PERCENT', 2.5),

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
    | Stripe processing fee (on credit top-ups): 2.5% of gross charge
    | Gross-up formula: gross = credits / (1 - 0.025)
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
     * Batch payout minimum threshold (simplified config key for views/controllers).
     */
    'batch_payout_min' => env('PAYOUT_THRESHOLD_AMOUNT', 5.00),

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

        /*
         * Phase 8 — Advanced Fraud Prevention
         *
         * auto_flag_threshold  — cumulative fraud score at which the voter is
         *                        automatically flagged without admin intervention.
         * ip_reputation_enabled — enable VPN / Tor / datacenter IP detection.
         * ipinfo_api_key        — optional ipinfo.io API key for enriched signals.
         *                        Leave empty to rely on the built-in CIDR blocklist.
         */
        'auto_flag_threshold'    => env('FRAUD_AUTO_FLAG_THRESHOLD', 80),
        'ip_reputation_enabled'  => env('FRAUD_IP_REPUTATION_ENABLED', true),
        'ipinfo_api_key'         => env('IPINFO_API_KEY', ''),
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
        'threshold_amount' => env('PAYOUT_THRESHOLD_AMOUNT', 5.00),
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
    | Major US Cities (for campaign targeting)
    |--------------------------------------------------------------------------
    | Includes top 100 cities by population + all 50 state capitals
    */
    'major_cities' => [
        // Top 100 US Cities by Population
        'New York, NY',
        'Los Angeles, CA',
        'Chicago, IL',
        'Houston, TX',
        'Phoenix, AZ',
        'Philadelphia, PA',
        'San Antonio, TX',
        'San Diego, CA',
        'Dallas, TX',
        'San Jose, CA',
        'Austin, TX',
        'Jacksonville, FL',
        'Fort Worth, TX',
        'Columbus, OH',
        'Charlotte, NC',
        'San Francisco, CA',
        'Indianapolis, IN',
        'Seattle, WA',
        'Denver, CO',
        'Washington, DC',
        'Boston, MA',
        'El Paso, TX',
        'Nashville, TN',
        'Detroit, MI',
        'Oklahoma City, OK',
        'Portland, OR',
        'Las Vegas, NV',
        'Memphis, TN',
        'Louisville, KY',
        'Baltimore, MD',
        'Milwaukee, WI',
        'Albuquerque, NM',
        'Tucson, AZ',
        'Fresno, CA',
        'Mesa, AZ',
        'Sacramento, CA',
        'Atlanta, GA',
        'Kansas City, MO',
        'Colorado Springs, CO',
        'Raleigh, NC',
        'Miami, FL',
        'Omaha, NE',
        'Long Beach, CA',
        'Virginia Beach, VA',
        'Oakland, CA',
        'Minneapolis, MN',
        'Tulsa, OK',
        'Tampa, FL',
        'Arlington, TX',
        'New Orleans, LA',
        'Wichita, KS',
        'Cleveland, OH',
        'Aurora, CO',
        'Bakersfield, CA',
        'Anaheim, CA',
        'Honolulu, HI',
        'Riverside, CA',
        'Corpus Christi, TX',
        'Lexington, KY',
        'Henderson, NV',
        'Stockton, CA',
        'Saint Paul, MN',
        'St. Louis, MO',
        'Cincinnati, OH',
        'Pittsburgh, PA',
        'Greensboro, NC',
        'Anchorage, AK',
        'Plano, TX',
        'Lincoln, NE',
        'Orlando, FL',
        'Irvine, CA',
        'Newark, NJ',
        'Durham, NC',
        'Chula Vista, CA',
        'Toledo, OH',
        'Fort Wayne, IN',
        'St. Petersburg, FL',
        'Laredo, TX',
        'Jersey City, NJ',
        'Chandler, AZ',
        'Madison, WI',
        'Lubbock, TX',
        'Scottsdale, AZ',
        'Reno, NV',
        'Buffalo, NY',
        'Gilbert, AZ',
        'Glendale, AZ',
        'North Las Vegas, NV',
        'Winston-Salem, NC',
        'Chesapeake, VA',
        'Norfolk, VA',
        'Fremont, CA',
        'Garland, TX',
        'Irving, TX',
        'Hialeah, FL',
        'Richmond, VA',
        'Boise, ID',
        'Spokane, WA',
        'Baton Rouge, LA',
        
        // State Capitals (not already listed above)
        'Montgomery, AL',
        'Juneau, AK',
        'Little Rock, AR',
        'Hartford, CT',
        'Dover, DE',
        'Tallahassee, FL',
        'Atlanta, GA',
        'Des Moines, IA',
        'Springfield, IL',
        'Topeka, KS',
        'Frankfort, KY',
        'Augusta, ME',
        'Annapolis, MD',
        'Lansing, MI',
        'Saint Paul, MN',
        'Jackson, MS',
        'Jefferson City, MO',
        'Helena, MT',
        'Concord, NH',
        'Trenton, NJ',
        'Santa Fe, NM',
        'Albany, NY',
        'Bismarck, ND',
        'Salem, OR',
        'Harrisburg, PA',
        'Providence, RI',
        'Columbia, SC',
        'Pierre, SD',
        'Salt Lake City, UT',
        'Montpelier, VT',
        'Olympia, WA',
        'Charleston, WV',
        'Cheyenne, WY',
        
        // Additional Major Regional Cities
        'Shreveport, LA',
        'Mobile, AL',
        'Huntsville, AL',
        'Little Rock, AR',
        'Fort Smith, AR',
        'Wilmington, DE',
        'Pensacola, FL',
        'Savannah, GA',
        'Cedar Rapids, IA',
        'Nampa, ID',
        'Rockford, IL',
        'Peoria, IL',
        'Evansville, IN',
        'South Bend, IN',
        'Overland Park, KS',
        'Bowling Green, KY',
        'Lafayette, LA',
        'Portland, ME',
        'Ann Arbor, MI',
        'Grand Rapids, MI',
        'Flint, MI',
        'Rochester, MN',
        'Springfield, MO',
        'Billings, MT',
        'Fargo, ND',
        'Manchester, NH',
        'Atlantic City, NJ',
        'Albuquerque, NM',
        'Rochester, NY',
        'Syracuse, NY',
        'Akron, OH',
        'Dayton, OH',
        'Eugene, OR',
        'Tacoma, WA',
        'Green Bay, WI',
        'Sioux Falls, SD',
        'Knoxville, TN',
        'Chattanooga, TN',
        'Amarillo, TX',
        'Provo, UT',
        'Burlington, VT',
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
