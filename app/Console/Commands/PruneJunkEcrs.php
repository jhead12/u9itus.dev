<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Support\CandidateNameCanonicalizer;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prunes junk rows from election_candidate_records — the table that feeds the
 * map's per-state candidate panels (MapStateCandidatesController §2 / §5b).
 *
 * The candidate-discovery pipeline (RssCandidateDiscoverySource → CandidateLead
 * → CandidateLeadPromoter) extracts "candidate names" from Google-News
 * headlines. Before the name-quality guard on ElectionCandidateRecord existed,
 * it wrote thousands of fragments — "Former L.A. Mayor Antonio", "Eric Swalwell
 * Won More", "Job Creator", "Nevada Joe Lombardo", "California Governor's Race"
 * — plus stale-cycle rows (2018–2024 races) and dozens of duplicate rows per
 * real person (one per headline). This command cleans up what the guard now
 * blocks on write.
 *
 * A row is flagged for deletion when it matches any of:
 *   - name       full_name fails PoliticianDataRules::headlineFragmentViolation()
 *   - stale      election_date is before the start of the current year
 *                (skipped with --keep-stale)
 *   - cross_state political_office names a different state than the row's
 *                own `state` column (e.g. "Texas Attorney General" / CA)
 *   - dup        an exact name+office+state duplicate of a higher-priority
 *                row; the survivor is chosen seed > manual > ballotpedia >
 *                feed > candidate_discovery, then identity-linked > has
 *                primary_result > most recently updated (skipped with --no-dedup)
 *
 * A row that has a candidate_identity_links row (matched to a real Politician)
 * is NEVER auto-deleted — deleting it would cascade-drop the link. Such rows
 * are reported for manual review instead, except when they are the chosen
 * survivor of a dedup group.
 *
 * Dry-run by default. Deleting a row busts that state's map cache via the
 * ElectionCandidateRecord `deleted` hook.
 *
 * Usage:
 *   php artisan politicians:prune-junk-ecrs                       # dry run, all states
 *   php artisan politicians:prune-junk-ecrs --state=CA --apply
 *   php artisan politicians:prune-junk-ecrs --keep-stale --no-dedup
 */
class PruneJunkEcrs extends Command
{
    protected $signature = 'politicians:prune-junk-ecrs
        {--state=*        : Restrict to one or more two-letter state codes (repeatable)}
        {--stale-before=  : ISO date; rows with an earlier election_date are stale (default: Jan 1 this year)}
        {--keep-stale     : Do not flag stale-cycle rows}
        {--no-dedup       : Do not collapse duplicate name+office+state rows}
        {--apply          : Actually delete flagged rows (default is dry-run)}
        {--limit=20000    : Max rows to scan}';

    protected $description = 'Delete news-headline-artifact, stale-cycle, cross-state, and duplicate rows from election_candidate_records.';

