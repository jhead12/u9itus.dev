<?php

namespace App\Support;

/**
 * Strips leading honorifics/titles ("Rep.", "Congressman", "Sen.", ...) from
 * a raw RSS-headline-extracted candidate name before it's written as
 * election_candidate_records.full_name (see CandidateLeadPromoter::promote).
 *
 * Without this, "Eric Swalwell" and "Rep. Eric Swalwell" — both real
 * extractions from different headlines about the same person, since
 * RssCandidateDiscoverySource::NAME_STOPWORDS doesn't filter titles — promote
 * to two different ECR rows and, because ReconcileMissingCandidateProfiles'
 * findExistingPolitician() match is exact-string (not fuzzy), can each spawn
 * their own Politician row for the same real candidate.
 */
class CandidateNameCanonicalizer
{
    private const TITLES = [
        'rep', 'representative', 'congressman', 'congresswoman', 'congressperson',
        'sen', 'senator',
        'gov', 'governor',
        'dr', 'mr', 'mrs', 'ms', 'hon', 'honorable',
        'mayor', 'councilman', 'councilwoman', 'councilmember',
    ];

    public static function canonicalize(?string $name): string
    {
        $raw = trim((string) $name);
        if ($raw === '') {
            return '';
        }

        $words = preg_split('/\s+/', $raw) ?: [];

        while ($words !== [] && in_array(strtolower(rtrim((string) $words[0], '.')), self::TITLES, true)) {
            array_shift($words);
        }

        $canonical = implode(' ', $words);

        return $canonical !== '' ? $canonical : $raw;
    }
}
