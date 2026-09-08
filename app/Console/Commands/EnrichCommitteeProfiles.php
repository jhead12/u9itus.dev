<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeProfile;
use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Services\FECService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Build/refresh the enriched `committee_profiles` row for every committee in
 * the registry — the read side of the PAC directory (/pacs/{id}).
 *
 * Mirrors politicians:enrich-donors: a shared DB lock (both Railway's
 * scheduler and a GitHub Actions cron invoke this against the same prod DB),
 * per-row try/catch, FEC telemetry reporting, --dry-run.
 *
 * The candidate set is committees that have been *seen* spending against a
 * tracked candidate — either they already have a `committees` row, or their
 * ID appears in a donor snapshot's `outside_spending` and gets registered
 * here first. We never enumerate all of FEC.
 */
class EnrichCommitteeProfiles extends Command
{
    protected $signature = 'committees:enrich-profiles
        {--limit=150         : Max committees to process per run}
        {--committee=        : Process a single committee by FEC ID}
        {--stale-hours=168   : Re-enrich profiles older than N hours}
        {--cycle=            : FEC 2-year cycle to pull (default: current even year)}
        {--force             : Re-enrich even if the profile is fresh}
        {--dry-run           : Report what would be fetched without writing}';

    protected $description = 'Fetch and cache FEC detail, totals and independent-expenditure data for PAC/committee directory pages.';

    public function handle(FECService $fec): int
    {
        if (! $fec->isConfigured()) {
            $this->warn('FEC_API_KEY not configured — nothing to do.');
            return self::SUCCESS;
        }

        $lock = Cache::lock('committees:enrich-profiles', 3600);
        if (! $lock->get()) {
            $this->warn('Another committees:enrich-profiles run is already in progress — exiting.');
            Log::info('committees:enrich-profiles: skipped, lock held by a concurrent run');
            return self::SUCCESS;
        }

        try {
            return $this->runEnrichment($fec);
        } finally {
            $lock->release();
        }
    }

