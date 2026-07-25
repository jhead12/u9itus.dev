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

    protected ?string $verbRegex = null;

    /**
     * @return array<int, array{group: string, label: string, matched_phrase: string, endorser_name: ?string, confidence: float}>
     */
    public function classify(string $headline, string $snippet): array
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

        $matches = [];

        foreach ($groups as $groupKey => $group) {
            $best = null;

            foreach (($group['patterns'] ?? []) as $pattern) {
                foreach ($this->findPatternOffsets($haystack, (string) $pattern) as [$offset, $length]) {
                    $distance = $this->nearestVerbDistance($offset, $length, $verbOffsets);
                    if ($distance === null || $distance > $this->proximityWindow) {
                        continue;
                    }

                    $confidence = $this->confidenceForDistance($distance);
                    if ($best === null || $confidence > $best['confidence']) {
                        $best = [
                            'group' => $groupKey,
                            'label' => $group['label'] ?? $groupKey,
                            'matched_phrase' => $this->excerpt($original, $offset, $length),
                            'endorser_name' => $this->captureEndorserName($original, $offset + $length),
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

        return array_map(fn ($mm) => [(int) $mm[1], strlen($mm[0])], $m[0]);
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
     * @param  array<int, array{0: int, 1: int}>  $verbOffsets
     */
    protected function nearestVerbDistance(int $offset, int $length, array $verbOffsets): ?int
    {
        $best = null;

        foreach ($verbOffsets as [$vOffset, $vLength]) {
            if ($vOffset >= $offset && $vOffset < $offset + $length) {
                $gap = 0; // overlapping spans
            } elseif ($vOffset >= $offset + $length) {
                $gap = $vOffset - ($offset + $length);
            } else {
                $gap = $offset - ($vOffset + $vLength);
            }

            $gap = max(0, $gap);
            if ($best === null || $gap < $best) {
                $best = $gap;
            }
        }

        return $best;
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

    /** Best-effort capture of a Title-Case name right after the matched keyword. */
    protected function captureEndorserName(string $original, int $afterOffset): ?string
    {
        $tail = mb_substr($original, $afterOffset, 60);

        if (!preg_match('/^[\s.]*([A-Z][a-zA-Z.\'-]+(?:\s+[A-Z][a-zA-Z.\'-]+){0,2})/', $tail, $m)) {
            return null;
        }

        $name = trim($m[1]);

        return $name !== '' ? $name : null;
    }

    protected function excerpt(string $original, int $offset, int $length): string
    {
        $start = max(0, $offset - 20);
        $text = mb_substr($original, $start, $length + 60);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
