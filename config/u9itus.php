<?php

use App\Services\Marketing\Channels\EmailChannel;

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
    | Revenue from politician per view:          $1.00
    | Revenue from citizen per view:              $0.60
    | Revenue from citizen ballot-issue per view: $1.00
    | Direct viewer/voter payout:                $0.50
    | Referral commission (10 % of $0.50):       $0.050
    | Politician-procurement commission (10 %):  $0.06 (one-time, amortised)
    |
    | Stripe processing fee (on credit top-ups): 2.5% of gross charge
    | Gross-up formula: gross = credits / (1 - 0.050)
    |
    */
    'revenue_per_view' => env('REVENUE_PER_VIEW', 1.00),
    'citizen_revenue_per_view' => env('CITIZEN_REVENUE_PER_VIEW', 0.60),
    'ballot_issue_revenue_per_view' => env('BALLOT_ISSUE_REVENUE_PER_VIEW', 1.00),
    'viewer_payout_per_view' => env('VIEWER_PAYOUT_PER_VIEW', 0.50),
    'referral_commission_percent' => env('REFERRAL_COMMISSION_PERCENT', 10),
    'procurement_commission_percent' => env('PROCUREMENT_COMMISSION_PERCENT', 10),

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
     * How many days an archived deleted_accounts record (the user_snapshot
     * PII captured by UserDeletionService::archiveAndDelete) is kept before
     * deleted-accounts:purge permanently erases it. After purge, that
     * account can no longer be restored. Runs daily via the scheduler.
     */
    'deleted_account_retention_days' => env('DELETED_ACCOUNT_RETENTION_DAYS', 90),

    /**
     * Batch payout minimum threshold (simplified config key for views/controllers).
     * Falls back to MIN_PAYOUT_AMOUNT so request and processing thresholds stay aligned.
     */
    'batch_payout_min' => env('PAYOUT_THRESHOLD_AMOUNT', env('MIN_PAYOUT_AMOUNT', 5.00)),

    /**
     * Default payment per view if not specified in campaign.
     */
    'default_payment_per_view' => env('DEFAULT_PAYMENT_PER_VIEW', 0.50),

    /**
     * Hide voter-facing Authentic User Verifier action prompts until a specific datetime.
     * Example: AUTHENTIC_USER_VERIFIER_ACTION_HIDE_UNTIL=2026-04-25T23:59:59Z
     */
    'authentic_user_verifier_action_hide_until' => env('AUTHENTIC_USER_VERIFIER_ACTION_HIDE_UNTIL'),

    /*
    |--------------------------------------------------------------------------
    | Video / Live-Feed Constraints
    |--------------------------------------------------------------------------
    |
    | Business rule: political ad videos must be 10–180 seconds.
    | This drives form validation (CreateCampaignRequest), upload hints,
    | and — when ffprobe/getID3 is available — server-side duration checks.
    |
    */
    'max_video_duration' => env('MAX_VIDEO_DURATION', 180),   // 180 seconds (hard cap)
    'min_video_duration' => env('MIN_VIDEO_DURATION', 10),    // 10 seconds minimum
    'max_video_size_mb' => env('MAX_VIDEO_SIZE_MB', 1024),   // 1 GB upload limit
    'allow_live_feed' => env('ALLOW_LIVE_FEED', true),

    /**
     * When true, the voter watch player renders any per-campaign WebVTT subtitle
     * track (campaign.subtitle_url) as a <track kind="captions"> element.
     * Satisfies WCAG 1.2.2 captions accessibility requirement.
     */
    'video_subtitles_enabled' => env('VIDEO_SUBTITLES_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Fraud Prevention
    |--------------------------------------------------------------------------
    */
    'fraud' => [
        'max_views_per_voter_per_day' => env('MAX_VIEWS_PER_VOTER_PER_DAY', 50),
        'device_fingerprint_required' => env('DEVICE_FINGERPRINT_REQUIRED', true),
        'payout_hold_hours' => env('PAYOUT_HOLD_HOURS', 48),
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
        'auto_flag_threshold' => env('FRAUD_AUTO_FLAG_THRESHOLD', 80),
        'ip_reputation_enabled' => env('FRAUD_IP_REPUTATION_ENABLED', true),
        'ipinfo_api_key' => env('IPINFO_API_KEY', ''),
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
        'batch_frequency' => env('PAYOUT_BATCH_FREQUENCY', 'weekly'),
        'threshold_amount' => env('PAYOUT_THRESHOLD_AMOUNT', 5.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories / Governance Levels
    |--------------------------------------------------------------------------
    */
    'governance_levels' => [
        'federal' => 'Federal / National',
        'state' => 'State',
        'county' => 'County',
        'city' => 'City / Municipal',
        'school' => 'School Board',
        'special' => 'Special District',
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
    | Organized by state and alphabetized for easy filtering
    | Includes top 100 cities by population + all 50 state capitals
    */
    'cities_by_state' => [
        'AL' => ['Huntsville', 'Mobile', 'Montgomery'],
        'AK' => ['Anchorage', 'Juneau'],
        'AZ' => ['Chandler', 'Gilbert', 'Glendale', 'Mesa', 'Phoenix', 'Scottsdale', 'Tucson'],
        'AR' => ['Fort Smith', 'Little Rock'],
        'CA' => ['Anaheim', 'Bakersfield', 'Chula Vista', 'Fremont', 'Fresno', 'Irvine', 'Long Beach', 'Los Angeles', 'Oakland', 'Riverside', 'Sacramento', 'San Diego', 'San Francisco', 'San Jose', 'Stockton'],
        'CO' => ['Aurora', 'Colorado Springs', 'Denver'],
        'CT' => ['Hartford'],
        'DE' => ['Dover', 'Wilmington'],
        'FL' => ['Hialeah', 'Jacksonville', 'Miami', 'Orlando', 'Pensacola', 'St. Petersburg', 'Tallahassee', 'Tampa'],
        'GA' => ['Atlanta', 'Savannah'],
        'HI' => ['Honolulu'],
        'ID' => ['Boise', 'Nampa'],
        'IL' => ['Chicago', 'Peoria', 'Rockford', 'Springfield'],
        'IN' => ['Evansville', 'Fort Wayne', 'Indianapolis', 'South Bend'],
        'IA' => ['Cedar Rapids', 'Des Moines'],
        'KS' => ['Overland Park', 'Topeka', 'Wichita'],
        'KY' => ['Bowling Green', 'Frankfort', 'Lexington', 'Louisville'],
        'LA' => ['Baton Rouge', 'Lafayette', 'New Orleans', 'Shreveport'],
        'ME' => ['Augusta', 'Portland'],
        'MD' => ['Annapolis', 'Baltimore'],
        'MA' => ['Boston'],
        'MI' => ['Ann Arbor', 'Detroit', 'Flint', 'Grand Rapids', 'Lansing'],
        'MN' => ['Minneapolis', 'Rochester', 'Saint Paul'],
        'MS' => ['Jackson'],
        'MO' => ['Jefferson City', 'Kansas City', 'Springfield', 'St. Louis'],
        'MT' => ['Billings', 'Helena'],
        'NE' => ['Lincoln', 'Omaha'],
        'NV' => ['Henderson', 'Las Vegas', 'North Las Vegas', 'Reno'],
        'NH' => ['Concord', 'Manchester'],
        'NJ' => ['Atlantic City', 'Jersey City', 'Newark', 'Trenton'],
        'NM' => ['Albuquerque', 'Santa Fe'],
        'NY' => ['Albany', 'Buffalo', 'New York', 'Rochester', 'Syracuse'],
        'NC' => ['Charlotte', 'Durham', 'Greensboro', 'Raleigh', 'Winston-Salem'],
        'ND' => ['Bismarck', 'Fargo'],
        'OH' => ['Akron', 'Cincinnati', 'Cleveland', 'Columbus', 'Dayton', 'Toledo'],
        'OK' => ['Oklahoma City', 'Tulsa'],
        'OR' => ['Eugene', 'Portland', 'Salem'],
        'PA' => ['Harrisburg', 'Philadelphia', 'Pittsburgh'],
        'RI' => ['Providence'],
        'SC' => ['Columbia'],
        'SD' => ['Pierre', 'Sioux Falls'],
        'TN' => ['Chattanooga', 'Knoxville', 'Memphis', 'Nashville'],
        'TX' => ['Amarillo', 'Arlington', 'Austin', 'Corpus Christi', 'Dallas', 'El Paso', 'Fort Worth', 'Garland', 'Houston', 'Irving', 'Laredo', 'Lubbock', 'Plano', 'San Antonio'],
        'UT' => ['Provo', 'Salt Lake City'],
        'VT' => ['Burlington', 'Montpelier'],
        'VA' => ['Chesapeake', 'Norfolk', 'Richmond', 'Virginia Beach'],
        'WA' => ['Olympia', 'Seattle', 'Spokane', 'Tacoma'],
        'WV' => ['Charleston'],
        'WI' => ['Green Bay', 'Madison', 'Milwaukee'],
        'WY' => ['Cheyenne'],
        'DC' => ['Washington'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy flat list of major cities (kept for backward compatibility)
    |--------------------------------------------------------------------------
    | Use cities_by_state for new implementations
    */
    'major_cities' => [
        'Akron, OH', 'Albany, NY', 'Albuquerque, NM', 'Amarillo, TX', 'Anaheim, CA',
        'Anchorage, AK', 'Ann Arbor, MI', 'Annapolis, MD', 'Arlington, TX', 'Atlanta, GA',
        'Atlantic City, NJ', 'Augusta, ME', 'Aurora, CO', 'Austin, TX', 'Bakersfield, CA',
        'Baltimore, MD', 'Baton Rouge, LA', 'Billings, MT', 'Birmingham, AL', 'Bismarck, ND',
        'Boise, ID', 'Boston, MA', 'Bowling Green, KY', 'Buffalo, NY', 'Burlington, VT',
        'Cedar Rapids, IA', 'Chandler, AZ', 'Charleston, WV', 'Charlotte, NC', 'Chattanooga, TN',
        'Chesapeake, VA', 'Cheyenne, WY', 'Chicago, IL', 'Chula Vista, CA', 'Cincinnati, OH',
        'Cleveland, OH', 'Colorado Springs, CO', 'Columbia, SC', 'Columbus, OH', 'Concord, NH',
        'Corpus Christi, TX', 'Dallas, TX', 'Dayton, OH', 'Denver, CO', 'Des Moines, IA',
        'Detroit, MI', 'Dover, DE', 'Durham, NC', 'El Paso, TX', 'Eugene, OR',
        'Evansville, IN', 'Fargo, ND', 'Flint, MI', 'Fort Smith, AR', 'Fort Wayne, IN',
        'Fort Worth, TX', 'Frankfort, KY', 'Fremont, CA', 'Fresno, CA', 'Garland, TX',
        'Gilbert, AZ', 'Glendale, AZ', 'Grand Rapids, MI', 'Green Bay, WI', 'Greensboro, NC',
        'Hartford, CT', 'Helena, MT', 'Henderson, NV', 'Hialeah, FL', 'Honolulu, HI',
        'Houston, TX', 'Huntsville, AL', 'Indianapolis, IN', 'Irvine, CA', 'Irving, TX',
        'Jackson, MS', 'Jacksonville, FL', 'Jefferson City, MO', 'Jersey City, NJ', 'Juneau, AK',
        'Kansas City, MO', 'Knoxville, TN', 'Lafayette, LA', 'Lansing, MI', 'Laredo, TX',
        'Las Vegas, NV', 'Lexington, KY', 'Lincoln, NE', 'Little Rock, AR', 'Long Beach, CA',
        'Los Angeles, CA', 'Louisville, KY', 'Lubbock, TX', 'Madison, WI', 'Manchester, NH',
        'Memphis, TN', 'Mesa, AZ', 'Miami, FL', 'Milwaukee, WI', 'Minneapolis, MN',
        'Mobile, AL', 'Montgomery, AL', 'Montpelier, VT', 'Nampa, ID', 'Nashville, TN',
        'New Orleans, LA', 'New York, NY', 'Newark, NJ', 'Norfolk, VA', 'North Las Vegas, NV',
        'Oakland, CA', 'Oklahoma City, OK', 'Olympia, WA', 'Omaha, NE', 'Orlando, FL',
        'Overland Park, KS', 'Pensacola, FL', 'Peoria, IL', 'Philadelphia, PA', 'Phoenix, AZ',
        'Pierre, SD', 'Pittsburgh, PA', 'Plano, TX', 'Portland, ME', 'Portland, OR',
        'Providence, RI', 'Provo, UT', 'Raleigh, NC', 'Reno, NV', 'Richmond, VA',
        'Riverside, CA', 'Rochester, MN', 'Rochester, NY', 'Rockford, IL', 'Sacramento, CA',
        'Saint Paul, MN', 'Salem, OR', 'Salt Lake City, UT', 'San Antonio, TX', 'San Diego, CA',
        'San Francisco, CA', 'San Jose, CA', 'Santa Fe, NM', 'Savannah, GA', 'Scottsdale, AZ',
        'Seattle, WA', 'Shreveport, LA', 'Sioux Falls, SD', 'South Bend, IN', 'Spokane, WA',
        'Springfield, IL', 'Springfield, MO', 'St. Louis, MO', 'St. Petersburg, FL', 'Stockton, CA',
        'Syracuse, NY', 'Tacoma, WA', 'Tallahassee, FL', 'Tampa, FL', 'Toledo, OH',
        'Topeka, KS', 'Trenton, NJ', 'Tucson, AZ', 'Tulsa, OK', 'Virginia Beach, VA',
        'Washington, DC', 'Wichita, KS', 'Wilmington, DE', 'Winston-Salem, NC',
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

    /*
    |--------------------------------------------------------------------------
    | Public Q&A Board Settings
    |--------------------------------------------------------------------------
    */
    'q_and_a' => [
        'use_public_board_heading' => env('QA_USE_PUBLIC_BOARD_HEADING', false),
        'legacy_heading_label' => 'Answered Questions',
        'public_heading_label' => 'Public Q&A Board',
        'rate_limit' => [
            'max_attempts' => env('QA_RATE_LIMIT_MAX_ATTEMPTS', 5),
            'decay_seconds' => env('QA_RATE_LIMIT_DECAY_SECONDS', 600),
        ],
        'moderation' => [
            'blocked_terms' => [
                'kill',
                'bomb',
                'terrorist',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Viral Moment Score
    |--------------------------------------------------------------------------
    |
    | Tunables for the "Recent media moment" feature (feat/viral-moment-score).
    | App\Services\MomentScoreService computes a per-clip score from the raw
    | engagement components; these values control eligibility + the recency
    | decay curve so the formula can be retuned without a code deploy.
    |
    */
    'moments' => [
        // Only clips published within this window are eligible to be "featured".
        'eligibility_window_days' => env('MOMENT_ELIGIBILITY_WINDOW_DAYS', 30),

        // exp(-age_days / half_life) — larger = slower decay, older clips stay relevant longer.
        'recency_decay_half_life_days' => env('MOMENT_RECENCY_HALF_LIFE_DAYS', 30),

        // Clips below this view count are stored but never scored/featured (noise floor).
        'min_view_count' => env('MOMENT_MIN_VIEW_COUNT', 1000),

        // How many ranked moments to keep per politician (older/lower-score clips pruned).
        'max_per_politician' => env('MOMENT_MAX_PER_POLITICIAN', 10),

        // Per-source authority weight — C-SPAN (official floor footage) ranks above
        // a random YouTube re-upload, which ranks above social re-shares.
        'authority_weights' => [
            'cspan' => 1.00,
            'news' => 0.80,
            'youtube' => 0.60,
            'tiktok' => 0.50,
            'instagram' => 0.50,
            'x' => 0.50,
        ],

        // C-SPAN scraper — drives a Playwright browser (scripts/scrape-cspan.js)
        // because c-span.org renders search results client-side and blocks plain
        // HTTP fetches. Mirrors the OpenSecrets Playwright scraper pattern. C-SPAN
        // exposes no view counts, so clips score 0 and surface in the list by
        // recency rather than featuring (the YouTube-driven clip stays featured).
        'cspan' => [
            'enabled' => env('CSPAN_MOMENTS_ENABLED', true),
            'base_url' => env('CSPAN_BASE_URL', 'https://www.c-span.org'),
            'cache_minutes' => env('CSPAN_CACHE_MINUTES', 1440),
            'max_clips' => env('CSPAN_MAX_CLIPS', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign website discovery
    |--------------------------------------------------------------------------
    | Drives App\Services\CampaignWebsiteDiscoveryService + the
    | politicians:discover-websites command, which finds an official campaign
    | site for candidates missing politicians.website_url by scraping their
    | Ballotpedia page (same plain-HTTP approach as SyncPrimaryResults — no
    | Playwright needed for Ballotpedia). A discovered URL is picked up on the
    | next politicians:enrich-profiles run.
    */
    'website_discovery' => [
        'cache_hours' => env('WEBSITE_DISCOVERY_CACHE_HOURS', 168),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issues: Inferred discourse badges
    |--------------------------------------------------------------------------
    | Drives the issue/discourse-badge system that labels politicians with the
    | topics they publicly talk about (App\Services\PoliticianTopicSignalService
    | + IssueClassifierService + BadgeService::grantInferredBadges). Inputs are
    | verified news articles (stored topic_key), viral-moment clip titles, and
    | Vote Smart NPAT issue positions. When a politician×topic total_score
    | crosses `signal_threshold`, an `inferred_discourse` profile badge is
    | granted. Runs nightly via politicians:enrich-issue-badges.
    */
    'issues' => [
        // Master switch. When false, the enricher command exits early and the
        // LLM classifier reports unconfigured (keyword tier still works for
        // rollups that already have stored topic_keys).
        'enabled' => env('ISSUE_BADGES_ENABLED', true),

        // Allow the Claude LLM fallback when the keyword tier is unconfident.
        // Disable for a keyword-only deployment (no ANTHROPIC_API_KEY spend).
        'llm_fallback' => env('ISSUE_LLM_FALLBACK', true),

        // Override the Anthropic model for issue classification; null falls
        // back to services.anthropic.model (claude-haiku-4-5 by default).
        'llm_model' => env('ISSUE_LLM_MODEL', null),

        // Only evidence (news/moments) within this lookback window contributes.
        'recency_window_days' => env('ISSUE_RECENCY_WINDOW_DAYS', 90),

        // exp(-age_days / half_life) per-mention decay — matches the
        // MomentScoreService recency shape so newer discourse dominates.
        'recency_half_life_days' => env('ISSUE_RECENCY_HALF_LIFE_DAYS', 60),

        // Min weighted total_score for a politician×topic to earn the badge.
        'signal_threshold' => env('ISSUE_SIGNAL_THRESHOLD', 1.0),

        // Per-source multipliers on the aggregated (confidence × recency) sum.
        // Vote Smart NPAT positions are self-stated stances → heaviest; a
        // viral clip signals active public discourse → next; news is broadest.
        'source_weights' => [
            'news' => 1.0,
            'viral_moment' => 1.2,
            'votesmart' => 1.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketing: Plugin Marketplace Spine
    |--------------------------------------------------------------------------
    | Feature flags + first-party channel map for the MarketingChannel plugin
    | system (App\Contracts\MarketingChannel). The `enabled` master switch
    | gates the whole marketing dispatch path; the first-party channel map
    | lists the in-repo plugin classes that the marketing_channels seeder row's
    | provider_class points at. Third-party (marketplace) channels register the
    | same contract but are added through the admin approval queue
    | (ChannelStatus::Pending → Active).
    */
    'marketing' => [
        'enabled' => env('MARKETING_ENABLED', true),

        // First-party channels shipped with the platform. Keyed by the
        // marketing_channels.key slug that the seeder inserts.
        'first_party_channels' => [
            'email' => EmailChannel::class,
        ],

        // Marketing content agent — auto-drafts blog Posts from a politician's
        // recent news / viral moments (App\Services\Marketing\PostDraftingService).
        // Drafts are saved as PendingApproval and require a human to publish via
        // the existing Post editor; nothing is auto-published. Defaults to OFF.
        'drafting' => [
            'enabled' => env('MARKETING_DRAFTING_ENABLED', false),
            // Override the Anthropic model for drafting; null falls back to services.anthropic.model.
            'model' => env('MARKETING_DRAFTING_MODEL', null),
            // Min moment_score for a viral moment to be drafted (0 = any eligible clip).
            'moment_score_threshold' => env('MARKETING_MOMENT_SCORE_THRESHOLD', 0.0),
            // How many recent verified news articles to consider per politician.
            'news_recency_limit' => 60,
            // Eligibility window (days) for viral moments via scopeEligible().
            'viral_eligible_days' => 30,

            // Daily admin digest of new PendingApproval drafts. Recipients:
            // a comma-separated email list here, else all admin users (User::admins()).
            'digest_enabled' => env('MARKETING_DRAFTS_DIGEST_ENABLED', true),
            'digest_recipients' => env('MARKETING_DRAFTS_DIGEST_RECIPIENTS', null),
        ],
    ],
];