    protected function runEnrichment(FECService $fec): int
    {
        FECService::resetTelemetry();

        $limit      = (int) $this->option('limit');
        $staleHours = (int) $this->option('stale-hours');
        $force      = (bool) $this->option('force');
        $dryRun     = (bool) $this->option('dry-run');
        $single     = $this->option('committee');
        $cycle      = $this->option('cycle')
            ? (int) $this->option('cycle')
            : ((int) date('Y') % 2 === 0 ? (int) date('Y') : (int) date('Y') - 1);

        if ($dryRun) {
            $this->info('[dry-run] No data will be written.');
        }

        if ($single) {
            $single = strtoupper(trim($single));
            Committee::firstOrCreate(
                ['fec_committee_id' => $single],
                ['first_seen_at' => now(), 'last_seen_at' => now()],
            );
        } else {
            $this->registerCommitteesFromSnapshots();
        }

        $query = Committee::query()
            ->when($single, fn ($q) => $q->where('fec_committee_id', $single))
            ->when(! $single, function ($q) use ($staleHours, $force) {
                $q->where(function ($sub) use ($staleHours, $force) {
                    $sub->whereDoesntHave('profile');
                    if (! $force) {
                        $sub->orWhereHas('profile', fn ($pq) =>
                            $pq->whereNull('enriched_at')
                               ->orWhere('enriched_at', '<', now()->subHours($staleHours))
                        );
                    }
                })
                // A committee still actively filing is the most useful to keep current.
                ->orderByDesc('last_seen_at')
                ->limit($limit);
            });

        $committees = $query->get();

        if ($committees->isEmpty()) {
            $this->info('No committees need enrichment.');
            return self::SUCCESS;
        }

        $this->info("Enriching {$committees->count()} committee(s) for cycle {$cycle}...");

        $enriched = 0;
        $failed = 0;

        foreach ($committees as $committee) {
            $this->line("  → {$committee->fec_committee_id} " . ($committee->name ?: '(unresolved name)'));

            try {
                $data = $this->buildProfile($committee, $fec, $cycle);

                if ($dryRun) {
                    $races = count($data['spending_by_race'] ?? []);
                    $donors = count($data['top_donors'] ?? []);
                    $this->line("    [dry-run] type=" . ($data['committee_type_full'] ?? '?')
                        . " races={$races} donors={$donors} ind_exp=" . ($data['independent_expenditures'] ?? 'n/a'));
                    $enriched++;
                    continue;
                }

                CommitteeProfile::updateOrCreate(
                    ['committee_id' => $committee->id],
                    array_merge($data, [
                        'fec_committee_id' => $committee->fec_committee_id,
                        'enriched_at' => now(),
                    ])
                );

                // Backfill a resolved name into the registry if we now have one.
                if (! empty($data['_committee_name'])
                    && $data['_committee_name'] !== $committee->name
                    && $data['_committee_name'] !== $committee->fec_committee_id) {
                    $committee->forceFill([
                        'name' => $data['_committee_name'],
                        'name_resolved_at' => now(),
                    ])->save();
                }

                $enriched++;
                $this->line('    ✓ Saved');
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('    ✗ Failed: ' . $e->getMessage());
                Log::warning('committees:enrich-profiles failed for committee', [
                    'committee_id' => $committee->id,
                    'fec_committee_id' => $committee->fec_committee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Enriched: {$enriched} | Failed: {$failed}");

        $calls = FECService::getHttpCallCount();
        $rateLimited = FECService::getRateLimitCount();
        $tripped = FECService::wasShortCircuited() ? 'yes' : 'no';
        $this->info("FEC telemetry: {$calls} API call(s), {$rateLimited} rate-limited (429), short-circuit tripped: {$tripped}");

        return self::SUCCESS;
    }

    /**
     * Ensure any committee ID that has shown up in a donor snapshot's outside
     * spending has a registry row, so the directory covers every committee the
     * site already surfaces on a politician profile.
     */
    protected function registerCommitteesFromSnapshots(): void
    {
        $seen = [];
        PoliticianDonorSnapshot::query()
            ->whereNotNull('outside_spending')
            ->select('outside_spending')
            ->chunk(200, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    foreach ((array) $row->outside_spending as $spender) {
                        $id = $spender['committee_id'] ?? null;
                        if (is_string($id) && preg_match('/^[A-Z]\d{8}$/', $id)) {
                            $seen[$id] = true;
                        }
                    }
                }
            });

        if ($seen === []) {
            return;
        }

        $existing = Committee::query()
            ->whereIn('fec_committee_id', array_keys($seen))
            ->pluck('fec_committee_id')
            ->all();

        $missing = array_diff(array_keys($seen), $existing);
        foreach ($missing as $id) {
            Committee::create([
                'fec_committee_id' => $id,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        if ($missing !== []) {
            $this->line('  Registered ' . count($missing) . ' committee(s) newly seen in donor snapshots.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProfile(Committee $committee, FECService $fec, int $cycle): array
    {
        $id = $committee->fec_committee_id;

        $detail = $fec->getCommitteeDetail($id) ?? [];
        $totals = $fec->getCommitteeTotals($id, $cycle) ?? [];
        $indExp = $fec->getCommitteeIndependentExpenditures($id, $cycle);
        $donors = $fec->getCommitteeContributions($id);

        return [
            '_committee_name' => $detail['name'] ?? null,
            'committee_type' => $detail['committee_type'] ?? null,
            'committee_type_full' => $detail['committee_type_full'] ?? null,
            'designation' => $detail['designation'] ?? null,
            'designation_full' => $detail['designation_full'] ?? null,
            'organization_type_full' => $detail['organization_type_full'] ?? null,
            'party' => $detail['party'] ?? null,
            'is_super_pac' => (bool) ($detail['is_super_pac'] ?? false),
            'is_hybrid' => (bool) ($detail['is_hybrid'] ?? false),
            'treasurer_name' => $detail['treasurer_name'] ?? null,
            'street' => $detail['street'] ?? null,
            'city' => $detail['city'] ?? null,
            'state' => $detail['state'] ?? null,
            'zip' => $detail['zip'] ?? null,
            'fec_website_url' => $detail['website'] ?? null,

            'cycle' => $totals['cycle'] ?? $cycle,
            'total_receipts' => $this->num($totals['receipts'] ?? null),
            'total_disbursements' => $this->num($totals['disbursements'] ?? null),
            'cash_on_hand' => $this->num($totals['cash_on_hand'] ?? null),
            'debts_owed' => $this->num($totals['debts_owed'] ?? null),
            'independent_expenditures' => $this->num($totals['independent_expenditures'] ?? null),
            'coverage_end_date' => $totals['coverage_end_date'] ?? null,

            'top_donors' => $this->normaliseDonors($donors),
            'spending_by_race' => $this->rollUpByRace($indExp),
            'recent_expenditures' => $this->recentLineItems($indExp),
        ];
    }

    private function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** FEC office code → label; passes through anything already spelled out. */
    private function officeLabel(?string $office): ?string
    {
        return match (strtoupper((string) $office)) {
            'H' => 'House',
            'S' => 'Senate',
            'P' => 'President',
            '' => null,
            default => $office,
        };
    }

    /**
     * FEC Schedule A returns one row per receipt, so a committee's biggest
     * backer shows up many times — collapse to one row per donor, summing the
     * amounts, and re-rank.
     *
     * @param array<int, array{name?: string, total?: mixed}> $donors
     */
    private function normaliseDonors(array $donors): array
    {
        $byName = [];
        foreach ($donors as $d) {
            $name = trim((string) ($d['name'] ?? ''));
            if ($name === '' || strtoupper($name) === 'UNKNOWN') {
                continue;
            }
            $key = Str::title($name);
            $byName[$key] = ($byName[$key] ?? 0.0) + Money::parseLoose((string) ($d['total'] ?? ''));
        }

        arsort($byName);

        $rows = [];
        foreach (array_slice($byName, 0, 10, true) as $name => $total) {
            $rows[] = ['name' => $name, 'total' => $total > 0 ? '$' . number_format($total) : null];
        }

        return $rows;
    }

    /**
     * Roll independent-expenditure line items up into one row per candidate,
     * with support vs oppose totals and a resolved local politician_id where
     * the candidate is on the platform.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function rollUpByRace(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $byCand = [];
        foreach ($items as $it) {
            $key = $it['candidate_id'] ?: ('name:' . strtolower((string) ($it['candidate_name'] ?? 'unknown')));
            $byCand[$key] ??= [
                'candidate_fec_id' => $it['candidate_id'] ?? null,
                'candidate_name' => $it['candidate_name'] ?: 'Unknown candidate',
                'office' => $this->officeLabel($it['office'] ?? null),
                'state' => $it['state'] ?? null,
                'district' => $it['district'] ?? null,
                'support' => 0.0,
                'oppose' => 0.0,
            ];
            $bucket = ($it['support_oppose'] === 'O') ? 'oppose' : 'support';
            $byCand[$key][$bucket] += (float) $it['amount'];
        }

        $fecIds = array_values(array_filter(array_map(
            fn ($r) => $r['candidate_fec_id'] ?? null,
            $byCand
        )));

        $politicianByFecId = $fecIds === []
            ? collect()
            : Politician::query()
                ->whereIn('fec_candidate_id', $fecIds)
                ->get(['id', 'slug', 'full_name', 'fec_candidate_id'])
                ->keyBy('fec_candidate_id');

        $rows = [];
        foreach ($byCand as $r) {
            $pol = $r['candidate_fec_id'] ? $politicianByFecId->get($r['candidate_fec_id']) : null;
            $rows[] = [
                'politician_id' => $pol?->id,
                'politician_slug' => $pol?->slug,
                'candidate_name' => $pol?->full_name ?? $r['candidate_name'],
                'office' => $r['office'],
                'state' => $r['state'],
                'district' => $r['district'],
                'support' => round($r['support'], 2),
                'oppose' => round($r['oppose'], 2),
            ];
        }

        usort($rows, fn ($a, $b) => ($b['support'] + $b['oppose']) <=> ($a['support'] + $a['oppose']));

        return array_slice($rows, 0, 40);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function recentLineItems(array $items): array
    {
        $rows = array_map(fn ($it) => [
            'candidate_name' => $it['candidate_name'] ?: 'Unknown candidate',
            'support_oppose' => $it['support_oppose'],
            'amount' => round((float) $it['amount'], 2),
            'date' => $it['date'],
            'purpose' => $it['purpose'] ? Str::limit((string) $it['purpose'], 90) : null,
        ], $items);

        usort($rows, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($rows, 0, 25);
    }
}
