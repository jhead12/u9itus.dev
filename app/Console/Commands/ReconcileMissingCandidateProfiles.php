<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Console\Command;

class ReconcileMissingCandidateProfiles extends Command
{
    protected $signature = 'politicians:reconcile-missing-profiles
        {--state=* : Restrict to one or more two-letter state codes (repeatable)}
        {--election-year= : Restrict to records for a specific election year}
        {--limit=500 : Max number of unlinked candidate records to inspect}
        {--dry-run : Report actions only, no DB writes}';

    protected $description = 'Create/link unclaimed politician profiles from election_candidate_records when profiles are missing.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $year = $this->normalizeYear($this->option('election-year'));
        $states = $this->normalizeStates($this->option('state'));

        $query = ElectionCandidateRecord::query()
            ->whereNotNull('full_name')
            ->whereRaw("TRIM(full_name) <> ''")
            ->whereDoesntHave('identityLinks')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');

        if ($year !== null) {
            // Only filter by year when election_date is set — records with a null
            // election_date (e.g. manually-corrected imports) must never be excluded.
            $query->where(function ($q) use ($year) {
                $q->whereNull('election_date')
                  ->orWhereYear('election_date', $year);
            });
        }

        if ($states !== []) {
            $query->whereIn('state', $states);
        }

        $records = $query->limit($limit)->get();

