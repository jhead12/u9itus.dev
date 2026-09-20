<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use App\Support\CandidateNameCanonicalizer;
use App\Support\ElectionCycle;
use App\Support\MapCacheNotice;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Discovery records written before the name fixes still carry headline debris —
 * "Abdul El-Sayed Billboards", "Michigan Gretchen Whitmer", "Detroit Mayor Mike
 * Duggan" — and never get renamed (the promoter leaves an existing row's name
 * alone). Rename each to the cleaned name; when the cleaned name already has its
 * own record, drop the debris row instead. A row matched to a real profile is
 * reported, never deleted.
 *
 * Dry-run by default.
 *
 *   php artisan candidates:clean-discovery-names --state=MI
 *   php artisan candidates:clean-discovery-names --state=MI --apply
 */
class CleanDiscoveryRecordNames extends Command
{
    protected $signature = 'candidates:clean-discovery-names
        {--state=* : Restrict to one or more two-letter state codes (repeatable)}
        {--name=   : Only records whose name contains this text}
        {--apply   : Write the changes (default is dry-run)}';

    protected $description = 'Rename headline-decorated discovery records to the cleaned name, merging into an existing record when there is one.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $name = trim((string) $this->option('name')) ?: null;
        $states = collect($this->option('state'))->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->values()->all();
        $corroboration = new CandidateCorroboration;
        $linked = CandidateIdentityLink::query()->distinct()->pluck('election_candidate_record_id')->flip();
        $stats = ['renamed' => 0, 'merged' => 0, 'skipped' => 0];

        $this->line($apply ? '<comment>[LIVE — writing changes]</comment>' : '[DRY RUN — no writes]');

        $rows = ElectionCandidateRecord::query()
            ->where('source', ElectionCandidateRecord::DISCOVERY_SOURCE)
            ->when($states, fn ($q) => $q->whereIn('state', $states))
            ->when($name, fn ($q) => $q->where('full_name', 'like', "%{$name}%"))
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $clean = $corroboration->anchorName(CandidateNameCanonicalizer::canonicalize($row->full_name), $row->state);
            if ($clean === '') {
                continue;
            }

            // A person on the FEC roster for a U.S. seat is not running for a state office: a record
            // saying so is a headline mix-up ("Abdul El-Sayed" under Governor). Move it to the seat
            // the FEC lists.
            $seat = $corroboration->federalSeat($clean, $row->state);
            $office = $row->political_office;
            $fixOffice = $seat !== null && ! in_array(CandidateCorroboration::officeKind($row->political_office), ['house', 'senate'], true);
            if ($fixOffice) {
                $office = $seat['office'];
            }

            if ($clean === $row->full_name && ! $fixOffice) {
                continue;
            }

            $label = sprintf('#%d "%s" → "%s" (%s, %s%s)', $row->id, $row->full_name, $clean, $row->state, $row->political_office, $fixOffice ? " → {$office}" : '');

            if (PoliticianDataRules::headlineFragmentViolation($clean) !== null) {
                // "Michigan AG Mike", "Candidate William": nobody to rename it to. Drop it unless a
                // profile is linked to it.
                if ($linked->has($row->id)) {
                    $this->line("  <fg=yellow>skip</> {$label} — cleaned name still reads as a fragment, but a profile is linked");
                    $stats['skipped']++;
                } else {
                    $this->line("  <fg=red>drop</> {$label} — cleaned name is still a fragment, not a person");
                    $apply && $row->delete();
                    $stats['merged']++;
                }

                continue;
            }

            $key = 'disc:'.strtolower((string) $row->state).':'.(Str::slug((string) $office) ?: 'office').':'.(Str::slug($clean) ?: 'name');
            $sibling = ElectionCandidateRecord::query()
                ->where('source', ElectionCandidateRecord::DISCOVERY_SOURCE)
                ->where(fn ($q) => $q->where('external_candidate_id', $key)
                    ->orWhere(fn ($same) => $same->where('full_name', $clean)->where('political_office', $office)->where('state', $row->state)))
                ->where('id', '!=', $row->id)
                ->first();

            if ($sibling !== null) {
                if ($linked->has($row->id)) {
                    $this->line("  <fg=yellow>skip</> {$label} — matched to a profile, review by hand");
                    $stats['skipped']++;

                    continue;
                }

                $this->line("  <fg=cyan>merge</> {$label} — #{$sibling->id} already exists, dropping this one");
                $apply && $row->delete();
                $stats['merged']++;

                continue;
            }

            $this->line("  <fg=green>rename</> {$label}");
            if ($apply) {
                $row->full_name = $clean;
                $row->external_candidate_id = $key;
                if ($fixOffice) {
                    $row->political_office = $office;
                    $row->governance_level = 'Federal';
                    $row->district = $seat['district'];
                }
                // A past-cycle date would get the renamed row pruned on the next cleanup.
                if ($row->election_date !== null && ! ElectionCycle::isCurrentOrFuture($row->election_date->toDateString())) {
                    $row->election_date = ElectionCycle::generalElectionDate(ElectionCycle::year());
                }
                $row->save();
            }
            $stats['renamed']++;
        }

        $this->info(sprintf("\n%d renamed, %d merged or dropped, %d skipped%s.", $stats['renamed'], $stats['merged'], $stats['skipped'], $apply ? '' : ' (dry run — pass --apply)'));
        if ($apply) {
            MapCacheNotice::afterWrite($this);
        }

        return self::SUCCESS;
    }
}
