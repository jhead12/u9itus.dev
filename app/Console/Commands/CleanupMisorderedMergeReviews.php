<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;

/**
 * One-off: the first production run of the new trailing-fragment duplicate
 * clustering (see DuplicatePoliticianDetectionService::clusterByName())
 * enqueued some merge reviews before the follow-up fix to scoreSurvivor()
 * landed, so a handful of pending reviews have the survivor and duplicate
 * backwards — e.g. "Eric Swalwell Officially" kept as politician_id over
 * the clean "Eric Swalwell" as duplicate_politician_id.
 *
 * Deletes any PENDING merge review where the survivor's name fails the
 * strict headlineFragmentViolation() check but the duplicate's passes.
 * politicians:dedupe --enqueue-review can then be re-run to regenerate
 * these with the correct direction (enqueue() only dedups against an
 * existing PENDING row with the exact same politician/duplicate pair, so
 * deleting the wrong-direction row first is required).
 *
 * Delete this command once it's been run once against production.
 */
class CleanupMisorderedMergeReviews extends Command
{
    protected $signature = 'politicians:cleanup-misordered-merge-reviews {--apply}';

    protected $description = 'One-off: delete pending merge reviews where the kept survivor is the headline-mangled name.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->line('<fg=yellow>[dry-run] No rows will be changed. Pass --apply to delete.</>');
        }

        $reviews = PoliticianCleanupReview::query()
            ->where('review_type', PoliticianCleanupReview::TYPE_MERGE)
            ->where('status', PoliticianCleanupReview::STATUS_PENDING)
            ->whereNotNull('duplicate_politician_id')
            ->get();

        $deleted = 0;
        foreach ($reviews as $review) {
            $survivor = Politician::find($review->politician_id);
            $duplicate = Politician::find($review->duplicate_politician_id);
            if (! $survivor || ! $duplicate) {
                continue;
            }

            $survivorClean = PoliticianDataRules::headlineFragmentViolation($survivor->full_name) === null;
            $duplicateClean = PoliticianDataRules::headlineFragmentViolation($duplicate->full_name) === null;

            if ($survivorClean || ! $duplicateClean) {
                continue;
            }

            $this->line("  <fg=green>#{$review->id}</> wrong direction: kept \"{$survivor->full_name}\" (#{$survivor->id}) over \"{$duplicate->full_name}\" (#{$duplicate->id})");

            if ($apply) {
                $review->delete();
            }
            $deleted++;
        }

        $this->newLine();
        $verb = $apply ? 'deleted' : 'would delete';
        $this->info("{$reviews->count()} pending merge review(s) scanned, {$deleted} {$verb}.");

        return self::SUCCESS;
    }
}
