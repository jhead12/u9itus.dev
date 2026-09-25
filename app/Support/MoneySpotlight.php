<?php

namespace App\Support;

/**
 * The rotating spotlight at the top of the home page's "Follow the Money" section: the
 * ballot measure spotlight (MeasureSpotlight) and the top contested candidates
 * (CandidateSpotlight) take turns, one per half-hour slot, so repeat visitors see a
 * different race over the day without the page doing any work per request.
 */
class MoneySpotlight
{
    public const SLOT_SECONDS = 1800;

    /** @return array{type: string, data: array<string, mixed>}|null */
    public static function current(?string $visitorState = null, ?int $timestamp = null): ?array
    {
        $pool = [];

        if (($measure = MeasureSpotlight::pick($visitorState)) !== null) {
            $pool[] = ['type' => 'measure', 'data' => $measure];
        }
        foreach (CandidateSpotlight::pool($visitorState) as $candidate) {
            $pool[] = ['type' => 'candidate', 'data' => $candidate];
        }

        if ($pool === []) {
            return null;
        }

        return $pool[intdiv($timestamp ?? time(), self::SLOT_SECONDS) % count($pool)];
    }

    /** Cache key for the current slot, so each visitor state caches one pick per slot. */
    public static function cacheKey(?string $visitorState = null): string
    {
        return 'home:money_spotlight:'.($visitorState ?: 'any').':'.intdiv(time(), self::SLOT_SECONDS);
    }
}
