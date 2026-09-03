<?php

namespace App\Console\Commands;

use App\Models\BallotMeasure;
use App\Models\ElectionDataSource;
use App\Services\GoogleCivicService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Step 3 of the civic source pipeline (see doc/CIVIC_SOURCE_REGISTRY.md).
 *
 * Walks election_data_sources rows and ingests the ballot measures Google Civic
 * (the Voting Information Project feed) already returns as structured data —
 * `Referendum` contests from voterInfoQuery — straight into `ballot_measures`.
 *
 * This is the cheap, no-scraping path. Jurisdictions with no VIP feed are left
 * for a `platform_template` HTML adapter (not built yet); this command records
 * which those are via the run summary rather than trying to scrape them.
 *
 * Dedup identity matches ImportBallotMeasures / AdminBallotMeasureController:
 * state + title + election_date (compared by calendar day). Without --refresh
 * an existing measure only has blank columns filled; with it, every mapped
 * column is overwritten.
 *
 * Usage:
 *   php artisan civic:pull-measures
 *   php artisan civic:pull-measures --state=CA --level=county
 *   php artisan civic:pull-measures --election-id=9468 --limit=100
 *   php artisan civic:pull-measures --refresh --dry-run
 */
class PullCivicBallotMeasures extends Command
{
    protected $signature = 'civic:pull-measures
        {--state=        : Limit to one state (two-letter USPS code)}
        {--level=all     : Which rows: state, county, or all}
        {--election-id=  : Force a Google Civic election id (else auto-picked per state)}
        {--limit=500     : Max registry rows to scan this run}
        {--sleep=250     : Milliseconds to pause between Civic API calls}
        {--refresh       : Overwrite mapped columns on existing measures (default: fill blanks only)}
        {--dry-run       : Report what would change without writing}';

    protected $description = 'Ingest ballot measures from Google Civic Referendum contests into ballot_measures, per election_data_sources row.';

    private bool $refresh = false;

    private bool $dryRun = false;

    public function handle(GoogleCivicService $civic): int
    {
        if (! $civic->isConfigured()) {
            $this->error('GOOGLE_CIVIC_API_KEY is not configured — nothing to pull.');

            return self::FAILURE;
        }

        $stateFilter = $this->option('state') ? strtoupper((string) $this->option('state')) : null;
        $level = strtolower((string) $this->option('level'));
        $forcedElectionId = $this->option('election-id') ? (string) $this->option('election-id') : null;
        $limit = (int) $this->option('limit');
        $sleepMs = max(0, (int) $this->option('sleep'));
        $this->refresh = (bool) $this->option('refresh');
        $this->dryRun = (bool) $this->option('dry-run');

        $levels = match ($level) {
            'state' => ['state'],
            'county' => ['county'],
            default => ['state', 'county'],
        };

        $electionIdByState = $forcedElectionId === null ? $this->electionIdsByState($civic) : [];

        $rows = ElectionDataSource::query()
            ->whereIn('level', $levels)
            ->when($stateFilter, fn ($q) => $q->where('state', $stateFilter))
            ->orderBy('level')
            ->orderBy('state')
            ->orderBy('jurisdiction_name')
            ->limit($limit)
            ->get();

        $this->info('Pulling measures for '.$rows->count().' registry row(s)'
            .($this->refresh ? ' [refresh]' : '')
            .($this->dryRun ? ' [DRY RUN]' : ''));

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $rowsWithMeasures = 0;
        $rowsWithFeed = 0;

        foreach ($rows as $row) {
            $address = $this->representativeAddress($row);
            if ($address === null) {
                continue;
            }

            $electionId = $forcedElectionId ?? ($electionIdByState[$row->state] ?? null);
            $info = $civic->voterInfoQuery($address, $electionId);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            if ($info === null) {
                continue;
            }
            $rowsWithFeed++;

            $referendums = $info['referendums'] ?? [];
            $electionDay = $info['election']['day'] ?? null;

            foreach ($referendums as $referendum) {
                $attrs = $this->mapReferendum($row, $referendum, $electionDay);
                if ($attrs === null) {
                    continue;
                }

                $result = $this->upsertMeasure($attrs);
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $unchanged++,
                };

                if ($result !== 'unchanged') {
                    $this->line("  [{$attrs['state']}] {$attrs['title']} ({$result})");
                }
            }

            // The query succeeded even when it found nothing — stamp the row so
            // reporting knows it was checked. 'ok' (a working measures source)
            // only when measures were actually present.
            $this->stampRegistryRow($row, hadMeasures: $referendums !== []);
            if ($referendums !== []) {
                $rowsWithMeasures++;
            }
        }

