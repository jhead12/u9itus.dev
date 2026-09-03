<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use App\Services\GoogleCivicService;
use App\Support\CivicVendorClassifier;
use Illuminate\Console\Command;

/**
 * Step 2 of the civic source pipeline (see doc/CIVIC_SOURCE_REGISTRY.md).
 *
 * Fills `authority_name`, the official URLs, and `vendor` on election_data_sources
 * rows that civic:seed-jurisdictions created, using Google Civic's voterInfoQuery:
 *
 *   - state  rows → hand Civic "<state capital>, <ST>" and take the statewide
 *                   electionAdministrationBody.
 *   - county rows → hand Civic "<jurisdiction_name>, <ST>" and take the
 *                   local_jurisdiction electionAdministrationBody (the office
 *                   that actually runs that county's ballot).
 *
 * voterInfoQuery only returns data when the Voting Information Project has a
 * feed for that address + election — mostly the weeks around an election. When
 * it returns nothing, a `state` row falls back to
 * config('civic.state_election_sites'); a county row is left for the next run.
 *
 * Idempotent. Without --refresh it only fills blank columns and never
 * overwrites a value a human or an earlier run set. `source_of_record` is only
 * upgraded to `google_civic` from a lower-trust value (manual/census/nass).
 *
 * Usage:
 *   php artisan civic:resolve-official-urls
 *   php artisan civic:resolve-official-urls --state=CA --level=county
 *   php artisan civic:resolve-official-urls --election-id=9000 --limit=100
 *   php artisan civic:resolve-official-urls --only-missing --stale-days=30
 *   php artisan civic:resolve-official-urls --refresh --dry-run
 */
class ResolveOfficialElectionUrls extends Command
{
    protected $signature = 'civic:resolve-official-urls
        {--state=          : Limit to one state (two-letter USPS code)}
        {--level=all       : Which rows: state, county, or all}
        {--election-id=    : Force a specific Google Civic election id (else auto-picked per state)}
        {--stale-days=45   : Only touch rows not verified within this many days}
        {--limit=500       : Max rows to process this run}
        {--only-missing    : Only rows still missing elections_home_url or ballot_measures_url}
        {--sleep=250       : Milliseconds to pause between Civic API calls}
        {--refresh         : Overwrite existing authority_name / URLs (default: fill blanks only)}
        {--dry-run         : Report what would change without writing}';

    protected $description = 'Resolve official election-authority names + URLs onto election_data_sources via Google Civic voterInfoQuery.';

    private bool $refresh = false;

    private bool $dryRun = false;

    /** source_of_record values that google_civic data is allowed to replace. */
    private const UPGRADEABLE_SOURCES = ['', 'manual', 'census', 'nass'];

    public function handle(GoogleCivicService $civic): int
    {
        if (! $civic->isConfigured()) {
            $this->error('GOOGLE_CIVIC_API_KEY is not configured — nothing to resolve.');

            return self::FAILURE;
        }

        $stateFilter = $this->option('state') ? strtoupper((string) $this->option('state')) : null;
        $level = strtolower((string) $this->option('level'));
        $forcedElectionId = $this->option('election-id') ? (string) $this->option('election-id') : null;
        $staleDays = (int) $this->option('stale-days');
        $limit = (int) $this->option('limit');
        $onlyMissing = (bool) $this->option('only-missing');
        $sleepMs = max(0, (int) $this->option('sleep'));
        $this->refresh = (bool) $this->option('refresh');
        $this->dryRun = (bool) $this->option('dry-run');

        $levels = match ($level) {
            'state' => ['state'],
            'county' => ['county'],
            default => ['state', 'county'],
        };

        // Auto-pick a Civic election id per state from the nationwide feed,
        // unless the caller forced one.
        $electionIdByState = $forcedElectionId === null ? $this->electionIdsByState($civic) : [];
        if ($forcedElectionId === null) {
            $this->line('Civic knows upcoming elections for '.count($electionIdByState).' state(s).');
            if ($electionIdByState === []) {
                $this->warn('No upcoming elections in the VIP feed right now — voterInfoQuery will likely return nothing until closer to an election.');
            }
        }

        $rows = ElectionDataSource::query()
            ->whereIn('level', $levels)
            ->when($stateFilter, fn ($q) => $q->where('state', $stateFilter))
            ->when($staleDays > 0, fn ($q) => $q->needsVerification($staleDays))
            ->when($onlyMissing, fn ($q) => $q->where(function ($q) {
                $q->whereNull('elections_home_url')->orWhereNull('ballot_measures_url');
            }))
            ->orderBy('level')
            ->orderBy('state')
            ->orderBy('jurisdiction_name')
            ->limit($limit)
            ->get();

        $this->info('Resolving official URLs for '.$rows->count().' row(s)'
            .($this->refresh ? ' [refresh]' : '')
            .($this->dryRun ? ' [DRY RUN]' : ''));

        $resolved = 0;
        $fallback = 0;
        $noData = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $address = $this->representativeAddress($row);
            if ($address === null) {
                $this->warn("  {$row->ocd_id}: no address available — skipped");

                continue;
            }

            $electionId = $forcedElectionId ?? ($electionIdByState[$row->state] ?? null);
            $info = $civic->voterInfoQuery($address, $electionId);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            if ($info === null) {
                if ($row->level === 'state' && $this->applyStateFallback($row)) {
                    $this->line("  {$row->state}  {$row->jurisdiction_name}  [fallback → config]");
                    $fallback++;
                } else {
                    $noData++;
                }

                continue;
            }

            $changes = $this->applyResolved($row, $info);

            if ($changes === []) {
                $unchanged++;

                continue;
            }

            $this->line("  {$row->state}  {$row->jurisdiction_name}  [".implode(', ', array_keys($changes)).']');
            $resolved++;
        }

