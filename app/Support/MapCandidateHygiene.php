<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Display-time guard for the public map's candidate lists.
 *
 * The importers write to several tables from several sources, so the map has
 * to defend itself against two kinds of rows that are individually "valid"
 * but wrong to show a voter:
 *
 *  - Placeholder / scrape-artifact names — "WI Candidate David", "Ballotpedia's
 *    Candidate Survey", a city name like "Huntington Beach". They pass
 *    {@see PoliticianDataRules::nameViolation()} because they look like two or
 *    three ordinary words.
 *  - The same person twice under different spellings — "Steven Bradford" and
 *    "Steve Bradford", or one platform row per import batch — which the exact
 *    lowercase-name comparison in the controller never matched.
 *
 * Nothing here deletes data. A junk-named row is hidden only when nothing
 * vouches for it (not seated, not a verified official); everything it finds is
 * also reported by `map:audit-candidates` so the rows can be fixed at source.
 */
class MapCandidateHygiene
{
    /** Common short forms folded to one given name so "Steve"/"Steven"/"Stephen" match. */
    private const NICKNAMES = [
        'steve' => 'steven', 'stephen' => 'steven', 'stevie' => 'steven',
        'mike' => 'michael', 'mick' => 'michael', 'mikey' => 'michael',
        'bill' => 'william', 'billy' => 'william', 'will' => 'william', 'willie' => 'william',
        'bob' => 'robert', 'bobby' => 'robert', 'rob' => 'robert', 'robbie' => 'robert',
        'jim' => 'james', 'jimmy' => 'james', 'jamie' => 'james',
        'tom' => 'thomas', 'tommy' => 'thomas',
        'dave' => 'david', 'davey' => 'david',
        'dan' => 'daniel', 'danny' => 'daniel',
        'joe' => 'joseph', 'joey' => 'joseph',
        'tony' => 'anthony',
        'chris' => 'christopher',
        'matt' => 'matthew',
        'nick' => 'nicholas',
        'rick' => 'richard', 'rich' => 'richard', 'dick' => 'richard', 'ricky' => 'richard',
        'ron' => 'ronald', 'ronnie' => 'ronald',
        'don' => 'donald', 'donnie' => 'donald',
        'ken' => 'kenneth', 'kenny' => 'kenneth',
        'greg' => 'gregory',
        'jeff' => 'jeffrey', 'geoff' => 'jeffrey',
        'jerry' => 'gerald',
        'ed' => 'edward', 'eddie' => 'edward',
        'andy' => 'andrew', 'drew' => 'andrew',
        'ben' => 'benjamin', 'benny' => 'benjamin',
        'tim' => 'timothy', 'timmy' => 'timothy',
        'phil' => 'philip', 'phillip' => 'philip',
        'fred' => 'frederick', 'freddie' => 'frederick',
        'pete' => 'peter',
        'charlie' => 'charles', 'chuck' => 'charles',
        'liz' => 'elizabeth', 'beth' => 'elizabeth', 'betsy' => 'elizabeth', 'lizzie' => 'elizabeth',
        'kate' => 'katherine', 'katie' => 'katherine', 'kathy' => 'katherine', 'cathy' => 'katherine',
        'catherine' => 'katherine', 'kathryn' => 'katherine',
        'sue' => 'susan', 'suzy' => 'susan',
        'jen' => 'jennifer', 'jenny' => 'jennifer',
        'becky' => 'rebecca',
        'debbie' => 'deborah', 'deb' => 'deborah',
        'peggy' => 'margaret', 'maggie' => 'margaret',
    ];

    /** Trailing word that makes a two-word "name" read as a town, not a person. */
    private const PLACE_SUFFIXES = ['beach', 'city', 'heights', 'springs', 'valley', 'village', 'township', 'borough', 'county'];