        $suffix = $this->dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. Measures created: {$created} | updated: {$updated} | unchanged: {$unchanged}"
            ." — {$rowsWithMeasures}/{$rowsWithFeed} rows with a feed had measures{$suffix}");

        return self::SUCCESS;
    }

    /** @return array<string, string> USPS => Civic election id (first per state wins) */
    private function electionIdsByState(GoogleCivicService $civic): array
    {
        $map = [];
        foreach ($civic->listUpcomingElections() as $election) {
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

        return $row->jurisdiction_name ? "{$row->jurisdiction_name}, {$row->state}" : null;
    }

    /**
     * Map one Civic Referendum contest onto ballot_measures columns.
     *
     * @param  array<string, mixed>  $r  a voterInfoQuery() referendum entry
     * @return array<string, mixed>|null null when there's no usable title
     */
    private function mapReferendum(ElectionDataSource $row, array $r, ?string $electionDay): ?array
    {
        $title = trim((string) ($r['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $text = trim((string) ($r['text'] ?? ''));
        $subtitle = trim((string) ($r['subtitle'] ?? ''));

        // "County" comes from the contest district when Civic scopes it, else
        // the registry row for a county-level source.
        $county = $r['district_name'] ?? ($row->level === 'county' ? $row->jurisdiction_name : null);

        return [
            'state' => $row->state,
            'county' => $county ? Str::limit((string) $county, 100, '') : null,
            'measure_number' => $this->extractMeasureNumber($title),
            'title' => Str::limit($title, 255, ''),
            'summary' => $subtitle !== '' ? $subtitle : ($text !== '' ? Str::limit($text, 1000) : null),
            // Civic gives the ballot question text, not a plain-language "what a
            // Yes/No vote does" — left for a later enrichment pass.
            'yes_meaning' => null,
            'no_meaning' => null,
            'election_date' => $electionDay,
            'status' => 'upcoming',
            'source' => 'google_civic',
            'source_url' => $r['url'] ?? $row->ballot_measures_url ?? $row->sample_ballot_url,
        ];
    }

    /** "Proposition 1: …" / "Measure A —" / "Question 3" → "1" / "A" / "3" */
    private function extractMeasureNumber(string $title): ?string
    {
        if (preg_match('/\b(?:prop(?:osition)?|measure|question|amendment|issue|referendum)\s+([A-Z0-9]{1,4})\b/i', $title, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsertMeasure(array $attrs): string
    {
        $existing = BallotMeasure::query()
            ->where('state', $attrs['state'])
            ->where('title', $attrs['title'])
            ->when(
                $attrs['election_date'] !== null,
                fn ($q) => $q->whereDate('election_date', $attrs['election_date']),
                fn ($q) => $q->whereNull('election_date'),
            )
            ->first();

        if ($existing === null) {
            if (! $this->dryRun) {
                BallotMeasure::create($attrs);
            }

            return 'created';
        }

        $changes = [];
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $existing->{$key};
            $isBlank = $current === null || $current === '';
            if (($isBlank || $this->refresh) && $current != $value) {
                $changes[$key] = $value;
            }
        }

        // Never let this pass clobber a human/Ballotpedia-authored measure's
        // provenance just because we matched it.
        unset($changes['source']);

        if ($changes === []) {
            return 'unchanged';
        }

        if (! $this->dryRun) {
            $existing->update($changes);
        }

        return 'updated';
    }

    private function stampRegistryRow(ElectionDataSource $row, bool $hadMeasures): void
    {
        if ($this->dryRun) {
            return;
        }

        $update = ['last_scraped_at' => now()];
        if ($hadMeasures) {
            $update['scrape_status'] = 'ok'; // a confirmed working measures source
        }

        $row->update($update);
    }
}
