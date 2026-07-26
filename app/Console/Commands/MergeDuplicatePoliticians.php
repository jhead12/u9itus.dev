<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Merges duplicate Politician rows created by district-lookup discovery
 * (PublicProfileController::persistDiscoveredOfficial()) racing itself before
 * a Cache::lock() was added around the check-then-insert — concurrent
 * district-lookup requests for the same real official each passed the
 * "does a profile already exist" check before any of them had inserted,
 * producing several distinct rows for the same person.
 *
 * Only ever touches unclaimed rows (user_id IS NULL): a real claimed profile
 * is never merged away, and it is never chosen as a duplicate to delete.
 */
class MergeDuplicatePoliticians extends Command
{
    protected $signature = 'politicians:merge-duplicates
                            {--dry-run : Report duplicate groups without changing anything}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Merge unclaimed Politician rows that are duplicates of the same real official (same name/office/state) into a single canonical row';

    /**
     * (table, column) pairs that reference politicians.id, gathered from
     * every migration under database/migrations. Keep in sync if a new
     * politician_id-referencing table is added.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    protected array $referencingColumns = [
        ['political_campaigns', 'politician_id'],
        ['campaign_transactions', 'politician_id'],
        ['politician_credits', 'politician_id'],
        ['payment_methods', 'politician_id'],
        ['politicians', 'referred_by_politician_id'],
        ['voters', 'referred_by_politician_id'],
        ['referral_earnings', 'referrer_politician_id'],
        ['referral_earnings', 'politician_id'],
        ['politician_pages', 'politician_id'],
        ['politician_initiatives', 'politician_id'],
        ['candidate_identity_links', 'politician_id'],
        ['candidate_match_reviews', 'politician_id'],
        ['referral_visits', 'referrer_politician_id'],
        ['politician_office_profiles', 'politician_id'],
        ['candidate_news_articles', 'politician_id'],
        ['politician_donor_snapshots', 'politician_id'],
        ['politician_song_picks', 'politician_id'],
        ['citizens', 'referred_by_politician_id'],
        ['voter_favorite_politicians', 'politician_id'],
        ['politician_photo_quarantines', 'politician_id'],
        ['earlybank_earnings', 'politician_id'],
        ['viral_moment_enrichment_runs', 'politician_id'],
        ['politician_viral_moments', 'politician_id'],
        ['politician_endorsements', 'politician_id'],
        ['marketing_post_drafts', 'politician_id'],
        ['politician_topic_signals', 'politician_id'],
    ];

    public function handle(): int
    {
        $groups = $this->findDuplicateGroups();

        if ($groups->isEmpty()) {
            $this->info('No duplicate unclaimed politician rows found.');

            return self::SUCCESS;
        }

        $totalDuplicates = $groups->sum(fn ($group) => $group->count() - 1);
        $this->info("Found {$groups->count()} duplicate group(s), {$totalDuplicates} row(s) to merge away.");

        foreach ($groups as $group) {
            $canonical = $group->first();
            $duplicates = $group->slice(1);
            $ids = $duplicates->pluck('id')->implode(', ');
            $this->line(" - \"{$canonical->full_name}\" / {$canonical->political_office} / {$canonical->state} — keeping id={$canonical->id}, merging id(s) {$ids}");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made. Run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $isInteractive = $this->input->isInteractive();
        if (! $this->option('force') && $isInteractive && ! $this->confirm('Proceed with merging these duplicates?', false)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $mergedRows = 0;

        foreach ($groups as $group) {
            $canonicalId = (int) $group->first()->id;

            foreach ($group->slice(1) as $duplicate) {
                DB::transaction(function () use ($canonicalId, $duplicate): void {
                    $this->mergeInto($canonicalId, (int) $duplicate->id);
                });
                $mergedRows++;
            }
        }

        Log::info('politicians:merge-duplicates merged rows', ['count' => $mergedRows]);
        $this->info("Merged {$mergedRows} duplicate politician row(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Politician>>
     */
    protected function findDuplicateGroups()
    {
        return Politician::query()
            ->whereNull('user_id')
            ->orderBy('id')
            ->get(['id', 'full_name', 'political_office', 'state'])
            ->groupBy(function (Politician $politician): string {
                return strtolower(trim((string) $politician->full_name))
                    .'|'.strtolower(trim((string) $politician->political_office))
                    .'|'.strtoupper(trim((string) $politician->state));
            })
            ->filter(function ($group, string $key) {
                return $group->count() > 1 && trim($key, '|') !== '';
            })
            ->values();
    }

    protected function mergeInto(int $canonicalId, int $duplicateId): void
    {
        foreach ($this->referencingColumns as [$table, $column]) {
            $this->reassignForeignKey($table, $column, $duplicateId, $canonicalId);
        }

        // Anything left pointing at the duplicate that isn't in
        // $referencingColumns above falls back to each table's own
        // cascadeOnDelete/nullOnDelete behavior as defined in its migration.
        Politician::query()->whereKey($duplicateId)->delete();
    }

    protected function reassignForeignKey(string $table, string $column, int $fromId, int $toId): void
    {
        try {
            DB::table($table)->where($column, $fromId)->update([$column => $toId]);

            return;
        } catch (QueryException $e) {
            // A compound unique key that includes $column (e.g. one row per
            // politician+topic, politician+service+track, etc.) — the
            // canonical politician already has an equivalent row for at
            // least one of these, so the bulk update collided. Fall back to
            // reassigning row by row and drop only the specific rows that
            // still collide, rather than losing the whole batch.
        }

        DB::table($table)->where($column, $fromId)->orderBy('id')->chunkById(100, function ($rows) use ($table, $column, $toId): void {
            foreach ($rows as $row) {
                try {
                    DB::table($table)->where('id', $row->id)->update([$column => $toId]);
                } catch (QueryException $e) {
                    DB::table($table)->where('id', $row->id)->delete();
                }
            }
        });
    }
}