        $suffix = $this->dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. Resolved: {$resolved} | Config fallback: {$fallback} | No data: {$noData} | Unchanged: {$unchanged}{$suffix}");

        return self::SUCCESS;
    }

    /** @return array<string, string> USPS => Civic election id */
    private function electionIdsByState(GoogleCivicService $civic): array
    {
        $map = [];
        foreach ($civic->listUpcomingElections() as $election) {
            // First one wins — listUpcomingElections() is already ordered by
            // the feed; a forced --election-id is the escape hatch when it isn't
            // the one you want.
            $map[$election['state']] ??= $election['civic_election_id'];
        }

        return array_filter($map);
    }

    private function representativeAddress(ElectionDataSource $row): ?string
    {
        if ($row->level === 'state') {
            $capital = config("civic.state_capitals.{$row->state}");

            return $capital ? "{$capital}, {$row->state}" : null;
        }

        // county / municipal — "Los Angeles County, CA"
        return $row->jurisdiction_name ? "{$row->jurisdiction_name}, {$row->state}" : null;
    }

    /**
     * Fill a state row's elections_home_url from config when Civic gave us
     * nothing. Returns true if it wrote (or would write) something.
     */
    private function applyStateFallback(ElectionDataSource $row): bool
    {
        if ($row->elections_home_url !== null && $row->elections_home_url !== '' && ! $this->refresh) {
            return false;
        }

        $url = config("civic.state_election_sites.{$row->state}");
        if (! $url || $row->elections_home_url === $url) {
            return false;
        }

        if (! $this->dryRun) {
            $row->update(['elections_home_url' => $url]);
        }

        return true;
    }

    /**
     * @param  array{election: ?array, admin_bodies: list<array<string,mixed>>, referendums: list<array<string,mixed>>}  $info
     * @return array<string, mixed> the changes applied (empty = nothing to do)
     */
    private function applyResolved(ElectionDataSource $row, array $info): array
    {
        $body = $this->pickBody($row, $info['admin_bodies']);
        $referendum = $info['referendums'][0] ?? null;

        // Civic often returns an admin body with no URLs (states that publish
        // no VIP electionInfoUrl). Backfill a state row's home URL from config.
        $homeUrl = $body['election_info_url'] ?? null;
        if (($homeUrl === null || $homeUrl === '') && $row->level === 'state') {
            $homeUrl = config("civic.state_election_sites.{$row->state}");
        }

        $changes = $this->mergeBlankable($row, [
            'authority_name' => $body['name'] ?? $body['jurisdiction_name'] ?? null,
            'elections_home_url' => $homeUrl,
            'sample_ballot_url' => $body['ballot_info_url'] ?? null,
            'ballot_measures_url' => $referendum['url'] ?? ($body['ballot_info_url'] ?? null),
            // vendor — inferred from whichever official URL resolves to a known host
            'vendor' => CivicVendorClassifier::fromUrls(
                $body['election_info_url'] ?? null,
                $body['ballot_info_url'] ?? null,
                $referendum['url'] ?? null,
            ),
        ]);

        if ($changes === [] && $row->last_verified_at !== null) {
            return [];
        }

        if (in_array((string) $row->source_of_record, self::UPGRADEABLE_SOURCES, true)) {
            $changes['source_of_record'] = 'google_civic';
        }
        $changes['last_verified_at'] = now();

        if (! empty($info['referendums']) && ($row->notes === null || $row->notes === '')) {
            $name = $info['election']['name'] ?? 'the upcoming election';
            $changes['notes'] = 'Civic: '.count($info['referendums'])." referendum(s) on {$name}.";
        }

        if (! $this->dryRun) {
            $row->update($changes);
        }

        // Don't report the housekeeping-only columns as a meaningful change.
        unset($changes['last_verified_at']);

        return $changes;
    }

    /**
     * Keep only the candidate values that are non-empty and would fill a blank
     * column (or any column, under --refresh) with a genuinely new value.
     *
     * @param  array<string, mixed>  $candidates  column => proposed value
     * @return array<string, mixed>
     */
    private function mergeBlankable(ElectionDataSource $row, array $candidates): array
    {
        $changes = [];

        foreach ($candidates as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $row->{$key};
            $isBlank = $current === null || $current === '';
            if (($isBlank || $this->refresh) && $current !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    /**
     * @param  list<array<string, mixed>>  $bodies
     * @return array<string, mixed>
     */
    private function pickBody(ElectionDataSource $row, array $bodies): array
    {
        $wantScope = $row->level === 'state' ? 'state' : 'local';

        foreach ($bodies as $body) {
            if (($body['scope'] ?? null) === $wantScope) {
                return $body;
            }
        }

        // County with no local body in the feed → fall back to the state body.
        return $bodies[0] ?? [];
    }
}
