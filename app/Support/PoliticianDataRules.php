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
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA',
        'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT',
        'VA', 'WA', 'WV', 'WI', 'WY', 'DC', 'PR', 'GU', 'VI', 'AS', 'MP',
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
     * Leading qualifier / title / geography / headline lead-in word, anchored
     * to the start of the string. Shared by {@see headlineFragmentViolation()}
     * (rejects a name starting with one of these outright) and
     * {@see \App\Support\PoliticianNameRepairer} (strips one of these at a
     * time from the front and re-validates what's left) — kept as one public
     * constant so the two vocabularies can't drift apart.
     */
    public const LEADING_QUALIFIER_PATTERN = '/^\s*(the|former|ex|current|incumbent|new|next|another|embattled|fiery|firebrand|outspoken|controversial|progressive|conservative|moderate|billionaire|bilionaire|millionaire|millennial|boomer|independent|democrat(ic)?|republican|libertarian|green|maga|gop|trump[\s-]?backed|reality|video|watch|listen|breaking|exclusive|opinion|editorial|poll|meet|why|how|when|where|what|who|after|before|amid|apostle|pastor|bishop|chief|consumer|onerepublic|indian|asian|african|latino|latina|hispanic|jewish|muslim|evangelical|california|nevada|tennessee|texas|arizona|florida|riverside|orange county|east bay|tri[\s-]valley|bay area|silicon valley|san jos[e\x{00E9}]|los angeles|northern|southern|sheriff|deputy|officer|detective|governor|gov\.|lieutenant governor|lieutenant|lt\.|senator|sen\.|representative|rep\.|congressman|congresswoman|delegate|speaker|mayor|treasurer|controller|comptroller|supervisor|assemblymember|assemblyman|assemblywoman|councilmember|councilman|councilwoman|alderman|selectman|trustee|clerk|auditor|coroner|constable|commissioner)(?![a-zA-Z0-9_])/iu';

    /**
     * RSS / news-headline extraction artifacts. The candidate-discovery
     * pipeline (RssCandidateDiscoverySource → CandidateLeadPromoter) pulls
     * "names" out of Google-News headlines; without these checks, fragments
     * like "Former L.A. Mayor Antonio", "Eric Swalwell Won More",
     * "Job Creator", "Nevada Joe Lombardo" and "California Governor's Race"
     * get written as election_candidate_records.full_name and rendered on
     * the public map. A real name starts with a given name and ends on a
     * surname — not an adjective, party, title, geography, or headline verb.
     * Case-insensitive.
     */
    private const HEADLINE_NAME_REJECT_PATTERNS = [
        self::LEADING_QUALIFIER_PATTERN,
        // Trailing dangling preposition / pronoun / headline verb.
        '/\b(is|are|was|were|has|have|had|he|she|they|him|them|his|her|their|to|for|of|in|on|by|as|and|or|but|more|less|most|out|off|up|won|wins|lost|loses|edges|edged|leads|trails|run|runs|running|ran|advances|advanced|exits|exited|enters|entered|joins|joined|drops|dropped|launches|launched|announces|announced|officially|eyes|weighs|mulls|backs|backed|slams|blasts|says|said|outraises|outraised|other|another|dinner|chances|comeback|bid)\s*$/i',
        // Process / horse-race noun that never appears inside a person's name.
        '/\b(campaign|candidacy|frontrunner|front[\s-]runner|primary|runoff|ballot|race|contest|showdown|matchup|fundraising|debate)\b/i',
        // Trailing occupation / media-role noun left dangling by a truncated
        // headline ("Job Creator", "Former Fox News Contributor"). Kept
        // deliberately narrow — words here must never plausibly be a surname.
        '/\b(creator|advocate|activist|strategist|commentator|contributor|columnist|pundit|correspondent|anchorman|spokesperson|spokesman|spokeswoman)\s*$/i',
        // Headline action verb anywhere in the string.
        '/\b(exits?|exited|suspends?|concedes?|endorses?|slams?|blasts?|rips|touts?|unveils?|clashes|spars|drops out|bows out|weighs in)\b/i',
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

        // A real name has a given name and a surname. Rejecting single-word
        // remainders is also what stops PoliticianNameRepairer from ever
        // leaving behind a bare leftover like "Party" (from "Democratic
        // Party") or "Ahmed" (from "Christian Ahmed") after stripping a
        // leading qualifier.
        if (str_word_count($name) < 2) {
            return 'name has fewer than 2 words';
        }

        // A name can never start with a dangling preposition/conjunction —
        // this only happens when a leading-qualifier strip removes a title
        // but leaves a fragment like "of Maury County, Tennessee" (from
        // "Mayor of Maury County, Tennessee") behind.
        if (preg_match('/^\s*(of|for|in|by|with|and|or|at|to|from)\s+/i', $name)) {
            return 'name starts with a dangling preposition/conjunction';
        }

        foreach (self::NAME_REJECT_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return 'name matches artifact pattern: '.$pattern;
            }
        }

        // Must contain at least one letter (rejects "---", "123", etc.)
        if (! preg_match('/\p{L}/u', $name)) {
            return 'name contains no letters';
        }

        return null;
    }

    /**
     * Stricter check for names produced by the candidate-discovery pipeline
     * (RSS news-headline extraction). Runs every {@see nameViolation()} rule
     * plus the headline-fragment heuristics — a real name starts with a given
     * name and ends on a surname, not an adjective, party, title, geography,
     * or headline verb. Used by the ElectionCandidateRecord write-guard,
     * CandidateLeadPromoter, and politicians:prune-junk-ecrs — NOT by the
     * Politician model hook, whose rows come from curated feeds.
     */
    public static function headlineFragmentViolation(?string $name): ?string
    {
        if (($v = self::nameViolation($name)) !== null) {
            return $v;
        }

        $name = trim((string) $name);

        foreach (self::HEADLINE_NAME_REJECT_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return 'name matches headline-fragment pattern: '.$pattern;
            }
        }

        // All-caps multi-word strings are headlines, not names
        // ("ADA BRICEÑO LAUNCHES CAMPAIGN").
        $letters = preg_replace('/[^\p{L}]/u', '', $name) ?? '';
        if (
            mb_strlen($letters) >= 4
            && str_word_count($name) >= 2
            && mb_strtoupper($letters, 'UTF-8') === $letters
        ) {
            return 'name is all-caps (likely a headline)';
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
            str_contains($p, 'democrat') || $p === 'd' => 'Democratic',
            str_contains($p, 'republican') || $p === 'r' => 'Republican',
            str_contains($p, 'independent') || $p === 'i' => 'Independent',
            str_contains($p, 'libertarian') || $p === 'l' => 'Libertarian',
            str_contains($p, 'green') || $p === 'g' => 'Green',
            str_contains($p, 'no party') || str_contains($p, 'nonpartisan') || str_contains($p, 'non-partisan') => 'No Party Preference',
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

    /** Map full state/territory names to USPS abbreviations. */
    private const STATE_NAME_TO_CODE = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
        'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID',
        'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS',
        'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME', 'MARYLAND' => 'MD',
        'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS',
        'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK',
        'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC',
        'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT',
        'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
        'WISCONSIN' => 'WI', 'WYOMING' => 'WY', 'DISTRICT OF COLUMBIA' => 'DC',
        'PUERTO RICO' => 'PR', 'GUAM' => 'GU', 'VIRGIN ISLANDS' => 'VI',
        'AMERICAN SAMOA' => 'AS', 'NORTHERN MARIANA ISLANDS' => 'MP',
    ];

    /**
     * Full US state / territory names mapped to their USPS code.
     * Exposed for callers that need to detect a state name embedded in
     * free text (e.g. "Texas Attorney General" on a row stamped state=CA).
     *
     * @return array<string, string>
     */
    public static function stateNameToCode(): array
    {
        return self::STATE_NAME_TO_CODE;
    }

    /**
     * Normalize state input to a valid 2-letter code when possible.
     * Handles full names (e.g. 'CALIFORNIA' → 'CA') and already-valid
     * uppercase codes. Returns null when input is empty or unmappable.
     */
    public static function resolveStateAbbreviation(?string $state): ?string
    {
        $state = strtoupper(trim((string) $state));
        if ($state === '') {
            return null;
        }

        if (in_array($state, self::ALLOWED_STATES, true)) {
            return $state;
        }

        return self::STATE_NAME_TO_CODE[$state] ?? null;
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
