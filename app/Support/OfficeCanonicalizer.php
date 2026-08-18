<?php

namespace App\Support;

/**
 * Canonicalizes a raw political_office string (as imported from Ballotpedia,
 * Congress.gov, Google Civic, etc.) into one of a small set of known
 * statewide-office buckets. Extracted from MapStateCandidatesController so
 * it and RaceMatcher share one definition instead of drifting apart, the
 * way governance_level and is_running_candidate already have once each in
 * this app's history.
 */
class OfficeCanonicalizer
{
    public const STATEWIDE_OFFICES = [
        'Governor',
        'Lieutenant Governor',
        'Attorney General',
        'State Treasurer',
        'State Controller',
        'Secretary of State',
    ];

    /**
     * Fuzzy aliases so a race matches however the data was imported.
     * Keyed by canonical label => array of partial strings to match.
     */
    private const OFFICE_ALIASES = [
        // IMPORTANT: more-specific aliases MUST come before broader ones.
        // 'Lieutenant Governor' contains the word 'governor', so it must be
        // checked before 'Governor' or it will fall into the wrong bucket.
        'Lieutenant Governor'  => ['lieutenant governor', 'lt. governor', 'lt governor', 'lt gov'],
        'Governor'             => ['governor'],
        'Attorney General'     => ['attorney general'],
        'State Treasurer'      => ['treasurer'],
        'State Controller'     => ['controller', 'comptroller'],
        'Secretary of State'   => ['secretary of state'],
    ];

    /**
     * Maps a political_office string to one of the STATEWIDE_OFFICES group
     * keys above, or null if it doesn't match a known statewide office.
     */
    public static function canonicaliseStatewide(?string $office): ?string
    {
        if ($office === null || $office === '') {
            return null;
        }

        $lower = strtolower($office);
        foreach (self::OFFICE_ALIASES as $canonical => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $canonical;
                }
            }
        }

        return null;
    }
}
