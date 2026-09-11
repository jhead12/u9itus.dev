<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Services\PoliticianDedup\DuplicatePoliticianDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consolidated replacement for politicians:merge-duplicates (--scope=unclaimed-all)
 * and politicians:dedupe-unclaimed (--scope=federal), which had independently
 * reinvented the same grouping/survivor-scoring logic — see
 * DuplicatePoliticianDetectionService for the shared engine both scopes now
 * use.
 *
 *   --scope=unclaimed-all  Any unclaimed row, grouped by name+office+state.
 *                          --apply reassigns every FK from loser to survivor
 *                          (across DuplicatePoliticianDetectionService::
 *                          POLITICIAN_ID_COLUMNS) and deletes the loser.
 *   --scope=federal        Unclaimed federal officials only, grouped by
 *                          name+state. --apply deletes a loser only when it
 *                          is a true orphan (no related rows anywhere);
 *                          anything else is reported for manual review.
 *
 * Merging/deleting is destructive, so per default this is a dry-run report.
 * Pass --apply to write directly (with a confirmation prompt, skippable with
 * --force), or --enqueue-review to write each duplicate pair to
 * politician_cleanup_reviews instead of touching politicians at all — the
 * politicians:cleanup-workflow orchestrator always uses --enqueue-review,
 * never --apply, since a merge should never happen without a human
 * approving it first.
 *
 * Usage:
 *   php artisan politicians:dedupe --scope=unclaimed-all               # dry run
 *   php artisan politicians:dedupe --scope=unclaimed-all --apply --force
 *   php artisan politicians:dedupe --scope=federal --state=CA --apply
 *   php artisan politicians:dedupe --scope=unclaimed-all --enqueue-review
 */
class DedupePoliticians extends Command
{
    protected $signature = 'politicians:dedupe
        {--scope=unclaimed-all : unclaimed-all|federal}
        {--state=               : Two-letter state code — limit to one state}
        {--apply                : Actually reassign/delete (default is dry-run)}
        {--force                : Skip the confirmation prompt (--scope=unclaimed-all --apply only)}
        {--enqueue-review       : Write flagged duplicate pairs to the review queue instead of applying}
        {--limit=5000           : Max rows to scan}';

    protected $description = 'Find and merge/dedupe duplicate unclaimed Politician rows (consolidates the former merge-duplicates and dedupe-unclaimed commands).';

