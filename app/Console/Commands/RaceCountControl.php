<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\MapStateCandidatesController;
use App\Models\ElectionCandidateRecord;
use App\Models\GovernorRaceCandidateCount;
use App\Services\WikipediaPrimaryResultsService;
use App\Support\ElectionCycle;
use App\Support\PoliticianDataRules;
use App\Support\RaceCalendar;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Measure + Control for the map's per-state race lists: how many people the map shows running
 * for U.S. Senate and Governor in each state, against a reference for that race.
 *
 * The reference comes from outside our own data, so a state full of junk can't make itself look
 * normal:
 *   1. RaceCalendar — is the state holding this race at all? If not, the expected count is 0.
 *   2. The race's Wikipedia article (advanced + declared), when it lists at least two people.
 *   3. governor_race_candidate_counts (hand-entered from Ballotpedia), for Governor races.
 *   4. None of those: the race is compared with the national median for that office instead
 *      (a robust z-score on the median absolute deviation, so outliers don't widen the limits).
 *
 * Verdicts: no_race (people listed for a race the state isn't holding), over / under (outside
 * expected ± tolerance), outlier (no reference, far above the national median). Out-of-control
 * races are listed with their unverified news-discovered names for a human to check.
 *
 * Runs as the last step of politicians:cleanup-workflow, after the filters (prune-junk-ecrs,
 * flag-suspect-profiles, dedupe-by-fec), so what it finds is what the filters left behind. It
 * records the out-of-control count to politician_cleanup_run_metrics for
 * politicians:check-cleanup-health to trend. Read-only apart from that metric row and refreshing
 * each measured state's map cache.
 */
class RaceCountControl extends Command
{
    protected $signature = 'politicians:race-count-control
        {--state=*          : Restrict to one or more two-letter state codes (repeatable)}
        {--year=            : Election year (default: the current cycle)}
        {--no-wikipedia     : Skip the Wikipedia reference (faster; falls back to Ballotpedia counts and the national median)}
        {--tolerance=2      : Allowed difference from the expected count (at least 25% of expected is always allowed)}
        {--z=3.5            : Robust z-score above which a race with no reference is an outlier}
        {--report=          : Also write the results as JSON to this path}
        {--no-record        : Do not write a politician_cleanup_run_metrics row (dry runs)}
        {--fail-on-findings : Exit non-zero when any race is out of control}';

    protected $description = 'Compare how many Senate and Governor candidates the map shows per state against the race calendar, Wikipedia and Ballotpedia references, and flag races outside control limits.';

    /** Map office group => the office name the references use. */
    private const RACES = [
        'U.S. Senators' => ['kind' => 'senate', 'office' => 'U.S. Senate'],
        'Governor' => ['kind' => 'Governor', 'office' => 'Governor'],
    ];

    public function handle(): int
    {
        $startedAt = now();
        $year = (int) ($this->option('year') ?: ElectionCycle::year());
        $tolerance = max(0, (int) $this->option('tolerance'));
        $zLimit = max(0.5, (float) $this->option('z'));
        $useWikipedia = ! $this->option('no-wikipedia');
        $states = collect($this->option('state'))->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->unique()->values()->all();
        $states = $states ?: collect(PoliticianDataRules::stateNameToCode())->values()->unique()->sort()->values()->all();

        $wikipedia = $useWikipedia ? new WikipediaPrimaryResultsService : null;
        $ballotpedia = GovernorRaceCandidateCount::query()->where('election_year', $year)->pluck('expected_count', 'state');

        $races = [];
        foreach ($states as $state) {
            foreach ($this->measure($state) as $group => $listed) {
                $race = self::RACES[$group];
                $held = RaceCalendar::held($state, $race['office'], $year);
                if ($held === null && $listed === []) {
                    continue;
                }
                [$expected, $source] = $this->reference($state, $race, $held, $year, $wikipedia, $ballotpedia);
                $races[] = [
                    'state' => $state,
                    'office' => $race['kind'],
                    'held' => $held,
                    'expected' => $expected,
                    'reference' => $source,
                    'measured' => count($listed),
                    'unverified' => array_values(array_map(fn ($c) => $c['name'], array_filter($listed, fn ($c) => $c['discovery']))),
                ];
            }
        }

        $races = $this->judge($races, $tolerance, $zLimit);
        $this->render($races);

        $out = array_values(array_filter($races, fn ($r) => $r['verdict'] !== 'in_control'));
        $breakdown = array_count_values(array_column($out, 'verdict'));

        if ($path = $this->option('report')) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode(['year' => $year, 'generated_at' => now()->toIso8601String(), 'races' => $races], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("Report written to {$path}");
        }

        if (! $this->option('no-record')) {
            DB::table('politician_cleanup_run_metrics')->insert([
                'step' => 'race-count-control',
                'scope' => $this->option('state') ? implode(',', $states) : null,
                'exit_code' => self::SUCCESS,
                'findings_count' => count($out),
                'auto_applied_count' => 0,
                'queued_count' => 0,
                'breakdown' => json_encode($breakdown),
                'started_at' => $startedAt,
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $out !== [] && $this->option('fail-on-findings') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Who the map lists as running for each covered race in this state, built fresh.
     *
     * @return array<string, array<int, array{name: string, discovery: bool}>>
     */
    private function measure(string $state): array
    {
        Cache::forget("map_state_candidates_{$state}");
        $data = app(MapStateCandidatesController::class)(Request::create('/api/v1/map/state-candidates', 'GET', ['state' => $state]))->getData(true);

        $listed = array_fill_keys(array_keys(self::RACES), []);
        foreach ($data['offices'] ?? [] as $group) {
            $office = (string) ($group['office'] ?? '');
            if (! isset($listed[$office])) {
                continue;
            }
            foreach ($group['candidates'] ?? [] as $candidate) {
                if (! ($candidate['is_running'] ?? false)) {
                    continue;
                }
                $listed[$office][] = [
                    'name' => (string) ($candidate['full_name'] ?? ''),
                    'discovery' => ($candidate['scrape_source'] ?? null) === ElectionCandidateRecord::DISCOVERY_SOURCE,
                ];
            }
        }

        return $listed;
    }

    /**
     * @param  array{kind: string, office: string}  $race
     * @param  \Illuminate\Support\Collection<string, int>  $ballotpedia
     * @return array{0: ?int, 1: string}
     */
    private function reference(string $state, array $race, ?bool $held, int $year, ?WikipediaPrimaryResultsService $wikipedia, $ballotpedia): array
    {
        if ($held === false) {
            return [0, 'calendar'];
        }

        if ($wikipedia !== null) {
            $roster = $wikipedia->roster($state, $race['office'], $year);
            $field = $roster ? array_unique(array_merge($roster['advanced'], $roster['declared'])) : [];
            // Same threshold as prune-junk-ecrs: a thin article is not a reference.
            if (count($field) >= 2) {
                return [count($field), 'wikipedia'];
            }
        }

        if ($race['kind'] === 'Governor' && isset($ballotpedia[$state])) {
            return [(int) $ballotpedia[$state], 'ballotpedia'];
        }

        return [null, 'national median'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $races
     * @return array<int, array<string, mixed>>
     */
    private function judge(array $races, int $tolerance, float $zLimit): array
    {
        // National baseline per office, from the races being held.
        $baseline = [];
        foreach (array_keys(array_flip(array_column($races, 'office'))) as $office) {
            $counts = array_column(array_filter($races, fn ($r) => $r['office'] === $office && $r['held'] !== false), 'measured');
            $median = $this->median($counts);
            $mad = $this->median(array_map(fn ($c) => abs($c - $median), $counts));
            $baseline[$office] = ['median' => $median, 'mad' => $mad];
        }

        foreach ($races as &$race) {
            $race['verdict'] = 'in_control';
            $race['limit'] = null;
            $expected = $race['expected'];

            if ($race['held'] === false) {
                $race['verdict'] = $race['measured'] > 0 ? 'no_race' : 'in_control';

                continue;
            }

            if ($expected !== null) {
                $allowed = max($tolerance, (int) ceil($expected * 0.25));
                $race['limit'] = [max(0, $expected - $allowed), $expected + $allowed];
                $race['verdict'] = match (true) {
                    $race['measured'] > $expected + $allowed => 'over',
                    $race['measured'] < $expected - $allowed => 'under',
                    default => 'in_control',
                };

                continue;
            }

            ['median' => $median, 'mad' => $mad] = $baseline[$race['office']];
            if ($mad > 0) {
                $z = 0.6745 * ($race['measured'] - $median) / $mad;
                $race['z'] = round($z, 1);
                $race['verdict'] = $z > $zLimit ? 'outlier' : 'in_control';
            }
        }
        unset($race);

        return $races;
    }

    /** @param  array<int, array<string, mixed>>  $races */
    private function render(array $races): void
    {
        $out = array_filter($races, fn ($r) => $r['verdict'] !== 'in_control');

        $this->table(
            ['State', 'Office', 'Held', 'Expected', 'Reference', 'Measured', 'Verdict'],
            array_map(fn ($r) => [
                $r['state'],
                $r['office'],
                $r['held'] === null ? '?' : ($r['held'] ? 'yes' : 'no'),
                $r['expected'] ?? '—',
                $r['reference'],
                $r['measured'],
                $r['verdict'] === 'in_control' ? 'ok' : "<fg=red>{$r['verdict']}</>",
            ], $out ?: []),
        );

        foreach ($out as $r) {
            if ($r['unverified'] !== []) {
                $this->line("  {$r['state']} {$r['office']} — unverified: ".implode(', ', $r['unverified']));
            }
        }

        $this->info(sprintf('%d race(s) measured, %d out of control.', count($races), count($out)));
    }

    /** @param  array<int, int|float>  $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
