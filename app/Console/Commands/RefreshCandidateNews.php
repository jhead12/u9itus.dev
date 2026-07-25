<?php

namespace App\Console\Commands;

use App\Models\CandidateNewsArticle;
use App\Models\CandidateNewsRunLog;
use App\Models\Politician;
use App\Services\CandidateNewsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshCandidateNews extends Command
{
    protected $signature = 'candidates:refresh-news
        {--stale-hours=6      : Only refresh candidates whose news is older than this many hours}
        {--limit=50           : Maximum number of candidates to process per run}
        {--politician=        : Refresh a single politician by ID}
        {--state=              : Two-letter state code — limit to one state}
        {--upcoming-only      : Only target candidates currently running (is_running_candidate), instead of ordering by traffic}
        {--dry-run            : Report which candidates would be refreshed without fetching}';

    protected $description = 'Fetch and cache recent news articles for candidates from Google News RSS (and optional NewsAPI/GNews).';

    public function handle(CandidateNewsService $newsService): int
    {
        $staleHours  = (int) $this->option('stale-hours');
        $limit       = (int) $this->option('limit');
        $politicianId = $this->option('politician');
        $state       = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $upcomingOnly = (bool) $this->option('upcoming-only');
        $dryRun      = (bool) $this->option('dry-run');

        $staleThreshold = now()->subHours($staleHours);

        // ── Build candidate set ──────────────────────────────────────────────

        if ($politicianId !== null) {
            $politicians = Politician::where('id', (int) $politicianId)
                ->where('is_active', true)
                ->get();
        } else {
            // Find politicians whose news has never been fetched or is stale.
            $recentlyFetched = CandidateNewsArticle::query()
                ->whereNotNull('politician_id')
                ->where('scraped_at', '>=', $staleThreshold)
                ->pluck('politician_id')
                ->unique();

            $politicians = Politician::query()
                ->where('is_active', true)
                ->whereNotIn('id', $recentlyFetched)
                ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state]))
                ->when(
                    $upcomingOnly,
                    // Target candidates on the ballot for the upcoming cycle, regardless
                    // of traffic — a brand-new low-traffic candidate shouldn't wait
                    // behind high-traffic incumbents in the default queue.
                    fn ($q) => $q->where('is_running_candidate', true),
                    fn ($q) => $q->orderByDesc('total_views_received') // prioritise high-traffic profiles
                )
                ->limit($limit)
                ->get();
        }

        $total    = $politicians->count();
        $refreshed = 0;
        $failed   = 0;

        $this->info("Candidates queued: {$total}" . ($dryRun ? ' [dry-run]' : ''));

        if ($dryRun) {
            $politicians->each(fn (Politician $p) => $this->line("  · [{$p->id}] {$p->full_name}"));

            return self::SUCCESS;
        }

        $runLog = CandidateNewsRunLog::create([
            'command_name' => 'candidates:refresh-news',
            'status' => 'running',
            'queued_count' => $total,
            'started_at' => now(),
        ]);

        try {
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach ($politicians as $politician) {
                try {
                    $newsService->fetchAndPersist(
                        politicianId: $politician->id,
                        candidateName: (string) $politician->full_name,
                        state: $politician->state,
                        websiteUrl: $politician->website_url,
                    );
                    $refreshed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('candidates:refresh-news failed for politician', [
                        'politician_id' => $politician->id,
                        'name'          => $politician->full_name,
                        'error'         => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->info("Done. Refreshed: {$refreshed} | Failed: {$failed}");

            // Return SUCCESS even when individual candidates fail — a FK mismatch or
            // transient HTTP error for one record should not fail the entire CI job.
            // Failures are logged as warnings above for investigation. The run log
            // (not the exit code) is what the health check watches.
            $runLog->markSuccess(self::SUCCESS, ['refreshed' => $refreshed, 'failed' => $failed]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $runLog->markFailed(self::FAILURE, $e->getMessage());

            throw $e;
        }
    }
}
