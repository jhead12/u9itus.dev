<?php

namespace App\Support;

use App\Models\BallotMeasure;
use App\Models\BallotMeasureCommittee;
use App\Models\ElectionDataSource;

/**
 * Central data-integrity rules for ballot measure committee links — the measure-finance
 * counterpart to PoliticianDataRules. A link is a claim that a committee in a state's
 * campaign finance system supports or opposes a specific measure, so the defect that
 * matters is a mis-assignment: the wrong measure, the wrong side, or the wrong state.
 *
 * Two tiers, used by:
 *  - violations(): malformed fields. Checked in BallotMeasureCommittee's saving hook, so
 *    no admin form, importer or AI path can persist them.
 *  - flags(): signs the link may be mis-assigned. A flagged link can't be auto-verified;
 *    a reviewer must look at it. HARD_FLAGS can never be verified at all. Re-run nightly
 *    by ballot-measures:audit-committee-links, which returns a verified link to review
 *    when a flag appears that its reviewer didn't see.
 */
class MeasureCommitteeRules
{
    public const POSITIONS = ['support', 'oppose'];

    public const STATUSES = ['pending', 'verified', 'rejected'];

    /** Flags that make a link wrong by definition, not just suspicious. */
    public const HARD_FLAGS = ['state_mismatch'];

    /**
     * FMEA detectability per flag (1 = a hard, near-certain rule; 5 = a soft signal
     * that needs a human eye). 'unreviewed' is a pending link with no flags: nothing
     * looks wrong, but no one has checked it against the filing yet.
     */
    public const DETECTABILITY = [
        'state_mismatch' => 1,
        'name_measure_conflict' => 2,
        'name_position_conflict' => 2,
        'duplicate_committee_name' => 3,
        'source_off_registry' => 4,
        'unreviewed' => 3,
    ];

    /** Plain-language labels for the admin UI. */
    public const FLAG_LABELS = [
        'state_mismatch' => "Committee's state doesn't match the measure's state",
        'name_measure_conflict' => 'Committee name mentions a different measure number',
        'name_position_conflict' => 'Committee name suggests the opposite side',
        'duplicate_committee_name' => 'Same committee name is linked under a different ID',
        'source_off_registry' => "Evidence link isn't on the state's official filing site",
    ];

    private const OPPOSE_SIGNALS = '/\b(no on|vote no|against|opposing|oppose|stop)\b/i';

    private const SUPPORT_SIGNALS = '/\b(yes on|vote yes|supporting|in support of|for (prop(osition)?|measure|question|issue|amendment))\b/i';

    // Measure letters are matched case-sensitively so "Question of Fairness" isn't read as Question "OF".
    private const MEASURE_REFERENCE = '/\b(?:prop(?:osition)?|measure|question|issue|amendment)\s*(?:no\.?\s*)?#?\s*((?-i:[A-Z]{0,2})\d{1,4}(?-i:[A-Z]?)|(?-i:[A-Z]{1,3}))\b/i';