    public function handle(DuplicatePoliticianDetectionService $service): int
    {
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['unclaimed-all', 'federal'], true)) {
            $this->error("Invalid --scope '{$scope}'. Must be 'unclaimed-all' or 'federal'.");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $enqueueReview = (bool) $this->option('enqueue-review');
        if ($apply && $enqueueReview) {
            $this->error('Pass either --apply or --enqueue-review, not both.');

            return self::FAILURE;
        }

        $stateFilter = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $limit = max(1, (int) $this->option('limit'));
        $strategy = $scope === 'federal'
            ? DuplicatePoliticianDetectionService::STRATEGY_NAME_STATE
            : DuplicatePoliticianDetectionService::STRATEGY_NAME_OFFICE_STATE;

        $query = Politician::query()
            ->whereNull('user_id')
            ->when($stateFilter, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$stateFilter]))
            ->when($scope === 'federal', fn ($q) => $q->where(function ($q2) {
                $q2->where('governance_level', 'Federal')
                    ->orWhereIn('political_office', [
                        'U.S. Representative', 'U.S. Senator',
                        'United States Representative', 'United States Senator',
                    ]);
            }))
            ->orderBy('id')
            ->limit($limit);

        $groups = $service->findGroups($query, $strategy);

        if ($groups->isEmpty()) {
            $this->info("No duplicate groups found for --scope={$scope}.");

            return self::SUCCESS;
        }

        $totalDuplicates = $groups->sum(fn ($group) => $group->count() - 1);
        $this->info("Found {$groups->count()} duplicate group(s), {$totalDuplicates} row(s) to resolve.");

        foreach ($groups as $group) {
            $survivor = $service->pickSurvivor($group);
            $losers = $group->reject(fn (Politician $p) => $p->id === $survivor->id);
            $ids = $losers->pluck('id')->implode(', ');
            $this->line(" - \"{$survivor->full_name}\" / {$survivor->political_office} / {$survivor->state} — keeping id={$survivor->id}, duplicate id(s) {$ids}");
        }

        if (! $apply && ! $enqueueReview) {
            $this->info('Dry run — no changes made. Run with --apply or --enqueue-review to act on these.');

            return self::SUCCESS;
        }

        if ($enqueueReview) {
            return $this->enqueueReviews($groups, $service);
        }

        return $this->applyMerges($groups, $service, $scope);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Politician>>  $groups
     */
    private function enqueueReviews($groups, DuplicatePoliticianDetectionService $service): int
    {
        $enqueued = 0;

        foreach ($groups as $group) {
            $survivor = $service->pickSurvivor($group);

            foreach ($group->reject(fn (Politician $p) => $p->id === $survivor->id) as $loser) {
                $review = PoliticianCleanupReview::enqueue(
                    PoliticianCleanupReview::TYPE_MERGE,
                    $survivor->id,
                    $loser->id,
                    [
                        'survivor' => ['id' => $survivor->id, 'full_name' => $survivor->full_name, 'political_office' => $survivor->political_office, 'state' => $survivor->state],
                        'duplicate' => ['id' => $loser->id, 'full_name' => $loser->full_name, 'political_office' => $loser->political_office, 'state' => $loser->state],
                        'related_data_table' => $service->firstTableWithRelatedRows($loser->id),
                    ],
                    'Detected by politicians:dedupe'
                );

                if ($review->wasRecentlyCreated) {
                    $enqueued++;
                }
            }
        }

        $this->info("Enqueued {$enqueued} new merge review(s) (existing pending reviews for the same pair were left as-is).");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Politician>>  $groups
     */
    private function applyMerges($groups, DuplicatePoliticianDetectionService $service, string $scope): int
    {
        $isInteractive = $this->input->isInteractive();
        if ($scope === 'unclaimed-all' && ! $this->option('force') && $isInteractive && ! $this->confirm('Proceed with merging these duplicates?', false)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $merged = 0;
        $skipped = 0;
        $verbose = $this->output->isVerbose();

        foreach ($groups as $group) {
            $survivor = $service->pickSurvivor($group);

            foreach ($group->reject(fn (Politician $p) => $p->id === $survivor->id) as $loser) {
                if ($scope === 'federal') {
                    $relatedTable = $service->firstTableWithRelatedRows((int) $loser->id);
                    if ($relatedTable !== null) {
                        $this->line("  <fg=yellow>⚠</> #{$loser->id} has related rows in '{$relatedTable}' — skipping, needs manual review");
                        $skipped++;

                        continue;
                    }

                    $loser->delete();
                    $this->line("  <fg=red>✗</> #{$loser->id} deleted");
                    $merged++;

                    continue;
                }

                if ($verbose) {
                    $this->line("<comment>Merging id={$loser->id} into id={$survivor->id}</comment> (\"{$survivor->full_name}\")");
                }

                DB::transaction(function () use ($service, $survivor, $loser, $verbose): void {
                    $service->mergeInto((int) $survivor->id, (int) $loser->id, $verbose, function ($table, $column, $result) {
                        $this->line("    {$table}.{$column}: reassigned {$result['reassigned']}, dropped {$result['dropped']} (unique-key collision)");
                    });
                });
                $merged++;
            }
        }

        Log::info('politicians:dedupe applied', ['scope' => $scope, 'merged' => $merged, 'skipped' => $skipped]);
        $this->info("{$merged} row(s) resolved, {$skipped} skipped (have related data).");

        return self::SUCCESS;
    }
}
