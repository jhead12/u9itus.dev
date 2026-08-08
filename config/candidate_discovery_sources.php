<?php

/**
 * Candidate Discovery Sources Configuration
 *
 * Drives RssCandidateDiscoverySource: Google News RSS searches tuned to surface
 * *new* Senate/Governor candidate signals (announcements, filings, primary wins),
 * as opposed to config/news_sources.php, which searches for coverage of a
 * candidate whose name is already known.
 *
 * Each query template is run once per state per office, with {STATE_NAME} and
 * {OFFICE} substituted in, against the shared {QUERY}-templated rss_url below.
 * State names come from config('u9itus.us_states') rather than duplicating that
 * map here.
 */

return [

    'rss_url' => 'https://news.google.com/rss/search?q={QUERY}&hl=en-US&gl=US&ceid=US:en',

    /*
    |--------------------------------------------------------------------------
    | Offices covered
    |--------------------------------------------------------------------------
    | Key = political_office value written onto the resulting CandidateLead's
    | office_hint; value = short slug used in provider ids.
    */
    'offices' => [
        'U.S. Senator' => 'senate',
        'Governor' => 'governor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query templates
    |--------------------------------------------------------------------------
    | {STATE_NAME} and {OFFICE} are substituted before {QUERY} is URL-encoded
    | into rss_url above. Multiple phrasings widen recall since candidacy
    | announcements aren't worded consistently across outlets.
    */
    'query_templates' => [
        'files_for_office' => '"{STATE_NAME}" "{OFFICE}" candidate files OR announces campaign',
        'wins_primary' => '"{STATE_NAME}" "{OFFICE}" wins primary',
        'launches_campaign' => '"{STATE_NAME}" launches campaign for {OFFICE}',
    ],
];