    /**
     * Identity key for "is this the same person": ASCII-folded, lowercase,
     * without titles, suffixes, middle names/initials, nickname-normalised.
     * "Steve Bradford" and "Steven Bradford" → "steven|bradford";
     * "Gilbert Ray Cisneros, Jr." → "gilbert|cisneros".
     */
    public static function identityKey(?string $name): string
    {
        $name = CandidateNameCanonicalizer::canonicalize($name);
        $name = Str::ascii($name);
        // "Greg Abbott's" is the same person as "Greg Abbott" with headline text attached.
        $name = preg_replace('/[\'’]s\s*$/iu', '', trim($name)) ?? $name;
        $name = preg_replace('/\([^)]*\)|"[^"]*"/', ' ', $name) ?? $name;
        $name = preg_replace('/,\s*(jr|sr|ii|iii|iv)\.?\s*$/i', '', $name) ?? $name;

        // "Bradford, Steven" → "Steven Bradford".
        if (substr_count($name, ',') === 1) {
            [$last, $first] = array_map('trim', explode(',', $name));
            if ($first !== '' && $last !== '') {
                $name = $first.' '.$last;
            }
        }

        $name = strtolower($name);
        $name = preg_replace('/[^a-z\s\'-]/', ' ', $name) ?? $name;
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn ($w) => $w !== '' && ! in_array($w, ['jr', 'sr', 'ii', 'iii', 'iv'], true),
        ));
        // Single-letter tokens are middle initials.
        $words = array_values(array_filter($words, fn ($w) => strlen(str_replace(['\'', '-'], '', $w)) > 1));

        if (count($words) < 2) {
            return $words[0] ?? '';
        }

        return (self::NICKNAMES[$words[0]] ?? $words[0]).'|'.$words[count($words) - 1];
    }

    /** Lowercase, punctuation-free form used to compare a name against known place names. */
    public static function placeKey(?string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii((string) $value))));
    }

    /**
     * News discovery sometimes glues a dateline city onto a name ("Austin Gina
     * Hinojosa" from "AUSTIN — Gina Hinojosa …"). Drop one leading place name
     * (one or two words) when a real first + last name remains, so the row
     * merges with the same person's clean record.
     *
     * @param  array<string, true>  $placeNames  placeKey()-normalised city names for the state
     */
    public static function stripLeadingPlace(?string $name, array $placeNames): ?string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];

        foreach ([1, 2] as $take) {
            if (count($words) - $take >= 2 && isset($placeNames[self::placeKey(implode(' ', array_slice($words, 0, $take)))])) {
                return implode(' ', array_slice($words, $take));
            }
        }

        return $name;
    }

    /**
     * Why this isn't a plausible person's name, or null when it looks fine.
     *
     * @param  array<string, true>  $placeNames  placeKey()-normalised city names for the state
     */
    public static function nameProblem(?string $name, array $placeNames = []): ?string
    {
        $name = trim((string) $name);

        if (($violation = PoliticianDataRules::nameViolation($name)) !== null) {
            return $violation;
        }

        if (PoliticianDataRules::headlineWordViolation($name) !== null) {
            return 'reads like a headline, not a name';
        }

        if (preg_match('/\b(candidates?|nominees?|survey|contact|vacant|placeholder|write-in list)\b/i', $name)) {
            return 'placeholder word in name';
        }

        if (str_contains($name, '?')) {
            return 'a question from a web page, not a name';
        }

        // "SeGqNJTiYizIbfRrOcCvX": random-case garbage. Real names cap out at one or two
        // internal capitals (McDonald, DeShawn, LaTonya).
        foreach (preg_split('/[\s-]+/u', $name) ?: [] as $token) {
            if (preg_match_all('/\p{Ll}\p{Lu}/u', $token) >= 3) {
                return 'random-case text, not a name';
            }
        }

        if (preg_match('/[\'’]s(\s|$)/u', $name) || preg_match('/^send us\b/i', $name)) {
            return 'scraped page text, not a name';
        }

        // Case-sensitive on purpose: "WI Candidate David", never "Al Green".
        $states = implode('|', array_filter(PoliticianDataRules::ALLOWED_STATES));
        if (preg_match('/^(?:'.$states.')\s+\S/', $name)) {
            return 'starts with a state abbreviation';
        }

        $words = preg_split('/\s+/', $name) ?: [];
        if (count($words) === 2 && in_array(strtolower($words[1]), self::PLACE_SUFFIXES, true)) {
            return 'reads like a place name';
        }

        if (isset($placeNames[self::placeKey($name)])) {
            return 'matches a city name';
        }

        return null;
    }

    /**
     * Whether a candidate row should be kept off the public map. Only rows
     * nothing vouches for are hidden — a seated or verified official with an
     * odd-looking name is reported by the audit instead of silently dropped.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, true>  $placeNames
     */
    public static function shouldHide(array $candidate, array $placeNames = []): bool
    {
        if (($candidate['status'] ?? null) === 'seated' || ! empty($candidate['verified'])) {
            return false;
        }

        return self::nameProblem($candidate['full_name'] ?? null, $placeNames) !== null;
    }

    /**
     * Collapse rows for the same person into the best one. Fields the winner
     * lacks (photo, website, party, Ballotpedia link) are filled from the
     * duplicates so nothing useful is lost in the merge.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{0: array<int, array<string, mixed>>, 1: int} [kept rows, number merged away]
     */
    public static function dedupe(array $candidates): array
    {
        $best = [];
        $order = [];
        $merged = 0;

        foreach ($candidates as $cand) {
            $key = self::identityKey($cand['full_name'] ?? null);
            if ($key === '') {
                $order[] = ['row', $cand];

                continue;
            }

            if (! isset($best[$key])) {
                $best[$key] = $cand;
                $order[] = ['key', $key];

                continue;
            }

            $merged++;
            $best[$key] = self::mergePair($best[$key], $cand);
        }

        $out = [];
        foreach ($order as [$kind, $value]) {
            $out[] = $kind === 'key' ? $best[$value] : $value;
        }

        return [$out, $merged];
    }

    /**
     * The better of two rows for one person, with the loser's missing fields
     * (photo, website, party, links) filled in.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private static function mergePair(array $existing, array $incoming): array
    {
        $incomingWins = self::score($incoming) > self::score($existing);
        $winner = $incomingWins ? $incoming : $existing;
        $loser = $incomingWins ? $existing : $incoming;

        foreach (['photo', 'website', 'ballotpedia_url', 'party', 'profile_url', 'slug'] as $field) {
            if (empty($winner[$field]) && ! empty($loser[$field])) {
                $winner[$field] = $loser[$field];
            }
        }

        return $winner;
    }

    /** Higher wins: platform profile > scraped row, seated > running, verified, has a public page/photo. */
    private static function score(array $c): int
    {
        return (($c['source'] ?? '') === 'platform' ? 4 : 0)
            + (($c['status'] ?? '') === 'seated' ? 3 : 0)
            + (! empty($c['verified']) ? 2 : 0)
            + (! empty($c['slug']) ? 1 : 0)
            + (! empty($c['photo']) ? 1 : 0);
    }
}
