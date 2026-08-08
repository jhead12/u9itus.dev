<?php

namespace App\Console\Commands;

use App\Models\CandidateLead;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Services\CandidateDiscovery\CandidateVerificationRegistry;
use Illuminate\Console\Command;

class VerifyCandidateLeads extends Command
{
    protected $signature = 'candidates:verify-leads
        {--limit=200     : Max pending leads to inspect per run.}
        {--dry-run       : Report only — no DB writes.}
        {--skip-ai       : Use Ballotpedia/Wikipedia tiers only (no Anthropic call).}
        {--require-ai    : Fail when ANTHROPIC_API_KEY is missing.}';

    protected $description = 'Verify pending candidate_leads via the tiered Ballotpedia/Wikipedia/LLM registry and auto-promote high-confidence results.';

    /** Confidence threshold at which a verified lead auto-promotes. */
    private const PROMOTE_THRESHOLD = 0.85;

    public function handle(CandidateVerificationRegistry $registry, CandidateLeadPromoter $promoter): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $skipAi = (bool) $this->option('skip-ai');
        $requireAi = (bool) $this->option('require-ai');
        $apiKey = (string) config('services.anthropic.api_key');

        if (! $skipAi && $apiKey === '' && $requireAi) {
            $this->error('ANTHROPIC_API_KEY is missing and --require-ai was set.');
            return self::FAILURE;
        }

        if (! $skipAi && $apiKey === '') {
            $this->warn('ANTHROPIC_API_KEY missing: running Ballotpedia/Wikipedia tiers only.');
            $skipAi = true;
        }

        $allowAi = ! $skipAi;

        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No DB writes will occur.</>');
        }

        $leads = CandidateLead::where('status', CandidateLead::STATUS_PENDING)
            ->orderBy('discovered_at')
            ->limit($limit)
            ->get();

        $this->info("Verifying {$leads->count()} pending lead(s)...\n");

        $stats = ['promoted' => 0, 'verified' => 0, 'rejected' => 0, 'unresolved' => 0];

        foreach ($leads as $lead) {
            $result = $registry->verifyTiered($lead, $allowAi);

            // Circuit breaker: first quota-exhausted response flips $allowAi for
            // the rest of the run — no more wasted API calls — then re-checks
            // this same lead against the heuristic-only tiers.
            if (($result['status'] ?? null) === 'quota_exhausted') {
                $this->warn('  ⚡ Anthropic quota exhausted — switching to Ballotpedia/Wikipedia-only for remaining leads.');
                $allowAi = false;
                $result = $registry->verifyTiered($lead, false);
            }

            if ($result === null) {
                $stats['unresolved']++;
                $this->line("  <fg=yellow>?</> {$lead->full_name} ({$lead->state}) — no tier could confirm yet, left pending");
                continue;
            }

            $this->applyResult($lead, $result, $dryRun, $promoter, $stats);
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            "\nVerification complete%s: %d promoted, %d verified (below threshold), %d rejected, %d unresolved.",
            $suffix, $stats['promoted'], $stats['verified'], $stats['rejected'], $stats['unresolved']
        ));

        return self::SUCCESS;
    }

    /**
     * @param array{status:string, confidence:float, reason:string,
     *   verified_payload:array<string,mixed>, verifier_key:string} $result
     * @param array<string,int> $stats
     */
    private function applyResult(CandidateLead $lead, array $result, bool $dryRun, CandidateLeadPromoter $promoter, array &$stats): void
    {
        $confidence = (float) $result['confidence'];
        $isRejected = $result['status'] === 'rejected';
        $willPromote = ! $isRejected && $confidence >= self::PROMOTE_THRESHOLD;

        $icon = $isRejected ? '✗' : ($willPromote ? '✓' : '~');
        $label = $isRejected ? 'rejected' : ($willPromote ? 'promoted' : 'verified');
        $this->line("  <fg=cyan>{$icon}</> {$lead->full_name} ({$lead->state}) — [{$result['verifier_key']}] {$label} ({$confidence})");

        if ($dryRun) {
            $stats[$isRejected ? 'rejected' : ($willPromote ? 'promoted' : 'verified')]++;
            return;
        }

        $lead->update([
            'verifier_key' => $result['verifier_key'],
            'confidence' => $confidence,
            'reason' => $result['reason'],
            'verified_payload' => $result['verified_payload'],
            'status' => $isRejected ? CandidateLead::STATUS_REJECTED : CandidateLead::STATUS_VERIFIED,
            'resolved_at' => $isRejected || ! $willPromote ? now() : null,
        ]);

        if ($willPromote) {
            $promoter->promote($lead->fresh());
            $stats['promoted']++;
        } elseif ($isRejected) {
            $stats['rejected']++;
        } else {
            $stats['verified']++;
        }
    }
}
