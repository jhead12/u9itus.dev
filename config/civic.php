<?php

/**
 * Civic Source Registry configuration.
 *
 * Backs the election_data_sources pipeline — see doc/CIVIC_SOURCE_REGISTRY.md.
 * State-name lookups live in config('u9itus.us_states'); only registry-specific
 * data is kept here.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Curated statewide elections landing pages
    |--------------------------------------------------------------------------
    | Seed hints for election_data_sources.elections_home_url on `state` rows.
    | Only states we're confident of are listed; the rest are resolved by
    | civic:resolve-official-urls and confirmed by civic:verify-sources.
    | Source of truth: NASS "Can I Vote" directory — https://www.nass.org/can-i-vote
    | Treat every entry as a hint, not verified truth.
    */
    'state_election_sites' => [
        'AZ' => 'https://azsos.gov/elections',
        'CA' => 'https://www.sos.ca.gov/elections',
        'CO' => 'https://www.sos.state.co.us/pubs/elections/main.html',
        'FL' => 'https://dos.fl.gov/elections/',
        'GA' => 'https://sos.ga.gov/elections-division-georgia-secretary-states-office',
        'MI' => 'https://www.michigan.gov/sos/elections',
        'NC' => 'https://www.ncsbe.gov/',
        'NY' => 'https://www.elections.ny.gov/',
        'OH' => 'https://www.ohiosos.gov/elections/',
        'PA' => 'https://www.vote.pa.gov/',
        'TX' => 'https://www.sos.state.tx.us/elections/',
        'VA' => 'https://www.elections.virginia.gov/',
        'WA' => 'https://www.sos.wa.gov/elections',
    ],

    /*
    |--------------------------------------------------------------------------
    | State capital cities
    |--------------------------------------------------------------------------
    | Used to build a representative address ("Montgomery, AL") to hand Google
    | Civic voterInfoQuery when resolving a `state` row's election-admin body.
    | County rows use "<jurisdiction_name>, <ST>" instead.
    */
    'state_capitals' => [
        'AL' => 'Montgomery', 'AK' => 'Juneau', 'AZ' => 'Phoenix', 'AR' => 'Little Rock',
        'CA' => 'Sacramento', 'CO' => 'Denver', 'CT' => 'Hartford', 'DE' => 'Dover',
        'FL' => 'Tallahassee', 'GA' => 'Atlanta', 'HI' => 'Honolulu', 'ID' => 'Boise',
        'IL' => 'Springfield', 'IN' => 'Indianapolis', 'IA' => 'Des Moines', 'KS' => 'Topeka',
        'KY' => 'Frankfort', 'LA' => 'Baton Rouge', 'ME' => 'Augusta', 'MD' => 'Annapolis',
        'MA' => 'Boston', 'MI' => 'Lansing', 'MN' => 'Saint Paul', 'MS' => 'Jackson',
        'MO' => 'Jefferson City', 'MT' => 'Helena', 'NE' => 'Lincoln', 'NV' => 'Carson City',
        'NH' => 'Concord', 'NJ' => 'Trenton', 'NM' => 'Santa Fe', 'NY' => 'Albany',
        'NC' => 'Raleigh', 'ND' => 'Bismarck', 'OH' => 'Columbus', 'OK' => 'Oklahoma City',
        'OR' => 'Salem', 'PA' => 'Harrisburg', 'RI' => 'Providence', 'SC' => 'Columbia',
        'SD' => 'Pierre', 'TN' => 'Nashville', 'TX' => 'Austin', 'UT' => 'Salt Lake City',
        'VT' => 'Montpelier', 'VA' => 'Richmond', 'WA' => 'Olympia', 'WV' => 'Charleston',
        'WI' => 'Madison', 'WY' => 'Cheyenne', 'DC' => 'Washington',
    ],

    /*
    |--------------------------------------------------------------------------
    | Known election-tech vendors
    |--------------------------------------------------------------------------
    | Hostname substring => vendor slug written to election_data_sources.vendor
    | (and, later, the scraper adapter key in platform_template). Once an
    | adapter handles one vendor site it handles the whole family, so this
    | mapping is where scraper ROI comes from. Extend as new hosts turn up.
    */
    'vendor_hosts' => [
        'voteinfo.net' => 'voteinfo_net',
        'sccnvote.com' => 'voteinfo_net',       // same platform, per-county alias
        'enhancedvoting.com' => 'enhanced_voting',
        'ballottrax.com' => 'ballottrax',
        'ballotscout.org' => 'ballot_scout',
        'democracylive.com' => 'democracy_live',
        'clarityelections.com' => 'clarity',    // Scytl/Clarity results
        'electionreporting.com' => 'election_reporting',
        'enr.clarityelections' => 'clarity',
        'sos.gov' => null,                      // generic gov — leave vendor null
    ],
];
