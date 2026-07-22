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
        'back', 'backs', 'backed', 'backing',
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
