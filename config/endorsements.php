<?php

/**
 * Known endorser titles / organizations and the verb phrases that signal an
 * endorsement, used by App\Services\EndorsementClassifier to detect real
 * public endorsements ("Governor Newsom endorses Jane Smith") inside already
 * fetched/verified candidate_news_articles rows.
 *
 * Unlike config/pac_affiliations.php (a single substring match against a
 * contributor name), an endorsement needs two things to co-occur in the same
 * headline/snippet: a group keyword (a title like "Governor" or an org name
 * like "AFL-CIO") AND a verb phrase ("endorses", "backs", ...) near it. See
 * EndorsementClassifier::classify() for the proximity-window matching.
 *
 * Add new groups/patterns or verb phrases here — no code changes needed
 * elsewhere. `patterns`/`verbs` are matched case-insensitively; verb entries
 * may use light regex alternation (e.g. `(?:his|her|their)`).
 */
return [

    'verbs' => [
        'endorse', 'endorses', 'endorsed', 'endorsing', 'endorsement of', 'endorsement from',
        // "back in the crosshairs", "backed into a corner", "backs off": a back-verb followed by
        // a particle is movement or retreat, not support; "back-to-back" and "back-channel" are compounds.
        '(?:back|backs|backed|backing)(?![-–])(?!\s+(?:in|on|to|at|from|home|down|out|up|off|into|away|over|under|and\s+forth|then|after|again)\b)',
        'throws? (?:his|her|their) support behind',
        'threw (?:his|her|their) support behind',
        'voices? support for',
        'gives? (?:his|her|their) endorsement to',
        'announces? (?:his|her|their|an)? ?endorsement',
        'stands? behind',
        'is supporting',
    ],

    'groups' => [

        'president' => [
            'label' => 'President',
            'patterns' => ['president', 'commander in chief'],
            // Well-known endorsers who are usually written without their title
            // ("Trump endorses Steve Hilton"). The value is the name shown on the profile.
            'named' => [
                'donald trump' => 'Donald Trump',
                'trump' => 'Donald Trump',
            ],
            // Regexes tested on the lowercased text just before / after a named match, to
            // reject relatives ("Eric Trump", "Trump Jr.") and non-endorsers ("Trump administration").
            'named_not_before' => '/(?:eric|ivanka|lara|melania|barron|tiffany|don(?:ald)? jr\.?|michael|robert|fred|mary|the|of) $/',
            'named_not_after' => '/^(?:\s+jr\b|\s+(?:administration|admin|campaign|organization|media|tower|university|foundation|tariffs?|effect|era|voters?|supporters?|loyalists?)\b)/',
        ],

        'vice_president' => [
            'label' => 'Vice President',
            'patterns' => ['vice president'],
        ],

        'governor' => [
            'label' => 'Governor',
            'patterns' => ['governor', 'gov.'],
        ],

        'us_senator' => [
            'label' => 'U.S. Senator',
            'patterns' => ['senator', 'sen.'],
        ],

        'us_representative' => [
            'label' => 'U.S. Representative',
            'patterns' => ['representative', 'congressman', 'congresswoman'],
        ],

        'mayor' => [
            'label' => 'Mayor',
            'patterns' => ['mayor'],
        ],

        'attorney_general' => [
            'label' => 'Attorney General',
            'patterns' => ['attorney general'],
        ],

        'afl_cio' => [
            'label' => 'AFL-CIO',
            'patterns' => ['afl-cio', 'afl cio'],
        ],

    ],

];