    /** Survivor preference within a duplicate group (higher = kept). */
    private const SOURCE_PRIORITY = [
        'seed' => 100,
        'manual_correction' => 90,
        'manual' => 90,
        'admin' => 90,
        'ballotpedia' => 70,
        'state_feed' => 50,
        'county_feed' => 50,
        'local_feed' => 50,
        'congress_legislators' => 50,
        'candidate_discovery' => 10,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $keepStale = (bool) $this->option('keep-stale');
        $dedup = ! (bool) $this->option('no-dedup');
        $limit = max(1, (int) $this->option('limit'));

        $states = collect($this->option('state'))
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter()->values()->all();

        $staleBefore = $this->option('stale-before')
            ? trim((string) $this->option('stale-before'))
            : now()->startOfYear()->toDateString();

        $this->line($apply ? '<comment>[LIVE — deleting flagged rows]</comment>' : '[DRY RUN — no writes]');
        $this->line('States: '.($states ? implode(', ', $states) : 'all')
            .' | stale-before: '.($keepStale ? 'disabled' : $staleBefore)
            .' | dedup: '.($dedup ? 'on' : 'off'));

        $rows = ElectionCandidateRecord::query()
            ->when($states, fn ($q) => $q->whereIn(DB::raw('UPPER(COALESCE(state, \'\'))'), $states))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'full_name', 'political_office', 'governance_level', 'state',
                'source', 'external_candidate_id', 'election_date', 'payload', 'updated_at']);

        if ($rows->isEmpty()) {
            $this->info('No election_candidate_records rows in scope — nothing to do.');

            return self::SUCCESS;
        }

        $linkedIds = DB::table('candidate_identity_links')
            ->whereIn('election_candidate_record_id', $rows->pluck('id'))
            ->distinct()->pluck('election_candidate_record_id')
            ->flip();

        $stateNames = PoliticianDataRules::stateNameToCode();

        /** @var array<int, string> $flag  row id => reason */
        $flag = [];
        /** @var array<int, string> $keptLinked  row id => reason (linked, needs manual review) */
        $keptLinked = [];

        foreach ($rows as $row) {
            $reason = $this->classify($row, $keepStale, $staleBefore, $stateNames);
            if ($reason === null) {
                continue;
            }

            if ($linkedIds->has($row->id)) {
                $keptLinked[$row->id] = $reason;

                continue;
            }

            $flag[$row->id] = $reason;
        }

        // ── Dedup pass over rows not already flagged ──────────────────────────
        if ($dedup) {
            $groups = [];
            foreach ($rows as $row) {
                if (isset($flag[$row->id])) {
                    continue;
                }
                $groups[$this->dedupKey($row)][] = $row;
            }

            foreach ($groups as $key => $group) {
                if (count($group) < 2) {
                    continue;
                }
                usort($group, fn ($a, $b) => $this->survivorScore($b, $linkedIds) <=> $this->survivorScore($a, $linkedIds));
                foreach (array_slice($group, 1) as $loser) {
                    if ($linkedIds->has($loser->id)) {
                        $keptLinked[$loser->id] = 'dup (linked — review)';

                        continue;
                    }
                    $flag[$loser->id] = 'dup';
                }
            }
        }

        // ── Report ───────────────────────────────────────────────────────────
        $byRow = $rows->keyBy('id');
        $counts = [];
        $this->newLine();
        foreach ($flag as $id => $reason) {
            $r = $byRow[$id];
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            $this->line(sprintf(
                '  <fg=red>#%-6d</> [%s] %-34s | %-24s | %s',
                $id,
                str_pad($reason, 11),
                mb_strimwidth((string) $r->full_name, 0, 34, '…'),
                mb_strimwidth((string) $r->political_office, 0, 24, '…'),
                $r->source
            ));
        }

        if ($keptLinked !== []) {
            $this->newLine();
            $this->warn('Kept (identity-linked — resolve manually, deleting would cascade the link):');
            foreach ($keptLinked as $id => $reason) {
                $r = $byRow[$id];
                $this->line(sprintf('  <fg=yellow>#%-6d</> [%s] %s (%s)', $id, $reason, $r->full_name, $r->state));
            }
        }

        $this->newLine();
        $total = count($flag);
        foreach ($counts as $reason => $n) {
            $this->line(sprintf('  %-12s %d', $reason, $n));
        }
        $this->line(sprintf('  %-12s %d', 'TOTAL', $total));

        if ($total === 0) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->warn("[dry-run] Would delete {$total} row(s). Re-run with --apply to delete.");

            return self::SUCCESS;
        }

        // ── Apply ────────────────────────────────────────────────────────────
        $affectedStates = $byRow->only(array_keys($flag))
            ->pluck('state')->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter()->unique()->values();

        $deleted = ElectionCandidateRecord::whereIn('id', array_keys($flag))->delete();

        foreach ($affectedStates as $st) {
            Cache::forget("map_state_candidates_{$st}");
        }

        Log::info('politicians:prune-junk-ecrs deleted rows', [
            'count' => $deleted,
            'by_reason' => $counts,
            'states' => $affectedStates->all(),
        ]);

        $this->info("Deleted {$deleted} row(s). Map cache cleared for: ".($affectedStates->implode(', ') ?: 'none').'.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $stateNames
     */
    private function classify(ElectionCandidateRecord $row, bool $keepStale, string $staleBefore, array $stateNames): ?string
    {
        if (PoliticianDataRules::headlineFragmentViolation($row->full_name) !== null) {
            return 'name';
        }

        if (! $keepStale && $row->election_date !== null) {
            $date = $row->election_date instanceof \DateTimeInterface
                ? $row->election_date->format('Y-m-d')
                : substr((string) $row->election_date, 0, 10);
            if ($date < $staleBefore) {
                return 'stale';
            }
        }

        $office = strtolower(trim((string) $row->political_office));
        $rowState = strtoupper(trim((string) $row->state));
        if ($office !== '' && $rowState !== '') {
            foreach ($stateNames as $name => $code) {
                if ($code !== $rowState && str_starts_with($office, strtolower($name).' ')) {
                    return 'cross_state';
                }
            }
        }

        return null;
    }

    private function dedupKey(ElectionCandidateRecord $row): string
    {
        $name = CandidateNameCanonicalizer::canonicalize($row->full_name);
        $name = preg_replace('/[^\p{L}\s]/u', '', mb_strtolower($name));
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        $office = mb_strtolower(trim((string) $row->political_office));
        // Collapse a leading state-name prefix so "California Governor" and
        // "Governor" group together.
        foreach (PoliticianDataRules::stateNameToCode() as $stateName => $code) {
            $sn = mb_strtolower($stateName).' ';
            if (str_starts_with($office, $sn)) {
                $office = substr($office, strlen($sn));

                break;
            }
        }

        return $name.'|'.$office.'|'.strtoupper(trim((string) $row->state));
    }

    /**
     * @param  Collection<int, int>  $linkedIds
     */
    private function survivorScore(ElectionCandidateRecord $row, $linkedIds): int
    {
        $score = self::SOURCE_PRIORITY[(string) $row->source] ?? 30;
        $score *= 1000;

        if ($linkedIds->has($row->id)) {
            $score += 500;
        }

        $payload = is_array($row->payload) ? $row->payload : [];
        if (($payload['primary_result'] ?? '') !== '' || ($payload['status'] ?? '') !== '') {
            $score += 200;
        }

        $score += (int) optional($row->updated_at)->timestamp % 100;

        return $score;
    }
}
