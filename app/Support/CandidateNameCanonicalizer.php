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
        'gov', 'governor', 'ag',
        'dr', 'mr', 'mrs', 'ms', 'hon', 'honorable',
        'mayor', 'councilman', 'councilwoman', 'councilmember',
        'treasurer', 'auditor', 'comptroller', 'commissioner',
        'former', 'state', 'us', 'u.s', 'candidate', 'nominee',
    ];

    /** Titles that reliably introduce a name ("Detroit Mayor Mike Duggan"); "state"/"us"/"former" alone do not. */
    private const STRONG_TITLES = [
        'rep', 'representative', 'congressman', 'congresswoman', 'congressperson',
        'sen', 'senator', 'gov', 'governor', 'ag', 'mayor', 'councilman', 'councilwoman', 'councilmember',
        'treasurer', 'auditor', 'comptroller', 'commissioner',
    ];

    /**
     * Office titles longer than one word ("Mississippi Attorney General Lynn Fitch"). Each
     * works like a strong title; the longest match wins.
     */
    private const TITLE_PHRASES = [
        'attorney general', 'lieutenant governor', 'lt governor', 'lt gov', 'secretary of state',
        'agriculture commissioner', 'commissioner of agriculture', 'agriculture and commerce commissioner',
        'commissioner of agriculture and commerce', 'insurance commissioner', 'commissioner of insurance',
        'land commissioner', 'labor commissioner', 'public service commissioner', 'state treasurer',
        'state auditor', 'state comptroller', 'state superintendent', 'superintendent of education',
        'superintendent of public instruction',
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
        if ($stateWords > 0 && isset($words[$stateWords]) && (self::isTitle($words[$stateWords]) || self::titlePhraseLength($words, $stateWords) > 0)) {
            $words = array_slice($words, $stateWords);
        }

        // "Detroit Mayor Mike Duggan" / "Detroit's Mayor Mike Duggan": one or two place words, then a
        // title, then a first + last name.
        foreach ([1, 2] as $take) {
            if (! isset($words[$take]) || self::hasTitle(array_slice($words, 0, $take))) {
                continue;
            }
            $titleLength = self::titlePhraseLength($words, $take) ?: (self::isStrongTitle($words[$take]) ? 1 : 0);
            if ($titleLength > 0 && count($words) - $take - $titleLength >= 2) {
                $words = array_slice($words, $take);
                break;
            }
        }

        while ($words !== []) {
            if ($length = self::titlePhraseLength($words, 0)) {
                $words = array_slice($words, $length);
            } elseif (self::isTitle($words[0]) || in_array(self::bare($words[0]), self::LEADING_NOISE, true)) {
                array_shift($words);
            } else {
                break;
            }
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

    private static function isStrongTitle(string $word): bool
    {
        return in_array(self::bare($word), self::STRONG_TITLES, true);
    }

    /**
     * Word count of the longest office-title phrase starting at $at, or 0.
     *
     * @param  array<int, string>  $words
     */
    private static function titlePhraseLength(array $words, int $at): int
    {
        $best = 0;
        foreach (self::TITLE_PHRASES as $phrase) {
            $length = substr_count($phrase, ' ') + 1;
            $slice = array_map(self::bare(...), array_slice($words, $at, $length));
            if ($length > $best && count($slice) === $length && implode(' ', $slice) === $phrase) {
                $best = $length;
            }
        }

        return $best;
    }

    /** @param  array<int, string>  $words */
    private static function hasTitle(array $words): bool
    {
        foreach ($words as $word) {
            if (self::isTitle($word)) {
                return true;
            }
        }

        return false;
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
