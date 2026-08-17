<?php

namespace App\Console\Commands;

use App\Models\CandidateLead;
use App\Services\CandidateDiscovery\CandidateDiscoveryRegistry;
use Illuminate\Console\Command;

class DiscoverCandidateLeads extends Command
{
    protected $signature = 'candidates:discover-leads
        {--source=* : Discovery source keys to run. Omit to run all registered sources.}
        {--state=   : Restrict to one two-letter state code.}
        {--office=  : Restrict to one office slug (senate|governor).}
        {--dry-run  : Report only — no DB writes.}';

    protected $description = 'Discover new candidate leads from configured discovery sources (RSS, etc.) into candidate_leads.';

    public function handle(CandidateDiscoveryRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sources = $registry->resolveMany((array) $this->option('source'));

        if ($sources === []) {
            $this->error('No valid discovery sources resolved. Check --source values.');
            return self::FAILURE;
        }

        $discoverOptions = array_filter([
            'state' => $this->option('state'),
            'office' => $this->option('office'),
        ]);

        $created = 0;
        $duplicate = 0;

        foreach ($sources as $source) {
            $leads = $source->discover($discoverOptions);
            $this->info("[{$source->key()}] discovered " . count($leads) . ' raw signal(s).');

            foreach ($leads as $lead) {
                $hash = hash('sha256', strtolower(trim($lead['source_url'])));
                $exists = CandidateLead::where('source_key', $source->key())
                    ->where('source_hash', $hash)
                    ->exists();

                if ($exists) {
                    $duplicate++;
                    continue;
                }

                $article = $lead['raw'] ?? [];
                $context = trim((string) ($article['headline'] ?? '') . '. ' . (string) ($article['snippet'] ?? ''));

                $this->line("  <fg=cyan>+</> {$lead['full_name']} ({$lead['state']}, {$lead['office_hint']})");

                if (! $dryRun) {
                    CandidateLead::create([
                        'source_key' => $source->key(),
                        'full_name' => $lead['full_name'],
                        'state' => $lead['state'],
                        'office_hint' => $lead['office_hint'],
                        'source_url' => $lead['source_url'],
                        'discovery_context' => $context !== '.' ? $context : null,
                        'discovered_at' => $lead['published_at'] ?? now(),
                        'status' => CandidateLead::STATUS_PENDING,
                    ]);
                }

                $created++;
            }
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info("\nDiscovery complete{$suffix}: {$created} new lead(s), {$duplicate} already known.");

        return self::SUCCESS;
    }
}