    /**
     * Field-format violations. Empty means the row may be saved.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    public static function violations(array $attributes): array
    {
        $violations = [];

        if (PoliticianDataRules::stateViolation($attributes['state'] ?? null) !== null || blank($attributes['state'] ?? null)) {
            $violations[] = 'state must be a two-letter state code';
        }
        if (! in_array($attributes['position'] ?? null, self::POSITIONS, true)) {
            $violations[] = 'position must be support or oppose';
        }
        if (! in_array($attributes['status'] ?? 'pending', self::STATUSES, true)) {
            $violations[] = 'status must be pending, verified or rejected';
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9\-]{0,63}$/', (string) ($attributes['committee_id'] ?? ''))) {
            $violations[] = 'committee_id must be the filer ID (letters, digits and dashes)';
        }
        if (trim((string) ($attributes['committee_name'] ?? '')) === '') {
            $violations[] = 'committee_name is required';
        }
        if (! preg_match('#^https?://[^\s/]+#i', (string) ($attributes['source_url'] ?? ''))) {
            $violations[] = 'source_url must be an http(s) link to the filing';
        }

        return $violations;
    }

    /**
     * Signs the link is assigned to the wrong measure, side or state.
     *
     * @return list<string>
     */
    public static function flags(BallotMeasureCommittee $link): array
    {
        $measure = $link->ballotMeasure;
        $flags = [];

        if ($measure !== null && strtoupper((string) $measure->state) !== strtoupper((string) $link->state)) {
            $flags[] = 'state_mismatch';
        }

        if ($measure !== null && self::mentionsOtherMeasure((string) $link->committee_name, $measure)) {
            $flags[] = 'name_measure_conflict';
        }

        if (self::nameContradictsPosition((string) $link->committee_name, (string) $link->position)) {
            $flags[] = 'name_position_conflict';
        }

        $sameName = BallotMeasureCommittee::query()
            ->where('ballot_measure_id', $link->ballot_measure_id)
            ->where('status', '!=', 'rejected')
            ->when($link->exists, fn ($q) => $q->whereKeyNot($link->getKey()))
            ->get(['committee_id', 'committee_name'])
            ->contains(fn (BallotMeasureCommittee $other) => self::normalizeName($other->committee_name) === self::normalizeName($link->committee_name)
                && $other->committee_id !== $link->committee_id);
        if ($sameName) {
            $flags[] = 'duplicate_committee_name';
        }

        $registry = self::financeRegistryUrl((string) $link->state);
        if ($registry !== null && ! self::sameSite((string) $link->source_url, $registry)) {
            $flags[] = 'source_off_registry';
        }

        return $flags;
    }

    /**
     * Flags the reviewer who verified the link didn't see, plus hard flags, which no review
     * can accept.
     *
     * @param  list<string>  $flags
     * @return list<string>
     */
    public static function unacknowledged(array $flags, ?array $acknowledged): array
    {
        return array_values(array_filter(
            $flags,
            fn (string $flag) => in_array($flag, self::HARD_FLAGS, true) || ! in_array($flag, $acknowledged ?? [], true)
        ));
    }

    /** @param  list<string>  $flags */
    public static function hasHardFlag(array $flags): bool
    {
        return array_intersect($flags, self::HARD_FLAGS) !== [];
    }

    /** The state's official campaign finance site, from the civic source registry. */
    public static function financeRegistryUrl(string $state): ?string
    {
        return ElectionDataSource::query()
            ->where('state', strtoupper($state))
            ->where('level', 'state')
            ->value('campaign_finance_url') ?: null;
    }

    private static function nameContradictsPosition(string $name, string $position): bool
    {
        $opposes = (bool) preg_match(self::OPPOSE_SIGNALS, $name);
        $supports = (bool) preg_match(self::SUPPORT_SIGNALS, $name);

        return $position === 'support' ? ($opposes && ! $supports) : ($supports && ! $opposes);
    }

    private static function mentionsOtherMeasure(string $name, BallotMeasure $measure): bool
    {
        $number = strtoupper(trim((string) preg_replace('/^(prop(osition)?|measure|question|issue|amendment)\s*#?\s*/i', '', trim((string) $measure->measure_number))));
        if ($number === '' || ! preg_match_all(self::MEASURE_REFERENCE, $name, $matches)) {
            return false;
        }

        foreach ($matches[1] as $mentioned) {
            if (strtoupper($mentioned) !== $number) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeName(?string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $name)));
    }

    /**
     * Same site as the registry URL: the evidence host ends with the registry's base
     * domain. "cal-access.sos.ca.gov" has base "sos.ca.gov", so "powersearch.sos.ca.gov"
     * passes but "example.com" doesn't.
     */
    private static function sameSite(string $url, string $registryUrl): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $registryHost = preg_replace('/^www\./', '', mb_strtolower((string) parse_url($registryUrl, PHP_URL_HOST)));
        if ($host === '' || $registryHost === '') {
            return true;
        }

        $labels = explode('.', $registryHost);
        $base = count($labels) >= 4 ? implode('.', array_slice($labels, 1)) : $registryHost;

        return $host === $base || str_ends_with($host, '.'.$base);
    }
}
