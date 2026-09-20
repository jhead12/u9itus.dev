<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hard-deletes the junk that the cleanup steps only hide: headline-fragment profiles
 * that are already inactive and unpublished, junk-named discovery records nobody is
 * linked to, and old rejected discovery leads. Dry-run by default; --apply takes a
 * database backup first and stops if it fails.
 *
 * A profile is only deleted when nothing but its cleanup-review, match-link and scraped
 * enrichment rows (news, donor snapshots, viral-moment runs) point at it. Deleting one cascades into every table that references it, so a profile
 * with a claim, campaign, note, donation or any other dependent row is reported, kept.
 *
 *   php artisan db:purge-junk                     # dry run
 *   php artisan db:purge-junk --apply             # backup, then delete
 *   php artisan db:purge-junk --apply --skip-backup   # a backup was already taken
 */
class PurgeJunkData extends Command
{
    protected $signature = 'db:purge-junk
        {--apply         : Delete (default is dry-run)}
        {--skip-backup   : With --apply, do not take a backup first (use when one was just taken)}
        {--backup-path=  : Directory for the backup (default: storage/app/backups)}
        {--lead-days=30  : Only delete rejected leads older than this many days}
        {--state=*       : Restrict records and leads to these states (profiles are not filtered)}';

    protected $description = 'Back up the database, then hard-delete hidden junk profiles, junk discovery records and old rejected leads.';

    /** Match links, review rows and machine-collected enrichment: removed along with a junk profile. */
    private const DISPOSABLE_REFERENCES = [
        'candidate_identity_links', 'politician_cleanup_reviews',
        'candidate_news_articles', 'politician_donor_snapshots', 'viral_moment_enrichment_runs',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $states = collect($this->option('state'))->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->values()->all();

        if ($apply && ! $this->option('skip-backup')) {
            $this->info('Taking a backup first…');
            $args = array_filter(['--path' => $this->option('backup-path')]);
            if ($this->call('db:backup', $args) !== self::SUCCESS) {
                $this->error('Backup failed — nothing was deleted.');

                return self::FAILURE;
            }
        }

        $this->line($apply ? '<comment>[LIVE — deleting]</comment>' : '[DRY RUN — no deletes; pass --apply]');

        $profiles = $this->purgeProfiles($apply);
        $records = $this->purgeRecords($apply, $states);
        $leads = $this->purgeLeads($apply, $states, max(1, (int) $this->option('lead-days')));

        $this->info(sprintf('%s %d profile(s), %d discovery record(s), %d rejected lead(s).', $apply ? 'Deleted' : 'Would delete', $profiles, $records, $leads));

        return self::SUCCESS;
    }

    private function purgeProfiles(bool $apply): int
    {
        $candidates = Politician::query()
            ->where('is_active', false)->where('page_published', false)
            ->whereNull('user_id')->whereNull('fec_candidate_id')
            ->get(['id', 'full_name'])
            ->filter(fn (Politician $p) => PoliticianDataRules::headlineFragmentViolation($p->full_name) !== null
                || PoliticianDataRules::nameViolation($p->full_name) !== null)
            ->toBase() // an Eloquent collection's except() re-indexes, which would lose the id keys
            ->keyBy('id');

        $blocked = $this->idsWithDependents($candidates->keys()->all());
        $deletable = $candidates->except(array_keys($blocked));

        foreach ($blocked as $id => $tables) {
            $this->line(sprintf('  <fg=yellow>keep</> profile #%d "%s" — has rows in %s', $id, mb_strimwidth((string) $candidates[$id]->full_name, 0, 50, '…'), implode(', ', $tables)));
        }

        if ($apply) {
            foreach ($deletable->keys()->chunk(200) as $chunk) {
                Politician::query()->whereKey($chunk->all())->delete();
            }
        }

        $this->line(sprintf('  profiles: %d junk hidden, %d kept for dependent rows, %d %s', $candidates->count(), count($blocked), $deletable->count(), $apply ? 'deleted' : 'deletable'));

        return $deletable->count();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<int, string>> profile id => tables that still reference it
     */
    private function idsWithDependents(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $blocked = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, self::DISPOSABLE_REFERENCES, true)) {
                continue;
            }

            foreach (Schema::getForeignKeys($name) as $foreign) {
                if ($foreign['foreign_table'] !== 'politicians' || count($foreign['columns']) !== 1) {
                    continue;
                }
                $column = $foreign['columns'][0];

                foreach (array_chunk($ids, 1000) as $chunk) {
                    foreach (DB::table($name)->whereIn($column, $chunk)->distinct()->pluck($column) as $id) {
                        $blocked[(int) $id][] = $name;
                    }
                }
            }
        }

        return array_map('array_unique', $blocked);
    }

    /** @param  array<int, string>  $states */
    private function purgeRecords(bool $apply, array $states): int
    {
        $linked = CandidateIdentityLink::query()->distinct()->pluck('election_candidate_record_id')->flip();

        $junk = ElectionCandidateRecord::query()
            ->where('source', ElectionCandidateRecord::DISCOVERY_SOURCE)
            ->when($states, fn ($q) => $q->whereIn('state', $states))
            ->get(['id', 'full_name'])
            ->filter(fn ($r) => ! $linked->has($r->id) && PoliticianDataRules::headlineFragmentViolation($r->full_name) !== null);

        if ($apply) {
            foreach ($junk->pluck('id')->chunk(200) as $chunk) {
                ElectionCandidateRecord::query()->whereKey($chunk->all())->delete();
            }
        }

        $this->line(sprintf('  discovery records: %d unlinked junk %s', $junk->count(), $apply ? 'deleted' : 'deletable'));

        return $junk->count();
    }

    /** @param  array<int, string>  $states */
    private function purgeLeads(bool $apply, array $states, int $days): int
    {
        $query = CandidateLead::query()
            ->where('status', CandidateLead::STATUS_REJECTED)
            ->where('discovered_at', '<', now()->subDays($days))
            ->when($states, fn ($q) => $q->whereIn('state', $states));

        $count = (clone $query)->count();
        if ($apply) {
            $query->delete();
        }

        $this->line(sprintf('  rejected leads older than %d days: %d %s', $days, $count, $apply ? 'deleted' : 'deletable'));

        return $count;
    }
}
