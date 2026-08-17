<?php

/**
 * News Sources Configuration
 *
 * Defines the RSS/API news providers available for candidate coverage.
 * Sources are grouped into:
 *   - 'national'  : Always searched regardless of candidate state
 *   - 'state'     : Keyed by two-letter state abbreviation
 *
 * Each source entry:
 *   id          — unique slug used as the `provider` value in candidate_news_articles
 *   label       — display name shown in the source-picker UI
 *   rss_url     — feed URL; use {QUERY} placeholder for search term
 *   type        — 'rss' | 'api' (api sources handled in CandidateNewsService)
 *   icon        — emoji or short text shown on the filter button
 *
 * 'civic_keywords' is used by DistrictNewsService (not candidate coverage) —
 * the broader keyword list it checks a fetched article's text against when
 * deciding whether a story about a locality is actually about election
 * administration (polling places, ballot measures, redistricting, etc.)
 * rather than local news generally. A shorter subset of these terms is also
 * used to build the initial RSS search query.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | National Sources (searched for every candidate)
    |--------------------------------------------------------------------------
    */
    'national' => [
        [
            'id'      => 'google_rss',
            'label'   => 'Google News',
            'icon'    => '🔍',
            'type'    => 'rss',
            'rss_url' => 'https://news.google.com/rss/search?q={QUERY}&hl=en-US&gl=US&ceid=US:en',
        ],
        [
            'id'      => 'cspan',
            'label'   => 'C-SPAN',
            'icon'    => '📺',
            'type'    => 'rss',
            // C-SPAN RSS search feed — returns video/event items mentioning the candidate
            'rss_url' => 'https://www.c-span.org/search/rss/?searchtype=Videos&ssearch={QUERY}',
        ],
        [
            'id'      => 'ap',
            'label'   => 'AP News',
            'icon'    => '📰',
            'type'    => 'rss',
            'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:apnews.com&hl=en-US&gl=US&ceid=US:en',
        ],
        [
            'id'      => 'politico',
            'label'   => 'Politico',
            'icon'    => '🏛️',
            'type'    => 'rss',
            'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:politico.com&hl=en-US&gl=US&ceid=US:en',
        ],
        [
            'id'      => 'thehill',
            'label'   => 'The Hill',
            'icon'    => '⚖️',
            'type'    => 'rss',
            'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:thehill.com&hl=en-US&gl=US&ceid=US:en',
        ],
        [
            'id'      => 'votebeat',
            'label'   => 'Votebeat',
            'icon'    => '🗳️',
            'type'    => 'rss',
            // Nonprofit newsroom covering election administration nationwide
            // (polling places, ballot access, voting machines, election law) —
            // votebeat.org, part of the Chalkbeat/States Newsroom network.
            'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:votebeat.org&hl=en-US&gl=US&ceid=US:en',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | State-Specific Local News Sources
    |--------------------------------------------------------------------------
    */
    'state' => [

        'AL' => [
            ['id' => 'al_com',       'label' => 'AL.com',             'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:al.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'wbrc',         'label' => 'WBRC Fox 6',         'icon' => '📡', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:wbrc.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'AK' => [
            ['id' => 'adn',          'label' => 'Anchorage Daily News','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:adn.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'AZ' => [
            ['id' => 'azcentral',    'label' => 'AZCentral',          'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:azcentral.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'kjzz',         'label' => 'KJZZ / NPR Arizona', 'icon' => '📻', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:kjzz.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'AR' => [
            ['id' => 'arktimes',     'label' => 'Arkansas Times',     'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:arktimes.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'CA' => [
            ['id' => 'latimes',      'label' => 'LA Times',           'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:latimes.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'sfgate',       'label' => 'SF Gate',            'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:sfgate.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'sacbee',       'label' => 'Sacramento Bee',     'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:sacbee.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'sdunion',      'label' => 'San Diego Union-Trib','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:sandiegouniontribune.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'calmatters',   'label' => 'CalMatters',         'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:calmatters.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'CO' => [
            ['id' => 'denverpost',   'label' => 'Denver Post',        'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:denverpost.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'coloradosun',  'label' => 'Colorado Sun',       'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:coloradosun.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'CT' => [
            ['id' => 'ctmirror',     'label' => 'CT Mirror',          'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:ctmirror.org&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'nhregister',   'label' => 'New Haven Register', 'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nhregister.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'FL' => [
            ['id' => 'miamiherald',  'label' => 'Miami Herald',       'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:miamiherald.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'orlandosentinel','label' => 'Orlando Sentinel',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:orlandosentinel.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'tampabay',     'label' => 'Tampa Bay Times',    'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:tampabay.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'flpolitics',   'label' => 'Florida Politics',   'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:floridapolitics.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'GA' => [
            ['id' => 'ajc',          'label' => 'Atlanta Journal-Constitution','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:ajc.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'georgiarecorder','label' => 'Georgia Recorder',  'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:georgiarecorder.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'IL' => [
            ['id' => 'chicagotrib',  'label' => 'Chicago Tribune',    'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:chicagotribune.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'suntimes',     'label' => 'Chicago Sun-Times',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:suntimes.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'ilpolicyinst', 'label' => 'IL Policy Inst.',    'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:illinoispolicy.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'IN' => [
            ['id' => 'indystar',     'label' => 'IndyStar',           'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:indystar.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'IA' => [
            ['id' => 'desmoinesreg', 'label' => 'Des Moines Register','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:desmoinesregister.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'KY' => [
            ['id' => 'kentucky',     'label' => 'Kentucky.com',       'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:kentucky.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'kycir',        'label' => 'Kentucky Center for Investigative Reporting','icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:kycir.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'LA' => [
            ['id' => 'nola',         'label' => 'NOLA.com / Times-Picayune','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nola.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'laanalyzer',   'label' => 'Louisiana Illuminator','icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:lailluminator.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MD' => [
            ['id' => 'baltimoresun', 'label' => 'Baltimore Sun',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:baltimoresun.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'marylandmatters','label' => 'Maryland Matters',  'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:marylandmatters.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MA' => [
            ['id' => 'bostonglobe',  'label' => 'Boston Globe',       'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:bostonglobe.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'masslive',     'label' => 'MassLive',           'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:masslive.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MI' => [
            ['id' => 'freep',        'label' => 'Detroit Free Press',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:freep.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'bridgemi',     'label' => 'Bridge Michigan',     'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:bridgemi.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MN' => [
            ['id' => 'startribune',  'label' => 'Star Tribune',        'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:startribune.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'minnpost',     'label' => 'MinnPost',            'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:minnpost.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MS' => [
            ['id' => 'clarionledger','label' => 'Clarion Ledger',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:clarionledger.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'msissippi',    'label' => 'Mississippi Today',   'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:mississippitoday.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MO' => [
            ['id' => 'kcstar',       'label' => 'Kansas City Star',   'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:kansascity.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'stlpd',        'label' => 'St. Louis Post-Dispatch','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:stltoday.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'MT' => [
            ['id' => 'mtstandard',   'label' => 'Montana Standard',   'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:mtstandard.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'mtfreepress',  'label' => 'Montana Free Press',  'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:montanafreepress.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NE' => [
            ['id' => 'omahaherald',  'label' => 'Omaha World-Herald',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:omaha.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NV' => [
            ['id' => 'lvrj',         'label' => 'Las Vegas Review-Journal','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:reviewjournal.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'nvindependent','label' => 'Nevada Independent',  'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:thenevadaindependent.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NJ' => [
            ['id' => 'njcom',        'label' => 'NJ.com',             'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nj.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'njspotlight',  'label' => 'NJ Spotlight News',  'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:njspotlightnews.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NM' => [
            ['id' => 'abqjournal',   'label' => 'Albuquerque Journal', 'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:abqjournal.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'nmpoliticalreport','label' => 'NM Political Report','icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nmpoliticalreport.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NY' => [
            ['id' => 'nytimes',      'label' => 'NY Times',           'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nytimes.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'nypost',       'label' => 'NY Post',            'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nypost.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'nydailynews',  'label' => 'NY Daily News',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:nydailynews.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'cityandstate', 'label' => 'City & State NY',    'icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:cityandstateny.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'NC' => [
            ['id' => 'ncobserver',   'label' => 'Raleigh News & Observer','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:newsobserver.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'ncpolicywatch','label' => 'NC Policy Watch',    'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:ncpolicywatch.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'OH' => [
            ['id' => 'dispatch',     'label' => 'Columbus Dispatch',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:dispatch.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'cleveland',    'label' => 'Cleveland Plain Dealer','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:cleveland.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'OK' => [
            ['id' => 'oklahoman',    'label' => 'The Oklahoman',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:oklahoman.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'OR' => [
            ['id' => 'oregonian',    'label' => 'The Oregonian',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:oregonlive.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'oregoncapbur', 'label' => 'Oregon Capital Bureau','icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:oregoncapitalbureau.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'PA' => [
            ['id' => 'inquirer',     'label' => 'Philadelphia Inquirer','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:inquirer.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'pittsburghpg', 'label' => 'Pittsburgh Post-Gazette','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:post-gazette.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'pawatch',      'label' => 'Pennsylvania Capital-Star','icon' => '🏛️', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:penncapital-star.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'TN' => [
            ['id' => 'tennessean',   'label' => 'The Tennessean',     'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:tennessean.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'tnjustice',    'label' => 'Tennessee Lookout',  'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:tennesseelookout.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'TX' => [
            ['id' => 'dallasmorning','label' => 'Dallas Morning News', 'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:dallasnews.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'houstonchron', 'label' => 'Houston Chronicle',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:houstonchronicle.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'texastribune', 'label' => 'Texas Tribune',      'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:texastribune.org&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'tpr',          'label' => 'Texas Public Radio', 'icon' => '📻', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:tpr.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'UT' => [
            ['id' => 'slctrib',      'label' => 'Salt Lake Tribune',  'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:sltrib.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'kslnews',      'label' => 'KSL News',           'icon' => '📡', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:ksl.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'VA' => [
            ['id' => 'rtdispatch',   'label' => 'Richmond Times-Dispatch','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:richmond.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'vpm',          'label' => 'VPM / NPR Virginia', 'icon' => '📻', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:vpm.org&hl=en-US&gl=US&ceid=US:en'],
        ],
        'WA' => [
            ['id' => 'seattletimes', 'label' => 'Seattle Times',      'icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:seattletimes.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'crosscut',     'label' => 'Crosscut',           'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:crosscut.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'WI' => [
            ['id' => 'jsonline',     'label' => 'Milwaukee Journal Sentinel','icon' => '📍', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:jsonline.com&hl=en-US&gl=US&ceid=US:en'],
            ['id' => 'wiseye',       'label' => 'WisEye / WI Public Media','icon' => '📻', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:wispolitics.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'WV' => [
            ['id' => 'wvmetronews',  'label' => 'WV MetroNews',       'icon' => '📻', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:wvmetronews.com&hl=en-US&gl=US&ceid=US:en'],
        ],
        'WY' => [
            ['id' => 'wyofile',      'label' => 'WyoFile',            'icon' => '🔎', 'type' => 'rss', 'rss_url' => 'https://news.google.com/rss/search?q={QUERY}+site:wyofile.com&hl=en-US&gl=US&ceid=US:en'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Civic Keywords (district news relevance gate)
    |--------------------------------------------------------------------------
    */
    'civic_keywords' => [
        'polling place', 'polling site', 'poll worker', 'ballot measure',
        'ballot initiative', 'redistricting', 'voter registration', 'voter roll',
        'election day', 'early voting', 'mail ballot', 'absentee ballot',
        'election security', 'voting machine', 'election official',
        'county elections', 'election commission', 'referendum',
        'special election', 'runoff election',
    ],
];
