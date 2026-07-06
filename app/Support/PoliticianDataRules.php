<?php

namespace App\Support;

/**
 * Central data-integrity rules for politician records.
 *
 * Single source of truth used by:
 *  - Politician model saving hook (blocks garbage writes at the app layer)
 *  - politicians:audit-data-integrity command (scans existing rows)
 *  - DB CHECK constraints migration (mirrors the same whitelists)
 *
 * Any scraper/AI import path automatically inherits these rules because
 * they run in the Eloquent saving event.
 */
class PoliticianDataRules
{
    /** Canonical party names. Null is allowed (unknown/ballot designation). */
    public const ALLOWED_PARTIES = [
        'Democratic',
        'Republican',
        'Independent',
        'Libertarian',
        'Green',
        'Nonpartisan',
        'No Party Preference',
    ];

    /** Allowed lifecycle statuses. */
    public const ALLOWED_TERM_STATUSES = [
        'seated', 'active', 'running', 'lost', 'retired', 'former', 'eliminated',
    ];

    /** Valid 2-letter state/territory codes. */
    public const ALLOWED_STATES = [
        'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
        'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
        'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
        'VA','WA','WV','WI','WY','DC','PR','GU','VI','AS','MP',
    ];

    /**
     * Patterns that indicate a full_name is an AI/scraper artifact,
     * not a person's name. Case-insensitive.
     */
    private const NAME_REJECT_PATTERNS = [
        '/\b(is |are |was |were )\b/i',
        '/\bno incumbents?\b/i',
        '/\bno candidates?\b/i',
        '/\boutcome\b/i',
        '/\belection\b/i',
        '/\bsource\s*\d*\b/i',
        '/\bresign/i',
        '/\bsafe (democratic|republican)\b/i',
        '/\bTBD\b/i',
        '/\bunknown\b/i',
        '/^\s*(democrat(ic)?|republican|independent|libertarian|green)\s*$/i',
    ];

    /**
     * Validate a full_name. Returns null when valid, or a human-readable
     * reason string when the name should be rejected.
     */
    public static function nameViolation(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '' || strlen($name) < 3) {
            return 'name too short';
        }

        if (strlen($name) > 120) {
            return 'name too long (>120 chars)';
        }

        if (str_word_count($name) > 6) {
            return 'name has too many words (likely a sentence)';
        }

        foreach (self::NAME_REJECT_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return 'name matches artifact pattern: ' . $pattern;
            }
        }

        // Must contain at least one letter (rejects "---", "123", etc.)
        if (! preg_match('/\p{L}/u', $name)) {
            return 'name contains no letters';
        }

        return null;
    }

    /**
     * Normalize a party string to its canonical form, or null if it cannot
     * be mapped. Never returns garbage — unmappable multi-word strings
     * become null (unknown).
     */
    public static function normalizeParty(?string $raw): ?string
    {
        $p = strtolower(trim((string) $raw));
        if ($p === '') {
            return null;
        }

        return match (true) {
            str_contains($p, 'democrat') || $p === 'd'   => 'Democratic',
            str_contains($p, 'republican') || $p === 'r' => 'Republican',
            str_contains($p, 'independent') || $p === 'i' => 'Independent',
            str_contains($p, 'libertarian') || $p === 'l' => 'Libertarian',
            str_contains($p, 'green') || $p === 'g'      => 'Green',
            str_contains($p, 'no party') || str_contains($p, 'nonpartisan') || str_contains($p, 'non-partisan')
                => 'No Party Preference',
            in_array(ucwords($p), self::ALLOWED_PARTIES, true) => ucwords($p),
            default => null, // unmappable — never store raw garbage
        };
    }

    /** Whether a party value (post-normalization) is acceptable to persist. */
    public static function partyViolation(?string $party): ?string
    {
        if ($party === null || $party === '') {
            return null; // null party is fine (unknown)
        }

        return in_array($party, self::ALLOWED_PARTIES, true)
            ? null
            : "party '{$party}' not in canonical list";
    }

    /** Validate a two-letter state code (null allowed). */
    public static function stateViolation(?string $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return in_array(strtoupper($state), self::ALLOWED_STATES, true)
            ? null
            : "state '{$state}' is not a valid 2-letter code";
    }

    /** Validate term_status (null allowed). */
    public static function termStatusViolation(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return in_array(strtolower($status), self::ALLOWED_TERM_STATUSES, true)
            ? null
            : "term_status '{$status}' not in allowed list";
    }

    /**
     * Run every rule against a politician attribute set.
     *
     * @param  array{full_name?:mixed,party_affiliation?:mixed,state?:mixed,term_status?:mixed}  $attributes
     * @return array<int, string> list of violation descriptions (empty = clean)
     */
    public static function violations(array $attributes): array
    {
        $violations = [];

        if (($v = self::nameViolation($attributes['full_name'] ?? null)) !== null) {
            $violations[] = $v;
        }
        if (($v = self::partyViolation($attributes['party_affiliation'] ?? null)) !== null) {
            $violations[] = $v;
        }
        if (($v = self::stateViolation($attributes['state'] ?? null)) !== null) {
            $violations[] = $v;
        }
        if (($v = self::termStatusViolation($attributes['term_status'] ?? null)) !== null) {
            $violations[] = $v;
        }

        return $violations;
    }
}
