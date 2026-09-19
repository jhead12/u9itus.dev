<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;

class AuditPoliticianDataIntegrity extends Command
{
    protected $signature = 'politicians:audit-data-integrity
        {--state=       : Restrict to a two-letter state code}
        {--fix          : Apply safe fixes (normalize party, uppercase state)}
        {--deactivate   : Deactivate rows with unfixable artifact names}
        {--limit=0      : Max rows to scan; 0 scans every row}
        {--dry-run      : Report only, even when fix/deactivate are supplied}
        {--max-violations=0 : Number of unresolved violations allowed}
        {--report=      : Write a JSON audit summary to this path}';

    protected $description = 'Scan politician rows against central data rules; report, fix, or deactivate violations.';

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $dryRun = (bool) $this->option('dry-run');
        $fix = ! $dryRun && (bool) $this->option('fix');
        $deactivate = ! $dryRun && (bool) $this->option('deactivate');
        $limit = max(0, (int) $this->option('limit'));

        $rows = Politician::query()
            ->when($state, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->orderBy('id')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->select(['id', 'full_name', 'party_affiliation', 'state', 'term_status', 'is_active', 'page_published', 'political_office', 'governance_level', 'is_running_candidate'])
            ->cursor();

        $scanned = 0;
        $clean = 0;
        $fixed = 0;
        $deactivated = 0;
        $flagged = 0;
        $governanceLevelFixed = 0;
        $runningSyncFixed = 0;

        foreach ($rows as $pol) {
            $scanned++;
            // An officeholder whose political_office unambiguously implies a
            // governance_level that doesn't match what's stored is a data bug,
            // not a display preference — the map's buckets (
            // MapStateCandidatesController) filter strictly by governance_level
            // (federal/state/city+county+local), so a mismatch here makes an
            // otherwise-correct officeholder invisible. Found corrupting 45+
            // U.S. Representatives/Senators in production via
            // CongressGovService::parseMember() not setting governance_level at
            // all (fixed separately, see also GoogleCivicService's Governor/
            // Mayor fallback hardening) — this repairs rows that bug (or ones
            // like it) already wrote, for any office title on the list below.
            $expectedGovernanceLevel = self::expectedGovernanceLevelFor((string) $pol->political_office);
            if ($expectedGovernanceLevel !== null && strcasecmp((string) $pol->governance_level, $expectedGovernanceLevel) !== 0) {
                $this->line(sprintf(
                    '  <fg=yellow>#%d</> %s (%s) — governance_level \'%s\' should be \'%s\' for office \'%s\'',
                    $pol->id,
                    mb_strimwidth((string) $pol->full_name, 0, 60, '…'),
                    $pol->state ?: '??',
                    $pol->governance_level ?: 'null',
                    $expectedGovernanceLevel,
                    $pol->political_office
                ));

                if ($fix) {
                    $pol->governance_level = $expectedGovernanceLevel;
                    $pol->saveQuietly();
                    $governanceLevelFixed++;
                } else {
                    $flagged++;
                }
            }

            // Serving in office and running in an election are independent.
            // A seated incumbent may also be a candidate; preserve that flag.
            $expectedRunning = match (strtolower((string) $pol->term_status)) {
                'running' => true,
                'lost', 'retired', 'former', 'eliminated' => false,
                default => null,
            };
            if ($expectedRunning !== null && (bool) $pol->is_running_candidate !== $expectedRunning) {
                $this->line(sprintf(
                    '  <fg=yellow>#%d</> %s (%s) — is_running_candidate=%s should be %s for term_status \'%s\'',
                    $pol->id,
                    mb_strimwidth((string) $pol->full_name, 0, 60, '…'),
                    $pol->state ?: '??',
                    $pol->is_running_candidate ? 'true' : 'false',
                    $expectedRunning ? 'true' : 'false',
                    $pol->term_status
                ));

                if ($fix) {
                    $pol->is_running_candidate = $expectedRunning;
                    $pol->saveQuietly();
                    $runningSyncFixed++;
                } else {
                    $flagged++;
                }
            }

            $violations = PoliticianDataRules::violations([
                'full_name' => $pol->full_name,
                'party_affiliation' => $pol->party_affiliation,
                'state' => $pol->state,
                'term_status' => $pol->term_status,
            ]);

            if ($violations === []) {
                $clean++;
                continue;
            }

            $nameViolation = PoliticianDataRules::nameViolation($pol->full_name) !== null;

            $this->line(sprintf(
                '  <fg=%s>#%d</> %s (%s) — %s',
                $nameViolation ? 'red' : 'yellow',
                $pol->id,
                mb_strimwidth((string) $pol->full_name, 0, 60, '…'),
                $pol->state ?: '??',
                implode('; ', $violations)
            ));

            // Unfixable artifact name → deactivate when requested.
            if ($nameViolation) {
                if ($deactivate && ($pol->is_active || $pol->page_published)) {
                    // saveQuietly: skip model events — the saving hook would
                    // (correctly) reject this artifact name and abort.
                    $pol->is_active = false;
                    $pol->page_published = false;
                    $pol->saveQuietly();
                    $deactivated++;
                } elseif ($pol->is_active || $pol->page_published) {
                    $flagged++;
                }
                continue;
            }

            // Fixable: normalize party/state/term_status in place.
            if ($fix) {
                $pol->party_affiliation = PoliticianDataRules::normalizeParty($pol->party_affiliation);

                // Resolve full state names (e.g. 'CALIFORNIA') to 2-letter
                // abbrevs first; only clear when truly unmappable.
                if (PoliticianDataRules::stateViolation($pol->state) !== null) {
                    $pol->state = PoliticianDataRules::resolveStateAbbreviation($pol->state);
                }

                // term_status is NOT NULL in prod — fall back to 'running'
                // (neutral, hides from seated/lost filters) instead of null.
                if (PoliticianDataRules::termStatusViolation($pol->term_status) !== null) {
                    $pol->term_status = 'running';
                    // is_running_candidate must move with term_status, or the
                    // directory's status=running filter (which historically
                    // checked only this boolean) silently hides the row.
                    $pol->is_running_candidate = true;
                }

                $pol->saveQuietly();
                $fixed++;
            } else {
                $flagged++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Audit complete: %d scanned, %d clean, %d fixed, %d governance_level fixed, %d running-status synced, %d deactivated, %d flagged (run with --fix/--deactivate to apply).',
            $scanned,
            $clean,
            $fixed,
            $governanceLevelFixed,
            $runningSyncFixed,
            $deactivated,
            $flagged
        ));

        // Non-zero exit when unresolved violations remain — usable as CI gate.
        $summary = compact('scanned', 'clean', 'fixed', 'governanceLevelFixed', 'runningSyncFixed', 'deactivated', 'flagged', 'dryRun');
        if ($report = $this->option('report')) {
            $directory = dirname($report);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($report, json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        return $flagged > max(0, (int) $this->option('max-violations')) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Office titles unambiguous enough to assert a governance_level from —
     * unlike "Senator"/"Representative" alone (federal vs. state legislator),
     * these titles only ever mean one level.
     */
    private const OFFICE_GOVERNANCE_LEVELS = [
        'united states representative' => 'Federal',
        'u.s. representative' => 'Federal',
        'united states senator' => 'Federal',
        'u.s. senator' => 'Federal',
        'governor' => 'State', // also matches "Lieutenant Governor" — still State
        'mayor' => 'City',
    ];

    private static function expectedGovernanceLevelFor(string $politicalOffice): ?string
    {
        $office = strtolower(trim($politicalOffice));

        foreach (self::OFFICE_GOVERNANCE_LEVELS as $needle => $level) {
            if (str_contains($office, $needle)) {
                return $level;
            }
        }

        return null;
    }
}
