<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\StateElectionDate;
use App\Services\FECService;
use App\Support\FecCandidateName;
use App\Support\MapCandidateHygiene;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Imports the House/Senate candidate list from the FEC API (api.data.gov key).
 *
 * The FEC record is authoritative for *who filed*, so this does two jobs:
 *
 *  1. Cross-reference: a filer who matches an existing Politician (same state,
 *     same chamber, same first/last name) gets that person's fec_candidate_id
 *     recorded. That id is what lets politicians:dedupe-by-fec prove two rows
 *     are one person instead of guessing from spellings.
 *  2. Discovery: a filer we have no profile for becomes an
 *     ElectionCandidateRecord (source "fec") — but only while their state's
 *     primary is still ahead. The FEC lists everyone who filed, including people
 *     eliminated in a primary, and has no result data; a record dated after the
 *     primary would surface them on the map as running. It never creates a
 *     Politician profile itself.
 */
class ImportFecCandidates extends Command
{
    protected $signature = 'politicians:import-fec-candidates
        {--year=2026 : Election year}
        {--state= : Two-letter state code — limit to one state}
        {--office=H,S : Chambers to import: H (House), S (Senate)}
        {--include-unqualified : Also import filers below the FEC $5,000 threshold (default: candidate_status C only)}
        {--dry-run : Report only — no DB writes}';

    protected $description = 'Import FEC House/Senate candidates: record FEC ids on matching profiles and add candidates we have no record of.';

    private const OFFICES = [
        'H' => ['title' => 'U.S. Representative', 'needles' => ['representative', 'house', 'congress']],
        'S' => ['title' => 'U.S. Senator', 'needles' => ['senator', 'senate']],
    ];

    private const PARTY_CODES = [
        'DEM' => 'Democratic', 'REP' => 'Republican', 'LIB' => 'Libertarian', 'GRE' => 'Green', 'IND' => 'Independent',
    ];

    /** @var array<string, ?string> state => earliest primary date (Y-m-d) */
    private array $primaryDates = [];

