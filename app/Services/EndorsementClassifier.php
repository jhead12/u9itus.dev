<?php

namespace App\Services;

/**
 * Detects real public endorsements ("Governor Newsom endorses Jane Smith")
 * inside a single article's headline/snippet, using config/endorsements.php.
 *
 * Unlike PacAffiliationClassifier (a single substring match against a known
 * contributor name), an endorsement claim requires two things to co-occur:
 * a group keyword (an office title like "Governor" or an org name like
 * "AFL-CIO") AND a verb phrase ("endorses", "backs", ...) within a small
 * proximity window of each other. Matching a title or a verb alone is not
 * enough — "Governor visits the county fair" must not fire, but "Governor
 * Newsom endorses Jane Smith" must.
 *
 * Source-agnostic and read-only: it only labels text already fetched by
 * CandidateNewsService, it does not fetch anything itself.
 */
class EndorsementClassifier
{
    /** Max character distance between a group keyword and a verb phrase to count as a match. */
    protected int $proximityWindow = 60;

    /** Tighter window for endorsers written without a title ("Trump endorses ..."): a bare surname is easy to hit by accident. */
    protected int $namedProximityWindow = 30;

    protected ?string $verbRegex = null;

    /**
     * When `$candidateName` (the politician the article was matched to) is given, an endorsement
     * verb the endorser performs ("Trump backs Jane Smith") only counts if the candidate's
     * surname comes after it, so an article that merely mentions both ("Trump's campaign puts
     * Mayor Bass on the defensive") cannot attach an endorsement to the candidate.
     *
     * @return array<int, array{group: string, label: string, matched_phrase: string, endorser_name: ?string, confidence: float}>
     */
    public function classify(string $headline, string $snippet, ?string $candidateName = null): array
    {
        $original = trim($headline . ' ' . $snippet);
        if ($original === '') {
            return [];
        }

        $groups = config('endorsements.groups', []);
        if (empty($groups)) {
            return [];
        }

        $haystack = strtolower($original);

        $verbOffsets = $this->findVerbOffsets($haystack);
        if (empty($verbOffsets)) {
            return [];
        }

        $surname = $this->surnameOf($candidateName);
        $matches = [];

        foreach ($groups as $groupKey => $group) {
            $best = null;

            $candidates = [];
            foreach (($group['patterns'] ?? []) as $pattern) {
                $candidates[] = [(string) $pattern, null];
            }
            foreach (($group['named'] ?? []) as $pattern => $displayName) {
                $candidates[] = [(string) $pattern, (string) $displayName];
            }

            foreach ($candidates as [$pattern, $fixedName]) {
                foreach ($this->findPatternOffsets($haystack, $pattern) as [$offset, $length]) {
                    if ($fixedName !== null && ! $this->namedMatchAllowed($group, $haystack, $offset, $length)) {
                        continue;
                    }

                    $window = $fixedName !== null ? $this->namedProximityWindow : $this->proximityWindow;
                    $distance = $this->nearestEndorserVerbDistance($haystack, $offset, $length, $verbOffsets, $surname);
                    if ($distance === null || $distance > $window) {
                        continue;
                    }

                    $confidence = $this->confidenceForDistance($distance);
                    if ($best === null || $confidence > $best['confidence']) {
                        $best = [
                            'group' => $groupKey,
                            'label' => $group['label'] ?? $groupKey,
                            'matched_phrase' => $this->excerpt($original, $offset, $length),
                            'endorser_name' => $fixedName ?? $this->captureEndorserName($original, $offset + $length),
                            'confidence' => $confidence,
                        ];
                    }
                }
            }

            if ($best !== null) {
                $matches[] = $best;
            }
        }

        return $matches;
    }

    /**
     * True if any match matched the given group key (e.g. 'governor').
     *
     * @param  array<int, array{group: string}>  $endorsements
     */
    public function hasGroup(array $endorsements, string $groupKey): bool
    {
        foreach ($endorsements as $match) {
            if (($match['group'] ?? null) === $groupKey) {
                return true;
            }
        }

        return false;
    }

    /** A bare surname is only the well-known endorser when it is not a relative or a phrase like "Trump administration". */
    protected function namedMatchAllowed(array $group, string $haystackLower, int $offset, int $length): bool
    {
        $before = substr($haystackLower, max(0, $offset - 20), min(20, $offset));
        $after = substr($haystackLower, $offset + $length, 30);

        if (! empty($group['named_not_before']) && preg_match($group['named_not_before'], $before)) {
            return false;
        }

        return empty($group['named_not_after']) || ! preg_match($group['named_not_after'], $after);
    }

