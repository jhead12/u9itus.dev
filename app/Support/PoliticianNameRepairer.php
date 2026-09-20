<?php

namespace App\Support;

/**
 * Repairs a Politician full_name mangled by a leading qualifier/title/
 * geography word — "Independent Michael Shellenberger", "Former California
 * Xavier Becerra", "Lt. Gov. Eleni Kounalakis" — instead of only rejecting
 * it. Strips one leading match of {@see PoliticianDataRules::LEADING_QUALIFIER_PATTERN}
 * at a time (so multi-qualifier fragments like "Lt. Gov. Eleni" need two
 * passes: strip "Lt." → strip "Gov." → done) and stops as soon as the
 * remainder passes {@see PoliticianDataRules::nameViolation()} — the same
 * loose check the Politician model's saving hook already applies to every
 * write, so a repaired name is held to no stricter a bar than a hand-typed
 * one would be.
 *
 * Used both by Politician::boot()'s saving hook (so a repair happens
 * automatically on every write path) and by the politicians:repair-names
 * backfill command (to fix rows already sitting in the table).
 */
class PoliticianNameRepairer
{
    /**
     * "Oklahoma Gov. Kevin Stitt", "Pa. Rep. Patty Kim", "New York Gov. Kathy Hochul": a state
     * (name or abbreviation, up to two words) glued to an abbreviated title. The place isn't in
     * LEADING_QUALIFIER_PATTERN's fixed list, so it needs its own rule; the required period on
     * the title keeps this from touching ordinary names.
     */
    private const PLACE_TITLE_PREFIX = '/^(?:[A-Z][A-Za-z]*\.?(?:\s+[A-Z][A-Za-z]*\.?)?)\s+(?:Lt\.\s+Gov|Gov|Sen|Rep|Del)\.\s+(?=\S)/u';

    /** Words that name an office or governing body, never part of a person's name on their own. */
    private const OFFICE_WORDS = [
        'state', 'senate', 'assembly', 'house', 'congress', 'legislature', 'county', 'city', 'board', 'council',
        'of', 'the', 'us', 'u.s.', 'united', 'states', 'representatives', 'senator', 'representative', 'member', 'members',
    ];

    /** A name ending on one of these is an office, body or organisation ("Attorney General", "Texas Municipal Police Association"). */
    private const TRAILING_NON_PERSON_WORDS = [
        'general', 'treasurer', 'comptroller', 'controller', 'office', 'commissioners', 'association', 'party',
        'senate', 'assembly', 'council', 'board', 'committee', 'department', 'commission', 'legislature', 'district',
    ];

    /**
     * @return array{name: string, changed: bool, unrepairable: bool}
     *   `changed` is true only when a qualifier was stripped AND the
     *   remainder is a valid name. `unrepairable` is true when a qualifier
     *   was stripped but nothing valid was left (e.g. "Former California")
     *   — a signal worth flagging for manual review, distinct from a name
     *   that never matched a leading qualifier at all.
     */
    public static function repair(?string $name): array
    {
        $original = trim((string) $name);
        [$current, $strippedAnything] = self::strip($original);

        if (! $strippedAnything) {
            return ['name' => $original, 'changed' => false, 'unrepairable' => false];
        }

        if ($current === '' || PoliticianDataRules::nameViolation($current) !== null) {
            return ['name' => $original, 'changed' => false, 'unrepairable' => true];
        }

        return ['name' => $current, 'changed' => true, 'unrepairable' => false];
    }

    /**
     * True when the string cannot be a person at all, so there is nothing for a human to
     * review: only qualifier/geography words ("California", "Former California") or an
     * election-page title ("California's 2nd Congressional District election, 2026").
     * A leftover surname ("Former California Newsom" → "Newsom") is NOT this — that is a
     * real person whose name needs a look.
     */
    public static function isNotAPerson(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '') {
            return false;
        }

        if (str_contains($name, '?')
            || preg_match('/\b(?:1[89]|20)\d{2}\b|\b\d+(?:st|nd|rd|th)\b|\b(?:elections?|districts?|primary|runoff|ballot)\b/i', $name)) {
            return true;
        }

        [$remainder] = self::strip($name);
        if ($remainder === '') {
            return true;
        }

        // What is left after the qualifier strip: a question ("do I run for office"), or nothing
        // but names of offices and bodies ("State Senate", "Assembly").
        if (preg_match('/^(?:do|does|did|is|are|can|will|should|would|what|why|how|who)\b/i', $remainder)) {
            return true;
        }

        $words = preg_split('/[^a-z.]+/i', strtolower($remainder), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $words !== []
            && (array_diff($words, self::OFFICE_WORDS) === [] || in_array(end($words), self::TRAILING_NON_PERSON_WORDS, true));
    }

    /**
     * @return array{0: string, 1: bool} the name with leading qualifiers removed, and whether any were
     */
    private static function strip(string $original): array
    {
        $current = $original;
        $strippedAnything = false;

        while ($current !== '') {
            $next = trim((string) preg_replace(PoliticianDataRules::LEADING_QUALIFIER_PATTERN, '', $current));
            if ($next === $current) {
                $next = trim((string) preg_replace(self::PLACE_TITLE_PREFIX, '', $current));
            }

            if ($next === $current) {
                break;
            }

            $current = $next;
            $strippedAnything = true;
        }

        return [$current, $strippedAnything];
    }
}
