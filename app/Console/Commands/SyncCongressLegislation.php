<?php

namespace App\Console\Commands;

use App\Models\CongressMemberLegislation;
use App\Models\Politician;
use App\Services\CongressLegislationImporter;
use Illuminate\Console\Command;

/**
 * Counts each linked member's sponsored/cosponsored bills by policy area and issue topic
 * (Congress.gov API; needs CONGRESS_API_KEY). Feeds politicians:enrich-issue-badges.
 *
 * Usage:
 *   php artisan congress:sync-legislation                    # members not refreshed in 6 days
 *   php artisan congress:sync-legislation --member=P000197   # one member, always refreshed
 *   php artisan congress:sync-legislation --since=118 --limit=50
 */
class SyncCongressLegislation extends Command
{
    protected $signature = 'congress:sync-legislation
        {--member=         : A single Bioguide ID}
        {--since=          : Oldest Congress to count (default: the previous Congress)}
        {--limit=600       : Max members per run}
        {--stale-hours=144 : Skip members refreshed more recently than this}';

    protected $description = 'Roll up sponsored/cosponsored bills by policy area and issue topic for members of Congress.';

    public function handle(CongressLegislationImporter $importer): int
    {
        if (! $importer->isConfigured()) {
            $this->warn('CONGRESS_API_KEY is not set; skipping.');

            return self::SUCCESS;
        }

        $since = (int) ($this->option('since') ?: CongressLegislationImporter::currentCongress() - 1);
        $member = trim((string) $this->option('member'));

        $bioguides = $member !== ''
            ? collect([$member])
            : Politician::query()->whereNotNull('bioguide_id')->where('bioguide_id', '!=', '')->distinct()->pluck('bioguide_id')
                ->diff(CongressMemberLegislation::where('updated_at', '>=', now()->subHours((int) $this->option('stale-hours')))
                    ->where('since_congress', $since)->pluck('bioguide_id'))
                ->take((int) $this->option('limit'));

        $failed = 0;
        foreach ($bioguides as $bioguide) {
            try {
                $row = $importer->import($bioguide, $since);
                $this->line("  ✓ {$bioguide}: {$row->sponsored_total} sponsored, {$row->cosponsored_total} cosponsored, ".count($row->topics ?? []).' topics');
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$bioguide}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Members: {$bioguides->count()} | Failed: {$failed} | Since Congress {$since}");

        return $failed > 0 && $failed === $bioguides->count() ? self::FAILURE : self::SUCCESS;
    }
}
