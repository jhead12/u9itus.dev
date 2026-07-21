<?php

/**
 * Manually curated roster of U.S. presidents to surface as unclaimed
 * federal Politician profiles: the sitting president plus former
 * presidents who remain politically newsworthy (endorsements, campaigning,
 * ongoing influence on current politics).
 *
 * Consumed by `php artisan politicians:import-presidents`. Edit this list
 * directly to add/remove people as relevance changes — no code changes
 * needed. `term_status` drives display: 'seated' for the current
 * president, 'former' for past presidents kept in the roster.
 */
return [

    'roster' => [

        [
            'full_name' => 'Donald Trump',
            'party_affiliation' => 'Republican',
            'term_status' => 'seated',
            'bio' => '47th President of the United States, inaugurated January 20, 2025.',
        ],

        [
            'full_name' => 'Joe Biden',
            'party_affiliation' => 'Democratic',
            'term_status' => 'former',
            'bio' => '46th President of the United States, served January 2021 – January 2025.',
        ],

        [
            'full_name' => 'Barack Obama',
            'party_affiliation' => 'Democratic',
            'term_status' => 'former',
            'bio' => '44th President of the United States, served January 2009 – January 2017.',
        ],

    ],

];