        $created = 0;
        $linked = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $this->shouldProcessRecord($record)) {
                $skipped++;
                continue;
            }

            $match = $this->findExistingPolitician($record);

            if ($match) {
                $existingUpdates = $this->buildExistingPoliticianUpdates($match, $record);
                if ($existingUpdates !== []) {
                    if (! $dryRun) {
                        $match->update($existingUpdates);
                    }
                    $updated++;
                }

                if (! $dryRun) {
                    $this->upsertIdentityLink($match, $record, 0.97);
                }

                $this->line('[LINK] ' . $record->full_name . ' (' . strtoupper((string) $record->state) . ') => #' . $match->id);
                $linked++;
                continue;
            }

            $payload = $this->buildPoliticianPayload($record);

            if (! $dryRun) {
                $createdPolitician = Politician::create($payload);
                $this->upsertIdentityLink($createdPolitician, $record, 1.0);
                $this->line('[CREATE] ' . $record->full_name . ' (' . strtoupper((string) $record->state) . ') => #' . $createdPolitician->id);
            } else {
                $this->line('[CREATE] ' . $record->full_name . ' (' . strtoupper((string) $record->state) . ')');
            }

            $created++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            'Missing-profile reconciliation complete%s: %d created, %d linked, %d updated, %d skipped.',
            $suffix,
            $created,
            $linked,
            $updated,
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * @param mixed $yearOption
     */
    protected function normalizeYear(mixed $yearOption): ?int
    {
        if ($yearOption === null || trim((string) $yearOption) === '') {
            return null;
        }

        $year = (int) $yearOption;

        return $year >= 1900 && $year <= 2100 ? $year : null;
    }

    /**
     * @param mixed $stateOption
     * @return array<int, string>
     */
    protected function normalizeStates(mixed $stateOption): array
    {
        if (! is_array($stateOption)) {
            return [];
        }

        $states = [];
        foreach ($stateOption as $state) {
            $normalized = strtoupper(trim((string) $state));
            if (strlen($normalized) === 2) {
                $states[] = $normalized;
            }
        }

        return array_values(array_unique($states));
    }

    protected function shouldProcessRecord(ElectionCandidateRecord $record): bool
    {
        $name = trim((string) $record->full_name);
        if ($name === '') {
            return false;
        }

        $payload = is_array($record->payload) ? $record->payload : [];
        $primary = strtolower((string) ($payload['primary_result'] ?? ''));
        $result = strtolower((string) ($payload['result_status'] ?? ''));

        if ($primary === 'eliminated' || $result === 'lost') {
            return false;
        }

        return true;
    }

    protected function findExistingPolitician(ElectionCandidateRecord $record): ?Politician
    {
        $query = Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower((string) $record->full_name)])
            ->whereRaw('UPPER(COALESCE(state, "")) = ?', [strtoupper((string) ($record->state ?? ''))]);

        if (! empty($record->political_office)) {
            $query->whereRaw('LOWER(COALESCE(political_office, "")) = ?', [strtolower((string) $record->political_office)]);
        }

        if (! empty($record->district)) {
            $query->where(function ($q) use ($record) {
                $q->whereNull('district')
                    ->orWhereRaw('LOWER(COALESCE(district, "")) = ?', [strtolower((string) $record->district)]);
            });
        }

        return $query
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPoliticianPayload(ElectionCandidateRecord $record): array
    {
        $payload = is_array($record->payload) ? $record->payload : [];
        $resultStatus = strtolower((string) ($payload['result_status'] ?? ''));

        $website = $this->extractWebsite($payload);

        return [
            'user_id' => null,
            'full_name' => trim((string) $record->full_name),
            'political_office' => $this->nullableString($record->political_office),
            'governance_level' => $this->nullableString($record->governance_level) ?? 'State',
            'district' => $this->nullableString($record->district),
            'party_affiliation' => $this->nullableString($record->party_affiliation),
            'state' => strtoupper((string) ($record->state ?? '')),
            'city' => $this->nullableString($record->city),
            'website_url' => $website,
            'bio' => 'This is an unclaimed profile generated from election data and available for verified claim by the official campaign.',
            'verified_official' => false,
            'verification_status' => 'unverified',
            'is_active' => true,
            'page_published' => true,
            'is_running_candidate' => ! in_array($resultStatus, ['won', 'incumbent', 'lost'], true),
            'term_status' => $resultStatus === 'won' || $resultStatus === 'incumbent'
                ? 'seated'
                : ($resultStatus === 'lost' ? 'lost' : 'running'),
            'status_updated_at' => now(),
            'ballotpedia_id' => $this->extractBallotpediaId($payload['ballotpedia_url'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildExistingPoliticianUpdates(Politician $politician, ElectionCandidateRecord $record): array
    {
        $updates = [];

        if (empty($politician->district) && ! empty($record->district)) {
            $updates['district'] = $record->district;
        }

        if (empty($politician->party_affiliation) && ! empty($record->party_affiliation)) {
            $updates['party_affiliation'] = $record->party_affiliation;
        }

        if (empty($politician->governance_level) && ! empty($record->governance_level)) {
            $updates['governance_level'] = $record->governance_level;
        }

        if (empty($politician->city) && ! empty($record->city)) {
            $updates['city'] = $record->city;
        }

        return $updates;
    }

    protected function upsertIdentityLink(Politician $politician, ElectionCandidateRecord $record, float $score): void
    {
        CandidateIdentityLink::updateOrCreate(
            [
                'politician_id' => $politician->id,
                'election_candidate_record_id' => $record->id,
            ],
            [
                'match_score' => round($score, 4),
                'link_source' => 'system',
                'linked_by_user_id' => null,
                'match_breakdown' => [
                    'name' => 1.0,
                    'state' => 1.0,
                    'office' => ! empty($record->political_office) ? 1.0 : 0.5,
                ],
                'linked_at' => now(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function extractWebsite(array $payload): ?string
    {
        $candidates = [
            $payload['website'] ?? null,
            $payload['campaign_website'] ?? null,
            $payload['official_website'] ?? null,
            $payload['website_url'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $url = $this->sanitizeUrl($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    protected function sanitizeUrl(mixed $url): ?string
    {
        $value = trim((string) ($url ?? ''));

        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = 'https://' . ltrim($value, '/');
        }

        $validated = filter_var($value, FILTER_VALIDATE_URL);

        return is_string($validated) ? $validated : null;
    }

    protected function extractBallotpediaId(mixed $url): ?string
    {
        $value = trim((string) ($url ?? ''));

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'https://ballotpedia.org/')) {
            $slug = substr($value, strlen('https://ballotpedia.org/'));
        } elseif (str_starts_with($value, '/')) {
            $slug = ltrim($value, '/');
        } else {
            return null;
        }

        if ($slug === '' || str_contains($slug, '?') || str_contains($slug, '#')) {
            return null;
        }

        return urldecode($slug);
    }

    protected function nullableString(?string $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
