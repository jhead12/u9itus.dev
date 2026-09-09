<?php

namespace App\Console\Commands;

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Services\CandidateDiscovery\CandidateDiscoveryRegistry;
use App\Support\CandidateNameCanonicalizer;
use Illuminate\Console\Command;

class DiscoverCandidateLeads extends Command
{
    protected $signature = 'candidates:discover-leads
        {--source=* : Discovery source keys to run. Omit to run all registered sources.}
        {--state=   : Restrict to one two-letter state code.}
        {--office=  : Restrict to one office slug (senate|governor|house).}
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
        $alreadyTracked = 0;

        foreach ($sources as $source) {
            $leads = $source->discover($discoverOptions);
            $this->info("[{$source->key()}] discovered ".count($leads).' raw signal(s).');

            foreach ($leads as $lead) {
                $hash = hash('sha256', strtolower(trim($lead['source_url'])));
                $exists = CandidateLead::where('source_key', $source->key())
                    ->where('source_hash', $hash)
                    ->exists();

                if ($exists) {
                    $duplicate++;

                    continue;
                }

                // The URL-hash check above only dedupes the same *article*.
                // Skip the lead entirely when the *person* is already tracked
                // — an existing politician, an open lead from another article,
                // or a prior discovery record — so verification quota isn't
                // spent re-confirming known candidates and the ECR table
                // doesn't refill with per-headline duplicates.
                if ($this->alreadyTracked($source->key(), $lead)) {
                    $alreadyTracked++;

                    continue;
                }

                $article = $lead['raw'] ?? [];
                $context = trim((string) ($article['headline'] ?? '').'. '.(string) ($article['snippet'] ?? ''));

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
        $this->info("\nDiscovery complete{$suffix}: {$created} new lead(s), {$duplicate} duplicate article(s), {$alreadyTracked} already-tracked candidate(s).");

        return self::SUCCESS;
    }

    /**
     * Whether the candidate behind this RSS lead is already in the system.
     *
     * @param  array{full_name:string, state:?string, office_hint:?string}  $lead
     */
    private function alreadyTracked(string $sourceKey, array $lead): bool
    {
        $name = strtolower(trim(CandidateNameCanonicalizer::canonicalize($lead['full_name'])));
        $state = strtoupper((string) ($lead['state'] ?? ''));

        if ($name === '' || $state === '') {
            return false;
        }

        $activePolitician = Politician::query()
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [$name])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where(function ($q) {
                $q->whereIn('term_status', ['seated', 'running', 'active'])
                    ->orWhere('is_running_candidate', true)
                    ->orWhereNotNull('user_id');
            })
            ->exists();

        if ($activePolitician) {
            return true;
        }

        $openLead = CandidateLead::query()
            ->where('source_key', $sourceKey)
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [$name])
            ->where('state', $lead['state'])
            ->where('office_hint', $lead['office_hint'])
            ->where('status', '!=', CandidateLead::STATUS_REJECTED)
            ->exists();

        if ($openLead) {
            return true;
        }

        return ElectionCandidateRecord::query()
            ->where('source', 'candidate_discovery')
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [$name])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->exists();
    }
}