    /**
     * @return array<int, array{0: int, 1: int}> Offset/length pairs.
     */
    protected function findVerbOffsets(string $haystackLower): array
    {
        if ($this->verbRegex === null) {
            $verbs = config('endorsements.verbs', []);
            $this->verbRegex = empty($verbs) ? '' : '/\b(?:' . implode('|', $verbs) . ')\b/';
        }

        if ($this->verbRegex === '') {
            return [];
        }

        if (!preg_match_all($this->verbRegex, $haystackLower, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        // "Trump-backed" is a modifier on someone else's candidate, not an endorsement verb.
        return array_values(array_filter(
            array_map(fn ($mm) => [(int) $mm[1], strlen($mm[0])], $m[0]),
            fn (array $v) => $v[0] === 0 || $haystackLower[$v[0] - 1] !== '-',
        ));
    }

    /**
     * @return array<int, array{0: int, 1: int}> Offset/length pairs.
     */
    protected function findPatternOffsets(string $haystackLower, string $pattern): array
    {
        $needle = strtolower(trim($pattern));
        if ($needle === '') {
            return [];
        }

        // Letter-based word boundaries (not \b) so a trailing "." in patterns
        // like "gov." or "sen." still counts as a clean boundary.
        $regex = '/(?<![a-z])' . preg_quote($needle, '/') . '(?![a-z])/';

        if (!preg_match_all($regex, $haystackLower, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(fn ($mm) => [(int) $mm[1], strlen($mm[0])], $m[0]);
    }

    /**
     * Distance from the title/org keyword to a verb that makes it the ENDORSER:
     * the verb follows it ("Governor Newsom endorses ..."), or it is the "by"/"from"
     * object of a preceding verb ("endorsed by the Governor"). A verb that precedes
     * the keyword without "by" makes the titleholder the one being endorsed
     * ("Caucus endorses Congresswoman Escobar's bill") — no endorsement by them.
     *
     * A possessive keyword ("Trump's Cuba campaign") is a modifier, not the subject, so it only
     * pairs with a noun-form verb ("Trump's endorsement of ..."). When `$surname` is given, a
     * verb the keyword performs must be followed by it (the candidate is the one endorsed).
     *
     * @param  array<int, array{0: int, 1: int}>  $verbOffsets
     */
    protected function nearestEndorserVerbDistance(string $haystackLower, int $offset, int $length, array $verbOffsets, ?string $surname = null): ?int
    {
        $best = null;
        $end = $offset + $length;
        $possessive = (bool) preg_match("/^['’]s\\b/u", substr($haystackLower, $end, 6));

        foreach ($verbOffsets as [$vOffset, $vLength]) {
            if ($vOffset >= $offset && $vOffset < $end) {
                $gap = 0; // overlapping spans
            } elseif ($vOffset >= $end) {
                $gap = $vOffset - $end;

                if ($possessive && ! str_starts_with(substr($haystackLower, $vOffset, 8), 'endorse')) {
                    continue;
                }

                if ($surname !== null && ! $this->mentionsAfter($haystackLower, $vOffset + $vLength, $surname)) {
                    continue;
                }
            } else {
                $between = substr($haystackLower, $vOffset + $vLength, $offset - ($vOffset + $vLength));
                if (! preg_match('/^\s*(?:by|from)\s+(?:the\s+)?$/', $between)) {
                    continue;
                }
                $gap = strlen($between);
            }

            if ($best === null || $gap < $best) {
                $best = $gap;
            }
        }

        return $best;
    }

    protected function mentionsAfter(string $haystackLower, int $from, string $surname): bool
    {
        return (bool) preg_match('/(?<![a-z])' . preg_quote($surname, '/') . '(?![a-z])/', substr($haystackLower, $from));
    }

    /** Lowercased last name of a full name, ignoring suffixes like "Jr."; null if unusable. */
    protected function surnameOf(?string $fullName): ?string
    {
        $tokens = preg_split('/\s+/', strtolower(trim((string) preg_replace('/[^\p{L}\s\'’-]/u', '', (string) $fullName)))) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== '' && ! in_array($t, ['jr', 'sr', 'ii', 'iii', 'iv'], true)));
        $last = end($tokens);

        return $last !== false && strlen($last) >= 3 ? $last : null;
    }

    protected function confidenceForDistance(int $distance): float
    {
        return match (true) {
            $distance <= 5 => 0.95,
            $distance <= 20 => 0.85,
            $distance <= 40 => 0.70,
            default => 0.55,
        };
    }

    /** Title-Case words that follow a title but are not part of a person's name. */
    private const NOT_NAME_WORDS = [
        'republican', 'republicans', 'democrat', 'democrats', 'democratic', 'gop', 'candidate', 'candidates', 'hopeful',
        'nominee', 'race', 'primary', 'election', 'campaign', 'announces', 'says', 'joins', 'signs', 'vetoes', 'calls',
        'urges', 'tells', 'slams', 'names', 'picks', 'rallies', 'wants', 'and', 'the', 'for', 'of',
    ];

    /**
     * Best-effort capture of the endorser's name from the words right after their title
     * ("Gov. Gavin Newsom endorses ..." → "Gavin Newsom"). Stops at a verb, a party word,
     * a "D-Mass." tag or a possessive, and never returns more than three words.
     */
    protected function captureEndorserName(string $original, int $afterOffset): ?string
    {
        $tail = ltrim(mb_strcut($original, $afterOffset, 90), " \t.:");
        $word = "\p{Lu}[\p{L}.'’-]*";

        if (! preg_match("/^{$word}(?:[ \t]+{$word}){0,3}/u", $tail, $m)) {
            return null;
        }

        $verbs = implode('|', (array) config('endorsements.verbs', []));
        $verbRegex = $verbs === '' ? null : '/^(?:'.$verbs.')$/';
        $kept = [];

        foreach (preg_split('/\s+/u', $m[0]) ?: [] as $token) {
            $bare = rtrim($token, '.');
            $suffix = (bool) preg_match('/^(?:jr|sr|ii|iii|iv)$/i', $bare);

            if (count($kept) >= 3
                || in_array(mb_strtolower($bare), self::NOT_NAME_WORDS, true)
                || ($verbRegex !== null && preg_match($verbRegex, mb_strtolower($bare)))
                || (! $suffix && preg_match('/^\p{Lu}{2,}$/u', $bare))
                || preg_match('/^\p{Lu}-/u', $bare)) {
                break;
            }

            $possessive = (bool) preg_match("/['’]s$/u", $bare);
            $kept[] = $possessive ? preg_replace("/['’]s$/u", '', $bare) : ($suffix ? $token : $bare);

            if ($possessive) {
                break;
            }
        }

        $name = trim(implode(' ', $kept));

        return $name !== '' ? $name : null;
    }

    protected function excerpt(string $original, int $offset, int $length): string
    {
        $start = max(0, $offset - 20);
        $text = mb_substr($original, $start, $length + 60);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
