<?php

namespace App\Services\CandidateDiscovery;

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Support\CandidateNameCanonicalizer;
use App\Support\CrossStateImpostors;
use App\Support\ElectionCycle;
use App\Support\PoliticianDataRules;
use Illuminate\Support\Str;

/**
 * Builds an election_candidate_records row from a verified CandidateLead —
 * same field shape as ImportElectionCandidates::buildPayload — so downstream
 * (politicians:reconcile-missing-profiles, politicians:sync-primary-results)
 * treats a promoted lead exactly like any other imported candidate record.
 */
class CandidateLeadPromoter
{
    public const SOURCE = 'candidate_discovery';

    /** @var array<string, array<int, array{id: int, state: string, name: string}>>|null */
    private ?array $holders = null;

    private ?CandidateCorroboration $corroboration = null;

    private function seatedHolders(): array
    {
        return $this->holders ??= CrossStateImpostors::seatedHolders();
    }

    public function promote(CandidateLead $lead): ?ElectionCandidateRecord
    {
        $payload = $lead->verified_payload ?? [];

        // The RSS discovery source extracts names from news headlines; a
        // verifier tier can confirm "someone by roughly this name is running"
        // without noticing the name itself is a fragment ("Former L.A. Mayor
        // Antonio", "Job Creator"). Block those here so they never reach the
        // map — mark the lead rejected rather than promoted so the run stats
        // and the review queue stay honest.
        $canonicalName = ($this->corroboration ??= new CandidateCorroboration)
            ->anchorName(CandidateNameCanonicalizer::canonicalize($lead->full_name), $lead->state);
        $nameViolation = PoliticianDataRules::headlineFragmentViolation($canonicalName);
        if ($nameViolation !== null) {
            $lead->update([
                'status' => CandidateLead::STATUS_REJECTED,
                'reason' => trim((string) $lead->reason.' | name-quality: '.$nameViolation, ' |'),
                'resolved_at' => now(),
            ]);

            return null;
        }

        $office = $payload['political_office'] ?? $lead->office_hint;

        // A national headline that mentions a sitting governor in a state-scoped
        // news search ("North Carolina Governor" → Greg Abbott) is a mention,
        // not a candidacy in that state.
        $holder = CrossStateImpostors::holderElsewhere($canonicalName, $office, $lead->state, $this->seatedHolders());
        if ($holder !== null) {
            $lead->update([
                'status' => CandidateLead::STATUS_REJECTED,
                'reason' => trim((string) $lead->reason.' | cross-state: seated '.$office.' in '.$holder['state'], ' |'),
                'resolved_at' => now(),
            ]);

            return null;
        }

        // Key the ECR by candidate identity (state + office + normalized
        // name), NOT by $lead->source_hash (the news-article URL hash).
        // Every headline about the same person used to mint its own row —
        // that is why "Steve Hilton" accumulated ~20 duplicate ECRs. Now the
        // 2nd..Nth article for a candidate updates the one row instead.
        $record = ElectionCandidateRecord::firstOrNew([
            'source' => self::SOURCE,
            'external_candidate_id' => $this->identityKey($lead->state, $office, $canonicalName),
        ]);

        if (! $record->exists) {
            $record->fill([
                'full_name' => $canonicalName,
                'political_office' => $office,
                'governance_level' => $payload['governance_level'] ?? null,
                'state' => $lead->state,
                'county' => null,
                'city' => null,
                'district' => null,
                'party_affiliation' => $payload['party_affiliation'] ?? null,
                'election_date' => $this->resolveElectionDate($payload),
            ]);
        }

        // Always refresh verification payload + provenance + freshness, but
        // leave name/office/state/date alone on an existing row so a manual
        // or reconcile correction is not clobbered by a later re-discovery.
        $record->payload = array_merge(
            is_array($record->payload) ? $record->payload : [],
            $payload,
            [
                'discovered_via' => $lead->source_key,
                'discovery_source_url' => $lead->source_url,
                'discovery_source_hash' => $lead->source_hash,
            ],
        );
        $record->last_seen_at = now();
        $record->save();

        $lead->update([
            'status' => CandidateLead::STATUS_PROMOTED,
            'election_candidate_record_id' => $record->id,
            'resolved_at' => now(),
        ]);

        return $record;
    }

    /**
     * Stable per-person key: "disc:{state}:{office-slug}:{name-slug}".
     */
    private function identityKey(?string $state, ?string $office, string $name): string
    {
        return 'disc:'
            .strtolower(trim((string) $state)).':'
            .(Str::slug((string) $office) ?: 'office').':'
            .(Str::slug($name) ?: 'name');
    }

    /**
     * A promoted lead almost never carries an explicit election_date — the
     * verifier only classifies a status, it doesn't
     * extract a date. Without one, politicians:sync-primary-results skips the
     * row unconditionally (its "no election_date" guard runs before --force
     * is even considered), so a promoted-but-dateless lead can never actually
     * be synced. An 'eliminated' candidate has no future date to guess, but
     * anyone else (advanced_to_general/running) is heading toward this
     * cycle's general election, so default to that computed date — same
     * "first Tuesday after first Monday in November" formula
     * SyncPrimaryResults::generalElectionDate() already uses.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveElectionDate(array $payload): ?string
    {
        // A payload date from a past cycle (the LLM tier used to guess 2024) would be
        // pruned as stale by politicians:prune-junk-ecrs — fall through to this cycle's general.
        if (isset($payload['election_date']) && ElectionCycle::isCurrentOrFuture((string) $payload['election_date'])) {
            return $payload['election_date'];
        }

        if (($payload['primary_result'] ?? null) === 'eliminated') {
            return null;
        }

        return ElectionCycle::generalElectionDate(ElectionCycle::year());
    }
}
