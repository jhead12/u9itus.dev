<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use App\Support\CandidateNameCanonicalizer;
use App\Support\MapCandidateHygiene;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Read-only sweep for discovery records the automatic cleanup will not (or cannot)
 * fix on its own, so they are listed on every workflow run instead of surfacing
 * only when someone spots them on the map:
 *
 *   name      still carries headline debris (kept because a profile is linked to it)
 *   office    the FEC roster has the person filing for a U.S. seat, the record says otherwise
 *   conflict  records for one person disagree on whether they advanced or were eliminated
 *
 *   php artisan candidates:audit-records --state=MI
 */
class AuditDiscoveryRecords extends Command
{
    protected $signature = 'candidates:audit-records
        {--state=* : Restrict to one or more two-letter state codes (repeatable)}';

    protected $description = 'List discovery candidate records whose name, office or primary result disagree with the FEC roster or with each other.';

    public function handle(): int
    {
        $states = collect($this->option('state'))->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->values()->all();
        $corroboration = new CandidateCorroboration;
        $cycleStart = now()->startOfYear()->toDateString();

        $rows = ElectionCandidateRecord::query()
            ->where('source', ElectionCandidateRecord::DISCOVERY_SOURCE)
            ->when($states, fn ($q) => $q->whereIn('state', $states))
            ->where(fn ($q) => $q->whereNull('election_date')->orWhere('election_date', '>=', $cycleStart))
            ->orderBy('state')->orderBy('id')
            ->get(['id', 'full_name', 'state', 'political_office', 'payload']);

        $findings = [];
        $results = [];

        foreach ($rows as $row) {
            $clean = $corroboration->anchorName(CandidateNameCanonicalizer::canonicalize($row->full_name), $row->state);
            if ($clean !== '' && $clean !== $row->full_name) {
                $findings[] = ['name', $row, "name should read \"{$clean}\""];
            }

            $seat = $corroboration->federalSeat($clean, $row->state);
            if ($seat !== null && ! in_array(CandidateCorroboration::officeKind($row->political_office), ['house', 'senate'], true)) {
                $findings[] = ['office', $row, "FEC lists them for {$seat['office']}, record says \"{$row->political_office}\""];
            }

            $result = is_array($row->payload) ? ($row->payload['primary_result'] ?? null) : null;
            if (in_array($result, ['advanced_to_general', 'eliminated'], true)) {
                $results[$row->state.'|'.MapCandidateHygiene::identityKey($clean)][$result][] = $row;
            }
        }

        foreach ($results as $byResult) {
            if (count($byResult) > 1) {
                $ids = collect($byResult)->flatten()->map(fn ($r) => '#'.$r->id)->implode(', ');
                $first = collect($byResult)->flatten()->first();
                $findings[] = ['conflict', $first, "records disagree on the primary result ({$ids})"];
            }
        }

        foreach ($findings as [$kind, $row, $detail]) {
            $this->line(sprintf('  <fg=yellow>%-8s</> #%d "%s" (%s) — %s', $kind, $row->id, $row->full_name, $row->state, $detail));
        }

        $count = count($findings);
        $this->info($count === 0
            ? 'No discovery record discrepancies.'
            : "{$count} discovery record discrepancies need review (fix by hand, or run candidates:clean-discovery-names).");

        if ($count > 0) {
            Log::warning('candidates:audit-records found discrepancies', ['count' => $count, 'states' => $states]);
        }

        return self::SUCCESS;
    }
}
