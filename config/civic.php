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
    | Source verifier (civic:verify-sources)
    |--------------------------------------------------------------------------
    | HTTP identity + defaults used when health-checking the URLs in
    | election_data_sources and reading each host's robots.txt.
    */
    'verifier' => [
        'user_agent' => env('CIVIC_VERIFIER_UA', 'U9itus-civic-registry/1.0 (+https://u9itus.dev/about)'),
        'timeout' => (int) env('CIVIC_VERIFIER_TIMEOUT', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statewide elections landing pages (Secretary of State / State Board of
    | Elections). Seed hints for election_data_sources.elections_home_url on
    | `state` rows — civic:verify-sources HEAD-checks each and --rewrite-redirects
    | fixes any that moved. A `blocked` result is usually a bot wall, not a dead
    | page. NASS "Can I Vote" directory is the reference: https://www.nass.org/can-i-vote
    */
    'state_election_sites' => [
        'AL' => 'https://www.sos.alabama.gov/alabama-votes',
        'AK' => 'https://www.elections.alaska.gov/',
        'AZ' => 'https://azsos.gov/elections',
        'AR' => 'https://www.sos.arkansas.gov/elections',
        'CA' => 'https://www.sos.ca.gov/elections',
        'CO' => 'https://www.sos.state.co.us/pubs/elections/main.html',
        'CT' => 'https://portal.ct.gov/sots/elections',
        'DE' => 'https://elections.delaware.gov/',
        'DC' => 'https://dcboe.org/',
        'FL' => 'https://dos.fl.gov/elections/',
        'GA' => 'https://sos.ga.gov/elections-division-georgia-secretary-states-office',
        'HI' => 'https://elections.hawaii.gov/',
        'ID' => 'https://voteidaho.gov/',
        'IL' => 'https://www.elections.il.gov/',
        'IN' => 'https://www.in.gov/sos/elections/',
        'IA' => 'https://sos.iowa.gov/',
        'KS' => 'https://sos.ks.gov/elections/elections.html',
        'KY' => 'https://www.sos.ky.gov/elections/',
        'LA' => 'https://www.sos.la.gov/',
        'ME' => 'https://www.maine.gov/sos/elections-voting',
        'MD' => 'https://elections.maryland.gov/',
        'MA' => 'https://www.sec.state.ma.us/divisions/elections/elections-and-voting.htm',
        'MI' => 'https://www.michigan.gov/sos/elections',
        'MN' => 'https://www.sos.state.mn.us/elections-voting/',
        'MS' => 'https://www.sos.ms.gov/elections-voting',
        'MO' => 'https://www.sos.mo.gov/elections',
        'MT' => 'https://sosmt.gov/elections/',
        'NE' => 'https://sos.nebraska.gov/elections',
        'NV' => 'https://www.nvsos.gov/sos/elections',
        'NH' => 'https://www.sos.nh.gov/elections',
        'NJ' => 'https://www.nj.gov/state/elections/',
        'NM' => 'https://www.sos.nm.gov/voting-and-elections/',
        'NY' => 'https://www.elections.ny.gov/',
        'NC' => 'https://www.ncsbe.gov/',
        'ND' => 'https://www.sos.nd.gov/elections',
        'OH' => 'https://www.ohiosos.gov/elections/',
        'OK' => 'https://oklahoma.gov/elections.html',
        'OR' => 'https://sos.oregon.gov/elections/pages/default.aspx',
        'PA' => 'https://www.pa.gov/agencies/vote',
        'RI' => 'https://elections.ri.gov/',
        'SC' => 'https://scvotes.gov/',
        'SD' => 'https://sdsos.gov/elections-voting/',
        'TN' => 'https://sos.tn.gov/elections',
        'TX' => 'https://www.sos.state.tx.us/elections/',
        'UT' => 'https://vote.utah.gov/',
        'VT' => 'https://sos.vermont.gov/elections/',
        'VA' => 'https://www.elections.virginia.gov/',
        'WA' => 'https://www.sos.wa.gov/elections',
        'WV' => 'https://sos.wv.gov/elections/',
        'WI' => 'https://elections.wi.gov/',
        'WY' => 'https://sos.wyo.gov/Elections/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wikipedia ballot-measure adapter (WikipediaBallotMeasuresAdapter)
    |--------------------------------------------------------------------------
    | Parses the per-state tables in "<year> United States ballot measures".
    | One page covers every state; the "Description" column fills yes_meaning.
    */
    'wikipedia' => [
        'year' => (int) env('CIVIC_WIKIPEDIA_YEAR', 2026),
        'article' => env('CIVIC_WIKIPEDIA_ARTICLE'), // null => "<year> United States ballot measures"
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

    /*
    |--------------------------------------------------------------------------
    | HTML measure adapters (civic:scrape-measures)
    |--------------------------------------------------------------------------
    | vendor slug => BallotMeasureAdapter key. Used for rows that have a vendor
    | but no VIP feed. These sample-ballot pages all render measure text
    | server-side, so the heuristic `generic_html` adapter handles them; add a
    | dedicated adapter key here once a vendor needs bespoke parsing.
    | Rows with an explicit election_data_sources.platform_template bypass this.
    */
    'measure_adapters' => [
        'voteinfo_net' => 'generic_html',
        'enhanced_voting' => 'generic_html',
        'democracy_live' => 'generic_html',
        'election_reporting' => 'generic_html',
        'clarity' => 'generic_html',
    ],
];
