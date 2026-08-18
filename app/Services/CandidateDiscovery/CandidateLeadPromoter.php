<?php

namespace App\Services\CandidateDiscovery;

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Support\CandidateNameCanonicalizer;

/**
 * Builds an election_candidate_records row from a verified CandidateLead —
 * same field shape as ImportElectionCandidates::buildPayload — so downstream
 * (politicians:reconcile-missing-profiles, politicians:sync-primary-results)
 * treats a promoted lead exactly like any other imported candidate record.
 */
class CandidateLeadPromoter
{
    public const SOURCE = 'candidate_discovery';

    public function promote(CandidateLead $lead): ElectionCandidateRecord
    {
        $payload = $lead->verified_payload ?? [];

        $record = ElectionCandidateRecord::updateOrCreate(
            [
                'source' => self::SOURCE,
                'external_candidate_id' => $lead->source_hash,
            ],
            [
                'full_name' => CandidateNameCanonicalizer::canonicalize($lead->full_name),
                'political_office' => $payload['political_office'] ?? $lead->office_hint,
                'governance_level' => $payload['governance_level'] ?? null,
                'state' => $lead->state,
                'county' => null,
                'city' => null,
                'district' => null,
                'party_affiliation' => $payload['party_affiliation'] ?? null,
                'election_date' => $this->resolveElectionDate($payload),
                'payload' => array_merge($payload, [
                    'discovered_via' => $lead->source_key,
                    'discovery_source_url' => $lead->source_url,
                ]),
                'last_seen_at' => now(),
            ],
        );

        $lead->update([
            'status' => CandidateLead::STATUS_PROMOTED,
            'election_candidate_record_id' => $record->id,
            'resolved_at' => now(),
        ]);

        return $record;
    }

    /**
     * A promoted lead almost never carries an explicit election_date — the
     * Ballotpedia/Wikipedia tiers only classify a primary_result, they don't
     * extract a date. Without one, politicians:sync-primary-results skips the
     * row unconditionally (its "no election_date" guard runs before --force
     * is even considered), so a promoted-but-dateless lead can never actually
     * be synced. An 'eliminated' candidate has no future date to guess, but
     * anyone else (advanced_to_general/running) is heading toward this
     * cycle's general election, so default to that computed date — same
     * "first Tuesday after first Monday in November" formula
     * SyncPrimaryResults::generalElectionDate() already uses.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveElectionDate(array $payload): ?string
    {
        if (isset($payload['election_date'])) {
            return $payload['election_date'];
        }

        if (($payload['primary_result'] ?? null) === 'eliminated') {
            return null;
        }

        return $this->generalElectionDate((int) now()->year);
    }

    private function generalElectionDate(int $year): string
    {
        $nov1 = new \DateTime("{$year}-11-01");
        $dayOfWeek = (int) $nov1->format('N'); // 1=Mon … 7=Sun
        $daysToMonday = ($dayOfWeek === 1) ? 0 : (8 - $dayOfWeek);
        $firstMonday = (clone $nov1)->modify("+{$daysToMonday} days");
        $electionDay = (clone $firstMonday)->modify('+1 day'); // Tuesday after first Monday

        return $electionDay->format('Y-m-d');
    }
}
