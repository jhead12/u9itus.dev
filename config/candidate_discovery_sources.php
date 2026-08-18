<?php

/**
 * Candidate Discovery Sources Configuration
 *
 * Drives RssCandidateDiscoverySource: Google News RSS searches tuned to surface
 * *new* Senate/Governor/House candidate signals (announcements, filings,
 * primary wins), as opposed to config/news_sources.php, which searches for
 * coverage of a candidate whose name is already known.
 *
 * Senate/Governor queries run once per state per office, with {STATE_NAME}
 * and {OFFICE} substituted in. House is per-district instead (a state-name-only
 * query can't tell districts apart), using house_query_templates against every
 * district RssCandidateDiscoverySource already knows about from existing
 * Politician rows — see house_batch_divisor below for why not every district
 * is queried every run. State names come from config('u9itus.us_states')
 * rather than duplicating that map here.
 */

return [

    'rss_url' => 'https://news.google.com/rss/search?q={QUERY}&hl=en-US&gl=US&ceid=US:en',

    /*
    |--------------------------------------------------------------------------
    | Offices covered
    |--------------------------------------------------------------------------
    | Key = political_office value written onto the resulting CandidateLead's
    | office_hint; value = short slug used in provider ids. 'house' is handled
    | separately (per-district) by RssCandidateDiscoverySource — see
    | house_query_templates below.
    */
    'offices' => [
        'U.S. Senator' => 'senate',
        'Governor' => 'governor',
        'U.S. Representative' => 'house',
    ],

    /*
    |--------------------------------------------------------------------------
    | Statewide query templates (Senate, Governor)
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

    /*
    |--------------------------------------------------------------------------
    | House (per-district) query templates
    |--------------------------------------------------------------------------
    | {STATE_NAME}, {DISTRICT_ORDINAL} (e.g. "12th"), and {DISTRICT_CODE}
    | (e.g. "CA-12") are substituted before {QUERY} is URL-encoded into
    | rss_url above. Kept to two templates (vs. three statewide) since this
    | already runs 435 districts wide — see house_batch_divisor.
    */
    'house_query_templates' => [
        'files_for_office' => '"{STATE_NAME}\'s {DISTRICT_ORDINAL} Congressional District" candidate files OR announces campaign',
        'wins_primary' => '"{DISTRICT_CODE}" congressional candidate wins primary',
    ],

    /*
    |--------------------------------------------------------------------------
    | House batching
    |--------------------------------------------------------------------------
    | Querying all ~435 districts every run (vs. ~50-68 statewide requests for
    | Senate+Governor) risks Google News rate limits and mostly returns
    | nothing for low-coverage districts anyway. Each run instead covers a
    | rotating 1/N slice of districts, keyed off day-of-year, so the full set
    | cycles through roughly every N days rather than none of them, always.
    */
    'house_batch_divisor' => 7,
];
