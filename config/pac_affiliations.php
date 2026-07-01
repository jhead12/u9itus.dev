<?php

/**
 * Known advocacy-group / PAC affiliation patterns.
 *
 * Used by App\Services\PacAffiliationClassifier to tag contributor/PAC
 * names (sourced from OpenSecrets `top_contributors` and/or FEC Schedule A
 * committee contributions) with the advocacy group they're affiliated
 * with, e.g. AIPAC-aligned pro-Israel PACs.
 *
 * Matching is case-insensitive substring matching against the
 * contributor's organization/PAC name. Add new groups/patterns here —
 * no code changes needed elsewhere.
 */
return [

    'groups' => [

        'aipac_pro_israel' => [
            'label' => 'AIPAC / Pro-Israel',
            'patterns' => [
                'aipac',
                'american israel public affairs committee',
                'pro-israel america',
                'pro israel america',
                'norpac',
                'u.s. israel pac',
                'us israel pac',
                'washington pac',
                'israel political action committee',
            ],
        ],

    ],

];
