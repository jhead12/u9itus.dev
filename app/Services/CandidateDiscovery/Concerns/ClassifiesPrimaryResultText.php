<?php

namespace App\Services\CandidateDiscovery\Concerns;

/**
 * Shared text classifier for BallotpediaLeadVerifier/WikipediaLeadVerifier —
 * same signal-keyword approach as SyncPrimaryResults::classify(), factored out
 * so both new verifier tiers (and SyncPrimaryResults, if it's ever migrated
 * onto these classes) read from one keyword list instead of two copies.
 */
trait ClassifiesPrimaryResultText
{
    private const ADVANCED_SIGNALS = [
        'advanced to the general',
        'won the primary',
        'won primary',
        'advanced to general',
        'general election candidate',
        'qualified for the general',
        'top-two primary',
        'advance',
    ];

    private const ELIMINATED_SIGNALS = [
        'lost the primary',
        'lost primary',
        'did not advance',
        'eliminated',
        'failed to advance',
        'defeated in the primary',
        'conceded',
    ];

    /**
     * Weaker than ADVANCED_SIGNALS but still confirms a real, current
     * candidacy — bio-style text (Wikipedia summaries, "About" pages) tends
     * to describe someone as "the nominee" or "running for X" rather than
     * narrating the specific primary-night result the way a Ballotpedia race
     * page does.
     */
    private const RUNNING_SIGNALS = [
        'is the democratic nominee',
        'is the republican nominee',
        'democratic nominee in the',
        'republican nominee in the',
        'is running for',
        'is a candidate for',
        'candidate in the',
    ];

    /** Returns 'advanced_to_general'|'eliminated'|'running'|null. */
    private function classifyPrimaryResultText(string $text): ?string
    {
        $tiers = [
            'eliminated' => self::ELIMINATED_SIGNALS,
            'advanced_to_general' => self::ADVANCED_SIGNALS,
            'running' => self::RUNNING_SIGNALS,
        ];

        $result = null;
        foreach ($tiers as $status => $signals) {
            foreach ($signals as $signal) {
                if (str_contains($text, $signal)) {
                    $result = $status;
                    break 2;
                }
            }
        }

        return $result;
    }

    /** Cheap relevance gate: does this page even mention the right state/office? */
    private function looksRelevant(string $text, ?string $stateName, ?string $officeHint): bool
    {
        $stateHit = $stateName !== null && str_contains($text, strtolower($stateName));
        $officeHit = $officeHint !== null && str_contains($text, strtolower($officeHint));

        return $stateHit || $officeHit;
    }
}