    public function handle(FECService $fec): int
    {
        if (! $fec->isConfigured()) {
            $this->error('FEC_API_KEY is not set (an api.data.gov key works).');

            return self::FAILURE;
        }

        $year = (int) $this->option('year');
        $dryRun = (bool) $this->option('dry-run');
        $statutoryOnly = ! $this->option('include-unqualified');
        $offices = array_values(array_intersect(
            array_map('strtoupper', array_map('trim', explode(',', (string) $this->option('office')))),
            array_keys(self::OFFICES),
        ));
        $states = $this->option('state')
            ? [strtoupper(trim((string) $this->option('state')))]
            : PoliticianDataRules::ALLOWED_STATES;

        if ($offices === []) {
            $this->error('--office must include H and/or S.');

            return self::FAILURE;
        }

        FECService::resetTelemetry();
        $totals = ['filers' => 0, 'linked' => 0, 'already' => 0, 'conflicts' => 0, 'records' => 0, 'after_primary' => 0, 'no_date' => 0, 'failed_lists' => 0];

        foreach ($states as $state) {
            foreach ($offices as $office) {
                if (FECService::wasShortCircuited()) {
                    $this->warn('FEC is rate-limiting us — stopping; re-run later to finish the remaining states.');

                    return $this->finish($totals, $dryRun, self::FAILURE);
                }

                $rows = $fec->listCandidates($year, $office, $state, $statutoryOnly);
                if ($rows === null) {
                    $this->warn("[{$state} {$office}] FEC list unavailable — skipped.");
                    $totals['failed_lists']++;

                    continue;
                }

                $this->importList($rows, $state, $office, $year, $dryRun, $totals);
            }
        }

        return $this->finish($totals, $dryRun, self::SUCCESS);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $totals
     */
    private function importList(array $rows, string $state, string $office, int $year, bool $dryRun, array &$totals): void
    {
        $pool = $this->politiciansByIdentity($state, $office);

        foreach ($rows as $row) {
            $fecId = (string) ($row['candidate_id'] ?? '');
            $displayName = FecCandidateName::display($row['name'] ?? '');
            if ($fecId === '' || $displayName === '' || ! empty($row['candidate_inactive'])) {
                continue;
            }

            $totals['filers']++;
            $matches = $pool->get(MapCandidateHygiene::identityKey($displayName), collect());

            if ($matches->isNotEmpty()) {
                foreach ($matches as $politician) {
                    $this->linkId($politician, $fecId, $displayName, $dryRun, $totals);
                }

                continue;
            }

            $this->addRecord($row, $fecId, $displayName, $state, $office, $year, $dryRun, $totals);
        }
    }

    /**
     * Existing profiles for this state + chamber, keyed by name identity. A key can hold
     * several rows — those are duplicates, and each gets the id so the dedupe step can see them.
     *
     * @return Collection<string, Collection<int, Politician>>
     */
    private function politiciansByIdentity(string $state, string $office): Collection
    {
        $needles = self::OFFICES[$office]['needles'];

        return Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereNotNull('political_office')
            ->get(['id', 'full_name', 'political_office', 'state', 'district', 'fec_candidate_id'])
            ->filter(fn (Politician $p) => $this->containsAny(strtolower((string) $p->political_office), $needles))
            ->groupBy(fn (Politician $p) => MapCandidateHygiene::identityKey($p->full_name));
    }

    /** @param  array<string, int>  $totals */
    private function linkId(Politician $politician, string $fecId, string $name, bool $dryRun, array &$totals): void
    {
        $existing = trim((string) $politician->fec_candidate_id);

        if ($existing === $fecId) {
            $totals['already']++;

            return;
        }

        if ($existing !== '') {
            // A person can hold more than one FEC id (a different office or district
            // years apart), and older ids were name-search guesses — don't overwrite; flag it.
            $this->line("[CONFLICT] {$name} (#{$politician->id}) has {$existing}, FEC lists {$fecId} — left as-is");
            $totals['conflicts']++;

            return;
        }

        if (! $dryRun) {
            $politician->updateQuietly(['fec_candidate_id' => $fecId]);
        }
        $this->line('['.($dryRun ? 'DRY' : 'LINK')."] {$name} (#{$politician->id}) ← {$fecId}");
        $totals['linked']++;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $totals
     */
    private function addRecord(array $row, string $fecId, string $name, string $state, string $office, int $year, bool $dryRun, array &$totals): void
    {
        $primary = $this->primaryDate($state, $year);

        if ($primary === null) {
            $totals['no_date']++;

            return;
        }
        if ($primary < now()->toDateString()) {
            $totals['after_primary']++; // outcome unknown — must not be shown as running

            return;
        }

        $district = $office === 'S' ? 'Statewide' : $this->districtCode($state, (string) ($row['district'] ?? ''));
        $party = self::PARTY_CODES[strtoupper((string) ($row['party'] ?? ''))]
            ?? PoliticianDataRules::normalizeParty($row['party_full'] ?? null);

        if (! $dryRun) {
            ElectionCandidateRecord::updateOrCreate(
                ['external_candidate_id' => $fecId],
                [
                    'source' => 'fec',
                    'full_name' => $name,
                    'political_office' => self::OFFICES[$office]['title'],
                    'governance_level' => 'federal',
                    'state' => $state,
                    'district' => $district,
                    'party_affiliation' => $party,
                    'election_date' => $primary,
                    'payload' => [
                        'status' => 'running',
                        'fec_candidate_id' => $fecId,
                        'incumbent_challenge' => $row['incumbent_challenge_full'] ?? null,
                        'candidate_status' => $row['candidate_status'] ?? null,
                    ],
                    'last_seen_at' => now(),
                ]
            );
        }

        $this->line('['.($dryRun ? 'DRY' : 'RECORD')."] {$name} ({$state} {$district}) {$fecId}");
        $totals['records']++;
    }

    private function districtCode(string $state, string $fecDistrict): string
    {
        $number = ltrim(trim($fecDistrict), '0');

        return $number === '' ? "{$state}-AL" : "{$state}-{$number}";
    }

    /** Earliest primary date on file for the state that year, or null when none is known. */
    private function primaryDate(string $state, int $year): ?string
    {
        if (! array_key_exists($state, $this->primaryDates)) {
            $date = StateElectionDate::query()
                ->where('state', $state)
                ->where('election_year', $year)
                ->whereRaw('LOWER(stage_name) LIKE ?', ['%primary%'])
                ->whereRaw('LOWER(stage_name) NOT LIKE ?', ['%runoff%'])
                ->whereNotNull('election_date')
                ->orderBy('election_date')
                ->value('election_date');

            $this->primaryDates[$state] = $date ? substr((string) $date, 0, 10) : null;
        }

        return $this->primaryDates[$state];
    }

    /** @param  array<string, int>  $totals */
    private function finish(array $totals, bool $dryRun, int $status): int
    {
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sFEC import: %d filer(s) — %d id(s) linked, %d already linked, %d conflict(s), %d new record(s); '
            .'not added: %d (primary already held — outcome unknown), %d (no primary date on file); %d list(s) unavailable.',
            $prefix, $totals['filers'], $totals['linked'], $totals['already'], $totals['conflicts'], $totals['records'],
            $totals['after_primary'], $totals['no_date'], $totals['failed_lists'],
        ));
        $this->line('FEC API calls: '.FECService::getHttpCallCount().', rate-limited: '.FECService::getRateLimitCount());

        return $status;
    }

    /** @param  array<int, string>  $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
