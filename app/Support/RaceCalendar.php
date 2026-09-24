<?php

namespace App\Support;

use App\Services\CandidateDiscovery\CandidateCorroboration;

/**
 * Whether a state holds a U.S. Senate or gubernatorial race in a given year
 * (config/election_races.php).
 *
 * News discovery searches per state, so a national headline about Ken Paxton's
 * Texas Senate run can be filed as a New York Senate candidate. New York has no
 * Senate race in 2026, and that alone rules the row out — no name matching needed.
 */
final class RaceCalendar
{
    /** One-per-person races the calendar covers, as CandidateCorroboration::officeKind() names them. */
    public const OFFICES = ['senate', 'Governor'];

    /** The calendar's name for this office, or null when it doesn't cover it. */
    public static function kind(?string $office): ?string
    {
        $kind = CandidateCorroboration::officeKind($office);

        // "Arkansas Senate District 5" is a state legislative seat, not the U.S. Senate race.
        if ($kind === 'senate' && preg_match('/district/i', (string) $office)) {
            return null;
        }

        return in_array($kind, self::OFFICES, true) ? $kind : null;
    }

    /**
     * States holding this race in $year, or null when the calendar has no entry for it.
     *
     * @return array<int, string>|null
     */
    public static function states(string $kind, ?int $year = null): ?array
    {
        $year ??= ElectionCycle::year();
        $states = config("election_races.{$year}.{$kind}");

        return is_array($states) ? $states : null;
    }

    /**
     * True or false when the calendar knows; null for an office or year it doesn't cover,
     * which callers must treat as "no evidence either way".
     */
    public static function held(?string $state, ?string $office, ?int $year = null): ?bool
    {
        $kind = self::kind($office);
        $state = strtoupper(trim((string) $state));
        if ($kind === null || $state === '') {
            return null;
        }

        $states = self::states($kind, $year);

        return $states === null ? null : in_array($state, $states, true);
    }

    /** The election year a record belongs to: its own election_date when it has one, else the current cycle. */
    public static function yearOf(mixed $electionDate): int
    {
        if ($electionDate instanceof \DateTimeInterface) {
            return (int) $electionDate->format('Y');
        }

        $year = (int) substr(trim((string) $electionDate), 0, 4);

        return $year > 1900 ? $year : ElectionCycle::year();
    }
}
