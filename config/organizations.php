<?php

/**
 * Controlled vocabulary for Organization::org_type. The column itself stays
 * a plain string (see the organizations table migration) — this config is
 * the single source of truth for valid values and which ones may legally
 * endorse a candidate, mirroring the App\Models\BallotMeasure::LEVELS
 * const pattern rather than a DB-level enum.
 *
 * `can_endorse_candidates=false` types (starting with 501(c)(3) nonprofits)
 * are restricted to ballot-measure positions on their white-label portal —
 * a 501(c)(3) that endorses a candidate risks its tax-exempt status, so this
 * is enforced in App\Http\Requests\StoreOrganizationEndorsementRequest
 * rather than left to sales judgment.
 */
return [
    'types' => [
        'pac' => [
            'label' => 'PAC',
            'can_endorse_candidates' => true,
        ],
        'union' => [
            'label' => 'Labor union',
            'can_endorse_candidates' => true,
        ],
        'c4_nonprofit' => [
            'label' => '501(c)(4) nonprofit',
            'can_endorse_candidates' => true,
        ],
        'c3_nonprofit' => [
            'label' => '501(c)(3) nonprofit',
            'can_endorse_candidates' => false,
        ],
        'student_org' => [
            'label' => 'Student organization',
            'can_endorse_candidates' => true,
        ],
        'campaign_coalition' => [
            'label' => 'Campaign coalition',
            'can_endorse_candidates' => true,
        ],
    ],
];
