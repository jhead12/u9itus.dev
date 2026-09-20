<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Services\CommitteeWebsiteDiscoveryService;
use Illuminate\Console\Command;

class DiscoverCommitteeWebsites extends Command
{
    protected $signature = 'committees:discover-websites
        {--committee= : FEC committee ID, PAC slug, or PAC page URL}
        {--limit=100 : Maximum committees to process}
        {--force : Recheck committees with a discovered website}
        {--dry-run : Show discoveries without writing data}';

    protected $description = 'Find committee websites from verified mappings, FEC filings, and labelled Ballotpedia links.';

    public function handle(CommitteeWebsiteDiscoveryService $discovery): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reference = trim((string) $this->option('committee'));
        if (! $limit || ($reference !== '' && ! preg_match('/(?:^|[\/-])(C\d{8})\/?$/i', $reference, $match))) {
            $this->error('Provide a positive --limit and a valid FEC ID, PAC slug, or PAC URL.');

            return self::FAILURE;
        }
        $query = Committee::query()->with('profile')->orderBy('id');
        if ($reference !== '') {
            $query->where('fec_committee_id', strtoupper($match[1]));
            if (! (clone $query)->exists()) {
                $this->error('Committee not found in the registry. Run committees:enrich-profiles first.');

                return self::FAILURE;
            }
        }
        if (! $this->option('force')) {
            $query->where(fn ($q) => $q->whereNull('website_url')->orWhere('website_url', ''));
        }
        $found = $missed = $failed = 0;
        foreach ($query->limit($limit)->get() as $committee) {
            try {
                $result = $discovery->discoverFor($committee);
                if ($result === null) {
                    $missed++;
                    $this->line($committee->fec_committee_id.': no website found');

                    continue;
                }
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '').$committee->fec_committee_id.': '.$result['url'].' (source: '.$result['source'].')');
                if (! $this->option('dry-run')) {
                    $committee->update([
                        'website_url' => $result['url'],
                        'website_source_url' => $result['source'],
                        'website_discovered_at' => now(),
                    ]);
                }
                $found++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn($committee->fec_committee_id.': '.$e->getMessage());
            }
        }
        $this->info("Done. Found: {$found} | Not found: {$missed} | Failed: {$failed}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
