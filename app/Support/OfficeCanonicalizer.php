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

    /** Label for an office row that carries no title at all. */
    public const UNNAMED_STATEWIDE = 'Other Statewide';

    /**
     * Named statewide offices beyond STATEWIDE_OFFICES, so the map panel says
     * what each job is instead of lumping them together. Aliases only merge
     * spellings of one office; distinct bodies (railroad vs. corporation
     * commission) keep their own title through the fallback in statewideGroup().
     */
    private const OTHER_STATEWIDE_ALIASES = [
        'Superintendent of Public Instruction' => ['superintendent of public instruction', 'superintendent of schools', 'school superintendent', 'superintendent of education', 'education superintendent'],
        'Insurance Commissioner'               => ['insurance commissioner', 'commissioner of insurance'],
        'State Auditor'                        => ['auditor'],
        'Agriculture Commissioner'             => ['agriculture commissioner', 'commissioner of agriculture'],
        'Labor Commissioner'                   => ['labor commissioner', 'commissioner of labor'],
        'Land Commissioner'                    => ['land commissioner', 'commissioner of public lands'],
        'Board of Equalization'                => ['board of equalization'],
    ];

    /**
     * The map panel's group name for a state-level office: one of the fixed
     * STATEWIDE_OFFICES, a named statewide office, "State Legislature" for
     * district seats filed at state level, or else the office's own title.
     */
    public static function statewideGroup(?string $office): string
    {
        $title = trim((string) preg_replace('/\s+/', ' ', (string) $office));
        if ($title === '') {
            return self::UNNAMED_STATEWIDE;
        }

        if ($canonical = self::canonicaliseStatewide($title)) {
            return $canonical;
        }

        $lower = strtolower($title);
        foreach (self::OTHER_STATEWIDE_ALIASES as $canonical => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $canonical;
                }
            }
        }

        if (preg_match('/\b(?:state (?:senat|repres|house)|assembl|legislat|delegate)|^representative\b/', $lower)) {
            return 'State Legislature';
        }

        return ucfirst($title);
    }

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
