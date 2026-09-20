<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Which federal election cycle "now" belongs to. Discovery guesses used to
 * default to whatever year a language model remembered (2024), which wrote
 * stale election dates and got the record pruned as a past-cycle row.
 */
class ElectionCycle
{
    /** Even year of the general election still ahead of $now (or happening on it). */
    public static function year(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $year = (int) $now->year;

        if ($year % 2 === 1) {
            return $year + 1;
        }

        return $now->toDateString() > self::generalElectionDate($year) ? $year + 2 : $year;
    }

    /** First Tuesday after the first Monday in November. */
    public static function generalElectionDate(int $year): string
    {
        $nov1 = new \DateTime("{$year}-11-01");
        $dayOfWeek = (int) $nov1->format('N'); // 1=Mon … 7=Sun
        $daysToMonday = $dayOfWeek === 1 ? 0 : 8 - $dayOfWeek;

        return (clone $nov1)->modify("+{$daysToMonday} days")->modify('+1 day')->format('Y-m-d');
    }

    /** True when $date (any Carbon-parsable string) falls in the current cycle's year or later. */
    public static function isCurrentOrFuture(?string $date, ?CarbonInterface $now = null): bool
    {
        if ($date === null || trim($date) === '') {
            return false;
        }

        try {
            return (int) Carbon::parse($date)->year >= self::year($now);
        } catch (\Throwable) {
            return false;
        }
    }
}
