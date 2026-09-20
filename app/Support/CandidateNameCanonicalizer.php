<?php

namespace App\Support;

/**
 * Cleans a raw RSS-headline-extracted candidate name before it's written as
 * election_candidate_records.full_name (see CandidateLeadPromoter::promote).
 *
 * Headline extraction grabs the leading Title-Case words, so it returns
 * "Rep. Eric Swalwell", "Texas Rep. James Talarico", "Read James Talarico's" and
 * "Ken Paxton College" for people who are really "Eric Swalwell", "James
 * Talarico" and "Ken Paxton". Left alone, each variant promotes to its own ECR
 * row and, because ReconcileMissingCandidateProfiles' findExistingPolitician()
 * match is exact-string (not fuzzy), can spawn its own Politician row too.
 *
 * Strips: leading titles / verbs / "<State> <title>", a trailing possessive, and
 * trailing headline words — but never trims a name down below two words.
 */
class CandidateNameCanonicalizer
{
    private const TITLES = [
        'rep', 'representative', 'congressman', 'congresswoman', 'congressperson',
        'sen', 'senator',
        'gov', 'governor',
        'dr', 'mr', 'mrs', 'ms', 'hon', 'honorable',
        'mayor', 'councilman', 'councilwoman', 'councilmember',
        'former', 'state', 'us', 'u.s', 'candidate', 'nominee',
    ];

    /** Verbs/labels a headline puts in front of the name ("Read James Talarico's plan"). */
    private const LEADING_NOISE = ['read', 'watch', 'see', 'meet', 'listen', 'exclusive', 'opinion', 'analysis', 'breaking'];

    /** Words a headline hangs after the name ("Ken Paxton College Station rally"). */
    private const TRAILING_NOISE = [
        'first', 'college', 'texas', 'senate', 'race', 'campaign', 'primary', 'runoff', 'election',
        'republican', 'democrat', 'democratic', 'gop', 'congress', 'governor', 'says', 'said', 'wins',
        'won', 'leads', 'faces', 'announces', 'launches', 'debate', 'poll', 'polls', 'ad', 'ads',
    ];

    public static function canonicalize(?string $name): string
    {
        $raw = trim((string) $name);
        if ($raw === '') {
            return '';
        }

        $words = preg_split('/\s+/', $raw) ?: [];

        // "Texas Rep. James Talarico": a state name is noise only when a title follows it.
        $stateWords = self::stateWordCount($words);
        if ($stateWords > 0 && isset($words[$stateWords]) && self::isTitle($words[$stateWords])) {
            $words = array_slice($words, $stateWords);
        }

        while ($words !== [] && (self::isTitle($words[0]) || in_array(self::bare($words[0]), self::LEADING_NOISE, true))) {
            array_shift($words);
        }

        // "Talarico's" / "Paxton’s": possessive on the last word only.
        if ($words !== []) {
            $last = array_key_last($words);
            $words[$last] = preg_replace('/[\'’]s$/iu', '', $words[$last]) ?? $words[$last];
        }

        // Trailing noise, only while a first + last name would remain ("Ken Paxton College" → "Ken Paxton").
        while (count($words) > 2 && in_array(self::bare(end($words)), self::TRAILING_NOISE, true)) {
            array_pop($words);
        }

        $canonical = implode(' ', $words);

        return $canonical !== '' ? $canonical : $raw;
    }

    private static function isTitle(string $word): bool
    {
        return in_array(self::bare($word), self::TITLES, true);
    }

    private static function bare(string $word): string
    {
        return strtolower(rtrim($word, '.,:;'));
    }

    /** How many leading words spell a US state name ("New York" = 2, "Texas" = 1, else 0). */
    private static function stateWordCount(array $words): int
    {
        $states = array_map('strtolower', array_values((array) config('u9itus.us_states', [])));

        foreach ([2, 1] as $take) {
            if (count($words) > $take && in_array(strtolower(implode(' ', array_slice($words, 0, $take))), $states, true)) {
                return $take;
            }
        }

        return 0;
    }
}
