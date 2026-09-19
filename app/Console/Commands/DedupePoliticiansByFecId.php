<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Services\FECService;
use App\Services\PoliticianDedup\DuplicatePoliticianDetectionService;
use App\Support\FecCandidateName;
use App\Support\MapCandidateHygiene;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds politicians rows that carry the same FEC candidate id — one person, two
 * profiles — and merges the ones the FEC record itself confirms.
 *
 * Two rows sharing an id is strong evidence, but not proof: ids saved by the older
 * FECService::findCandidateId() were the first name-search hit. So a pair is only
 * merged automatically when ALL of these hold; anything else is queued for a human
 * on Admin → Data Quality:
 *
 *  - the row that would be deleted is unclaimed (no user) and not a verified official
 *  - both rows are in the same state
 *  - FEC's own record for that id names the same person as each row (first + last name)
 *    and is in that state
 *  - fewer than --max-auto merges have happened this run (a bad batch of ids can't
 *    cascade into a mass merge)
 *
 * Every automatic merge leaves an already-approved PoliticianCleanupReview behind so
 * it shows in the admin history. (It references only the surviving row: the review
 * table cascades on delete, so a link to the deleted row would erase the record.)
 */
class DedupePoliticiansByFecId extends Command
{
    protected $signature = 'politicians:dedupe-by-fec
        {--apply : Merge verified duplicates and queue the rest for review (default: report only)}
        {--state= : Two-letter state code — limit to one state}
        {--max-auto=25 : Most merges to auto-approve in one run; the rest are queued for review}';

    protected $description = 'Merge duplicate profiles that share a FEC candidate id when the FEC record confirms them; queue the rest for review.';

    public function handle(DuplicatePoliticianDetectionService $service, FECService $fec): int
    {
        $apply = (bool) $this->option('apply');
        $maxAuto = max(0, (int) $this->option('max-auto'));
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;

        $groups = Politician::query()
            ->whereNotNull('fec_candidate_id')
            ->where('fec_candidate_id', '!=', '')
            ->when($state, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->get(['id', 'full_name', 'political_office', 'state', 'user_id', 'governance_level', 'verified_official', 'slug', 'updated_at', 'fec_candidate_id'])
            ->groupBy('fec_candidate_id')
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No profiles share a FEC candidate id.');

            return self::SUCCESS;
        }

        $merged = 0;
        $queued = 0;
        $prefix = $apply ? '' : 'WOULD ';

        foreach ($groups as $fecId => $group) {
            $survivor = $service->pickSurvivor($group->values());

            foreach ($group->reject(fn (Politician $p) => $p->id === $survivor->id) as $loser) {
                $blocker = $this->autoBlocker($survivor, $loser, (string) $fecId, $fec)
                    ?? ($merged >= $maxAuto ? "auto-merge limit of {$maxAuto} reached this run" : null);

                if ($blocker === null) {
                    $this->line("[{$prefix}AUTO-MERGE] #{$loser->id} \"{$loser->full_name}\" → #{$survivor->id} \"{$survivor->full_name}\" ({$fecId}, confirmed by FEC)");
                    if ($apply) {
                        $this->autoMerge($service, $survivor, $loser, (string) $fecId);
                    }
                    $merged++;

                    continue;
                }

                $this->line("[{$prefix}QUEUE] #{$loser->id} \"{$loser->full_name}\" ~ #{$survivor->id} \"{$survivor->full_name}\" ({$fecId}) — {$blocker}");
                if ($apply) {
                    $this->queueForReview($service, $survivor, $loser, (string) $fecId, $blocker);
                }
                $queued++;
            }
        }

        $this->info(($apply ? '' : '[report only] ')."Same-FEC-id duplicates: {$merged} ".($apply ? 'auto-merged' : 'would auto-merge').", {$queued} ".($apply ? 'queued for review' : 'would be queued').'.');
        if (! $apply) {
            $this->line('Run with --apply to act on these.');
        }

        return self::SUCCESS;
    }

    /** Why this pair can't be merged automatically, or null when every safety check passes. */
    private function autoBlocker(Politician $survivor, Politician $loser, string $fecId, FECService $fec): ?string
    {
        if ($loser->user_id !== null) {
            return 'the duplicate is a claimed account';
        }
        if ($loser->verified_official) {
            return 'the duplicate is a verified official';
        }
        if (strtoupper(trim((string) $survivor->state)) !== strtoupper(trim((string) $loser->state))) {
            return 'the rows are in different states';
        }

        $record = $fec->fetchCandidateRecord($fecId);
        if ($record === null) {
            return 'FEC record unavailable, so the id could not be verified';
        }
        if (strtoupper((string) ($record['state'] ?? '')) !== strtoupper(trim((string) $survivor->state))) {
            return "FEC lists {$fecId} in ".($record['state'] ?? '?').", not {$survivor->state}";
        }

        $fecKey = MapCandidateHygiene::identityKey(FecCandidateName::display($record['name'] ?? ''));
        foreach ([$survivor, $loser] as $row) {
            if ($fecKey === '' || MapCandidateHygiene::identityKey($row->full_name) !== $fecKey) {
                return "\"{$row->full_name}\" does not match FEC's name \"".($record['name'] ?? '?').'"';
            }
        }

        return null;
    }

    private function autoMerge(DuplicatePoliticianDetectionService $service, Politician $survivor, Politician $loser, string $fecId): void
    {
        $duplicate = ['id' => $loser->id, 'full_name' => $loser->full_name, 'political_office' => $loser->political_office, 'state' => $loser->state, 'slug' => $loser->slug];

        DB::transaction(function () use ($service, $survivor, $loser, $fecId, $duplicate): void {
            $service->mergeInto((int) $survivor->id, (int) $loser->id);

            PoliticianCleanupReview::create([
                'review_type' => PoliticianCleanupReview::TYPE_MERGE,
                'politician_id' => $survivor->id,
                'duplicate_politician_id' => null,
                'payload' => [
                    'source' => 'dedupe-by-fec',
                    'auto_approved' => true,
                    'fec_candidate_id' => $fecId,
                    'survivor' => ['id' => $survivor->id, 'full_name' => $survivor->full_name, 'political_office' => $survivor->political_office, 'state' => $survivor->state],
                    'duplicate' => $duplicate,
                ],
                'status' => PoliticianCleanupReview::STATUS_APPROVED,
                'reason' => "Auto-approved: same FEC candidate id {$fecId}, both names confirmed against the FEC record",
                'reviewed_at' => now(),
            ]);
        });
    }

    private function queueForReview(DuplicatePoliticianDetectionService $service, Politician $survivor, Politician $loser, string $fecId, string $blocker): void
    {
        PoliticianCleanupReview::enqueue(
            PoliticianCleanupReview::TYPE_MERGE,
            $survivor->id,
            $loser->id,
            [
                'source' => 'dedupe-by-fec',
                'fec_candidate_id' => $fecId,
                'why_not_automatic' => $blocker,
                'survivor' => ['id' => $survivor->id, 'full_name' => $survivor->full_name, 'political_office' => $survivor->political_office, 'state' => $survivor->state],
                'duplicate' => ['id' => $loser->id, 'full_name' => $loser->full_name, 'political_office' => $loser->political_office, 'state' => $loser->state],
                'related_data_table' => $service->firstTableWithRelatedRows((int) $loser->id),
            ],
            "Same FEC candidate id {$fecId}; needs a human: {$blocker}",
        );
    }
}
