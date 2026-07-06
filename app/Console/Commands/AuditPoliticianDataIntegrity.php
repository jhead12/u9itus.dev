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
        {--limit=5000   : Max rows to scan}';

    protected $description = 'Scan politician rows against central data rules; report, fix, or deactivate violations.';

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $fix = (bool) $this->option('fix');
        $deactivate = (bool) $this->option('deactivate');
        $limit = max(1, (int) $this->option('limit'));

        $rows = Politician::query()
            ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, "")) = ?', [$state]))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'full_name', 'party_affiliation', 'state', 'term_status', 'is_active', 'page_published']);

        $clean = 0;
        $fixed = 0;
        $deactivated = 0;
        $flagged = 0;

        foreach ($rows as $pol) {
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
                if ($deactivate && $pol->is_active) {
                    // saveQuietly: skip model events — the saving hook would
                    // (correctly) reject this artifact name and abort.
                    $pol->is_active = false;
                    $pol->page_published = false;
                    $pol->saveQuietly();
                    $deactivated++;
                } else {
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
                }

                $pol->saveQuietly();
                $fixed++;
            } else {
                $flagged++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Audit complete: %d scanned, %d clean, %d fixed, %d deactivated, %d flagged (run with --fix/--deactivate to apply).',
            $rows->count(),
            $clean,
            $fixed,
            $deactivated,
            $flagged
        ));

        // Non-zero exit when unresolved violations remain — usable as CI gate.
        return $flagged > 0 ? self::FAILURE : self::SUCCESS;
    }
}
