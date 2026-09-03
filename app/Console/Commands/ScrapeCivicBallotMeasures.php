<?php

namespace App\Console\Commands;

use App\Models\BallotMeasure;
use App\Models\ElectionDataSource;
use App\Services\Civic\MeasureAdapterRegistry;
use App\Support\BallotMeasureWriter;
use Illuminate\Console\Command;

/**
 * The HTML-adapter half of step 3 (see doc/CIVIC_SOURCE_REGISTRY.md).
 *
 * For registry rows that have a vendor / platform_template but no Voting
 * Information Project feed, resolve a BallotMeasureAdapter and scrape the
 * jurisdiction's own ballot-measures page into `ballot_measures`.
 *
 * Runs after civic:resolve-official-urls (needs a URL + vendor) and
 * civic:verify-sources (skips rows already known dead/blocked, or robots-
 * disallowed). Everything it finds still goes through BallotMeasureWriter, so
 * it only fills blanks and never rewrites a measure's `source`.
 *
 * Usage:
 *   php artisan civic:scrape-measures
 *   php artisan civic:scrape-measures --vendor=voteinfo_net --state=CA
 *   php artisan civic:scrape-measures --only-empty --election-date=2026-11-03
 *   php artisan civic:scrape-measures --refresh --dry-run
 */
class ScrapeCivicBallotMeasures extends Command
{
    protected $signature = 'civic:scrape-measures
        {--state=          : Limit to one state (two-letter USPS code)}
        {--vendor=         : Limit to rows with this vendor slug}
        {--election-date=  : YYYY-MM-DD to stamp on measures whose page has no parseable date}
        {--only-empty      : Skip states that already have upcoming ballot_measures}
        {--limit=300       : Max registry rows to scrape this run}
        {--sleep=500       : Milliseconds to pause between rows}
        {--refresh         : Overwrite mapped columns on existing measures}
        {--dry-run         : Report without writing}';

    protected $description = 'Scrape ballot measures from jurisdiction web pages (HTML adapters) for registry rows with no VIP feed.';

    public function __construct(
        private readonly MeasureAdapterRegistry $registry,
        private readonly BallotMeasureWriter $writer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $stateFilter = $this->option('state') ? strtoupper((string) $this->option('state')) : null;
        $vendorFilter = $this->option('vendor') ? (string) $this->option('vendor') : null;
        $electionDateOverride = $this->option('election-date') ? (string) $this->option('election-date') : null;
        $onlyEmpty = (bool) $this->option('only-empty');
        $limit = (int) $this->option('limit');
        $sleepMs = max(0, (int) $this->option('sleep'));
        $refresh = (bool) $this->option('refresh');
        $dryRun = (bool) $this->option('dry-run');

        $rows = ElectionDataSource::query()
            ->where(function ($q) {
                // Rows whose OWN page we fetch: need a live, allowed URL and a
                // vendor to pick an adapter.
                $q->where(fn ($q) => $q
                    ->whereNotNull('vendor')
                    ->where(fn ($q) => $q->whereNotNull('ballot_measures_url')->orWhereNotNull('sample_ballot_url'))
                    ->where(fn ($q) => $q->whereNull('robots_ok')->orWhere('robots_ok', true))
                    ->whereNotIn('scrape_status', ['dead', 'blocked']))
                    // Rows with a self-sufficient adapter named directly
                    // (e.g. platform_template = 'wikipedia', which fetches its
                    // own source and ignores the row's URL / health).
                    ->orWhereNotNull('platform_template');
            })
            ->when($stateFilter, fn ($q) => $q->where('state', $stateFilter))
            ->when($vendorFilter, fn ($q) => $q->where('vendor', $vendorFilter))
            ->orderBy('state')
            ->orderBy('jurisdiction_name')
            ->limit($limit)
            ->get();

        $this->info('Scraping measures for '.$rows->count().' registry row(s)'
            .($refresh ? ' [refresh]' : '').($dryRun ? ' [DRY RUN]' : ''));

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $rowsScraped = 0;
        $rowsWithMeasures = 0;
        $noAdapter = 0;

        foreach ($rows as $row) {
            $adapter = $this->registry->for($row);
            if ($adapter === null) {
                $noAdapter++;

                continue;
            }

            if ($onlyEmpty && $this->stateHasUpcomingMeasures($row->state)) {
                continue;
            }

            $rowsScraped++;
            $measures = $adapter->fetchMeasures($row);
            $county = $row->level === 'county' ? $row->jurisdiction_name : null;

            foreach ($measures as $measure) {
                $attrs = BallotMeasureWriter::normalize(
                    $measure,
                    state: $row->state,
                    county: $county,
                    electionDate: ($measure['election_date'] ?? null) ?: $electionDateOverride,
                    source: 'html_scrape',
                    fallbackUrl: $row->ballot_measures_url ?: $row->sample_ballot_url,
                );
                if ($attrs === null) {
                    continue;
                }

                $result = $this->writer->upsert($attrs, $refresh, $dryRun);
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $unchanged++,
                };
                if ($result !== 'unchanged') {
                    $this->line("  [{$row->state}] {$attrs['title']} ({$result})");
                }
            }

            if ($measures !== []) {
                $rowsWithMeasures++;
            }
            $this->stampRow($row, $measures !== [], $dryRun);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $suffix = $dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. Measures created: {$created} | updated: {$updated} | unchanged: {$unchanged}"
            ." — {$rowsWithMeasures}/{$rowsScraped} rows yielded measures, {$noAdapter} with no adapter{$suffix}");

        return self::SUCCESS;
    }

    private function stateHasUpcomingMeasures(string $state): bool
    {
        return BallotMeasure::query()
            ->where('state', $state)
            ->where('status', 'upcoming')
            ->exists();
    }

    private function stampRow(ElectionDataSource $row, bool $hadMeasures, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $update = ['last_scraped_at' => now()];
        if ($hadMeasures) {
            $update['scrape_status'] = 'ok';
        }

        $row->update($update);
    }
}
