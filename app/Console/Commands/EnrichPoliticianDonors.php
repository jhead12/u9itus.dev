<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Services\FECService;
use App\Services\OpenSecretsService;
use App\Services\PacAffiliationClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichPoliticianDonors extends Command
{
    protected $signature = 'politicians:enrich-donors
        {--limit=200         : Max politicians to process per run}
        {--stale-hours=48    : Re-enrich snapshots older than N hours}
        {--politician=       : Process a single politician by ID or slug}
        {--force             : Re-enrich even if snapshot is fresh}
        {--upcoming-only     : Only target candidates currently running (is_running_candidate)}
        {--dry-run           : Report what would be fetched without writing}';

    protected $description = 'Fetch and cache donor/sponsor data from OpenSecrets and FEC for politician profiles.';

    public function handle(OpenSecretsService $openSecrets, FECService $fec, PacAffiliationClassifier $pacClassifier): int
    {
        $limit      = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force      = (bool) $this->option('force');
        $dryRun     = (bool) $this->option('dry-run');
        $singleId   = $this->option('politician');
        $upcomingOnly = (bool) $this->option('upcoming-only');

        // Per-process FEC throttle/rate-limit state — start the batch clean so
        // a short-circuit tripped in this run reflects only this run's 429s.
        FECService::resetTelemetry();

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->when($upcomingOnly, fn ($q) => $q->where('is_running_candidate', true));

        if ($singleId) {
            $query->where(fn ($q) =>
                $q->where('id', $singleId)->orWhere('slug', $singleId)
            );
        } else {
            // Prioritise politicians with missing or stale snapshots
            $query->where(function ($q) use ($staleHours, $force) {
                $q->whereDoesntHave('donorSnapshot');
                if (! $force) {
                    $q->orWhereHas('donorSnapshot', fn ($sq) =>
                        $sq->where('enriched_at', '<', now()->subHours($staleHours))
                           ->orWhereNull('enriched_at')
                    );
                }
            })->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians need enrichment.');
            return self::SUCCESS;
        }

        $this->info("Enriching {$politicians->count()} politician(s)...");

        $enriched = 0;
        $failed   = 0;

        foreach ($politicians as $politician) {
            $this->line("  → {$politician->full_name} (id={$politician->id})");

            try {
                $snapshot = $this->buildSnapshot($politician, $openSecrets, $fec, $pacClassifier);

                if ($dryRun) {
                    $contributors = count($snapshot['top_contributors'] ?? []);
                    $industries   = count($snapshot['top_industries'] ?? []);
                    $hasFec       = ! empty($snapshot['fec_summary']);
                    $pacMatches   = count($snapshot['pac_affiliations'] ?? []);
                    $profileUrl   = $snapshot['opensecrets_source_url'] ?? ($snapshot['profile_url'] ?? null);
                    $this->line("    [dry-run] contributors={$contributors} industries={$industries} fec=" . ($hasFec ? 'yes' : 'no') . " pac_affiliations={$pacMatches} url=" . ($profileUrl ? "yes ({$profileUrl})" : 'no'));
                    $enriched++;
                    continue;
                }

                PoliticianDonorSnapshot::updateOrCreate(
                    ['politician_id' => $politician->id],
                    array_merge($snapshot, ['enriched_at' => now()])
                );

                $enriched++;
                $this->line("    ✓ Saved");

            } catch (\Throwable $e) {
                $failed++;
                $this->warn("    ✗ Failed: " . $e->getMessage());
                Log::warning('politicians:enrich-donors failed for politician', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Enriched: {$enriched} | Failed: {$failed}");

        if ($fec->isConfigured()) {
            $calls       = FECService::getHttpCallCount();
            $rateLimited = FECService::getRateLimitCount();
            $tripped     = FECService::wasShortCircuited() ? 'yes' : 'no';
            $this->info("FEC telemetry: {$calls} API call(s), {$rateLimited} rate-limited (429), short-circuit tripped: {$tripped}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSnapshot(
        Politician $politician,
        OpenSecretsService $openSecrets,
        FECService $fec,
        PacAffiliationClassifier $pacClassifier,
    ): array {
        // Preserve the last good OpenSecrets scrape when this run finds nothing
        // (e.g. blocked by a bot-check) — a stale-but-real link/summary beats
        // silently wiping it back to null every time the scraper gets blocked.
        $priorSnapshot = $politician->donorSnapshot;

        $snapshot = [
            'top_contributors'       => $priorSnapshot->top_contributors ?? null,
            'top_industries'         => $priorSnapshot->top_industries ?? null,
            'fec_summary'            => null,
            'opensecrets_summary'    => $priorSnapshot->opensecrets_summary ?? null,
            'outside_spending'       => null,
            'pac_affiliations'       => $priorSnapshot->pac_affiliations ?? null,
            'opensecrets_source_url' => $priorSnapshot->opensecrets_source_url ?? null,
            'fec_source_url'         => null,
            'election_cycle'         => (int) date('Y') % 2 === 0 ? (int) date('Y') : (int) date('Y') - 1,
        ];

        // ── OpenSecrets (scraped — no API key required) ───────────────────────
        try {
            // Clear caches when --force is set so a fresh scrape + FEC fetch run.
            // Without clearing the FEC cache, a forced re-enrich reuses the
            // 24h-cached candidate id/filings — which matters now that outside
            // spending depends on a freshly resolved fec_candidate_id.
            if ($this->option('force')) {
                $openSecrets->clearCache($politician);
                $fec->clearCache($politician);
            }
            $data = $openSecrets->fetchCampaignFinanceData($politician);
            if (is_array($data)) {
                $snapshot['top_contributors']       = $data['top_contributors'] ?? null;
                $snapshot['top_industries']         = $data['top_industries']   ?? null;
                $snapshot['opensecrets_summary']    = $data['candidate_summary'] ?? null;
                $snapshot['opensecrets_source_url'] = $data['profile_url']      ?? null;

                // Derived: flag known advocacy-group PACs (e.g. AIPAC-aligned) found
                // among the scraped top_contributors. No additional source is scraped.
                $pacMatches = $pacClassifier->classify($snapshot['top_contributors'] ?? []);
                $snapshot['pac_affiliations'] = $pacMatches !== [] ? $pacMatches : null;

                $this->line("    OpenSecrets: " . count($data['top_contributors'] ?? []) . " contributors, " . count($data['top_industries'] ?? []) . " industries, " . count($pacMatches) . " pac affiliation match(es)");
            } else {
                $this->line("    OpenSecrets: no data returned");
            }
        } catch (\Throwable $e) {
            $this->warn("    OpenSecrets error: " . $e->getMessage());
            Log::info('OpenSecrets scrape skipped', ['politician_id' => $politician->id, 'error' => $e->getMessage()]);
        }

        // ── FEC ───────────────────────────────────────────────────────────────
        if ($fec->isConfigured() && $fec->isFederalCandidate($politician)) {
            try {
                $data = $fec->getDisplayData($politician);
                if (is_array($data)) {
                    $snapshot['fec_summary']    = $data['sections']['summary'] ?? null;
                    $snapshot['fec_source_url'] = $data['source_url'] ?? null;
                }

                // Direct PAC-to-committee contributions from FEC Schedule A,
                // in addition to whatever OpenSecrets' scrape happened to surface.
                // fetchCandidateFilings() (called within getDisplayData()) persists
                // fec_candidate_id on this same model instance when first resolved.
                $candidateId = $politician->fec_candidate_id;
                if ($candidateId) {
                    $committees = $fec->getCandidateCommittees($candidateId);
                    $fecContributors = [];
                    foreach ($committees as $committee) {
                        if (empty($committee['committee_id'])) {
                            continue;
                        }
                        $fecContributors = array_merge(
                            $fecContributors,
                            $fec->getCommitteeContributions($committee['committee_id'])
                        );
                    }

                    if ($fecContributors !== []) {
                        $fecPacMatches = $pacClassifier->classify($fecContributors);

                        if ($fecPacMatches !== []) {
                            $existing = $snapshot['pac_affiliations'] ?? [];
                            $seen = [];
                            foreach ($existing as $match) {
                                $seen[$match['group'] . '|' . strtolower($match['matched_name'])] = true;
                            }

                            foreach ($fecPacMatches as $match) {
                                $key = $match['group'] . '|' . strtolower($match['matched_name']);
                                if (!isset($seen[$key])) {
                                    $existing[] = $match;
                                    $seen[$key] = true;
                                }
                            }

                            $snapshot['pac_affiliations'] = $existing;
                            $this->line("    FEC: " . count($fecPacMatches) . " pac affiliation match(es) from Schedule A");
                        }
                    }

                    // Independent expenditures (Schedule E): who is spending
                    // to support/oppose this candidate, outside their campaign.
                    // Target the candidate's actual most-recent FEC cycle (from
                    // /candidate/{id}/.cycles) rather than the default "current
                    // even year", so a recently-retired candidate still surfaces
                    // the IE data from the cycle they actually ran in.
                    $latestCycle = $fec->getLatestCycle($candidateId);
                    if ($latestCycle) {
                        $snapshot['election_cycle'] = $latestCycle;
                    }
                    $outsideSpending = $fec->getOutsideSpending($candidateId, (int) $snapshot['election_cycle']);
                    if ($outsideSpending !== []) {
                        $snapshot['outside_spending'] = $outsideSpending;
                        $this->line("    FEC: " . count($outsideSpending) . " outside spender(s) from Schedule E");
                    }
                }
            } catch (\Throwable $e) {
                Log::info('FEC enrichment skipped', ['politician_id' => $politician->id, 'error' => $e->getMessage()]);
            }
        }

        return $snapshot;
    }
}
