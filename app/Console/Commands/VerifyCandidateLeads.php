<?php

namespace App\Console\Commands;

use App\Models\CandidateLead;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Services\CandidateDiscovery\CandidateVerificationRegistry;
use App\Support\CandidateNameCanonicalizer;
use Illuminate\Console\Command;

class VerifyCandidateLeads extends Command
{
    protected $signature = 'candidates:verify-leads
        {--limit=200     : Max leads to inspect per run.}
        {--state=        : Only leads for this two-letter state.}
        {--name=         : Only leads whose name contains this text (use with --relink: a lead cannot tell a nominee from a primary loser).}
        {--recheck       : Also re-verify leads left "verified" below the threshold, or rejected by the AI tier.}
        {--relink        : Re-promote "promoted" leads whose record was deleted, when the FEC roster or a non-news record corroborates the name.}
        {--dry-run       : Report only — no DB writes.}
        {--skip-ai       : Use Ballotpedia/Wikipedia tiers only (no Anthropic call).}
        {--require-ai    : Fail when ANTHROPIC_API_KEY is missing.}';

    protected $description = 'Verify pending candidate_leads via the tiered Ballotpedia/Wikipedia/LLM registry and auto-promote high-confidence results.';

    /** Confidence threshold at which a verified lead auto-promotes. */
    private const PROMOTE_THRESHOLD = 0.85;

    /**
     * A lead the FEC roster (or a non-news record) independently confirms is a real
     * person on the ballot for that chamber — Wikipedia's 0.80 is enough then.
     */
    private const CORROBORATED_THRESHOLD = 0.7;

    private ?CandidateCorroboration $corroboration = null;

    public function handle(CandidateVerificationRegistry $registry, CandidateLeadPromoter $promoter): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $skipAi = (bool) $this->option('skip-ai');
        $requireAi = (bool) $this->option('require-ai');
        $apiKey = (string) config('services.anthropic.api_key');
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $name = trim((string) $this->option('name')) ?: null;

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

        $leads = CandidateLead::query()
            ->when($state, fn ($q) => $q->where('state', $state))
            ->when($name, fn ($q) => $q->where('full_name', 'like', "%{$name}%"))
            ->where(function ($q) {
                $q->where('status', CandidateLead::STATUS_PENDING);
                if ($this->option('recheck')) {
                    $q->orWhere('status', CandidateLead::STATUS_VERIFIED)
                        ->orWhere(fn ($r) => $r->where('status', CandidateLead::STATUS_REJECTED)->where('verifier_key', 'llm_anthropic'));
                }
            })
            ->orderBy('discovered_at')
            ->limit($limit)
            ->get();

        $this->info("Verifying {$leads->count()} lead(s)...\n");

        $stats = ['promoted' => 0, 'verified' => 0, 'rejected' => 0, 'unresolved' => 0, 'relinked' => 0];

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

        if ($this->option('relink')) {
            $this->relink($state, $name, $limit, $dryRun, $promoter, $stats);
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            "\nVerification complete%s: %d promoted, %d verified (below threshold), %d rejected, %d unresolved, %d relinked.",
            $suffix, $stats['promoted'], $stats['verified'], $stats['rejected'], $stats['unresolved'], $stats['relinked']
        ));

        return self::SUCCESS;
    }

    /**
     * @param array{status:string, confidence:float, reason:string,
     *   verified_payload:array<string,mixed>, verifier_key:string} $result
     * @param  array<string,int>  $stats
     */
    private function applyResult(CandidateLead $lead, array $result, bool $dryRun, CandidateLeadPromoter $promoter, array &$stats): void
    {
        $confidence = (float) $result['confidence'];
        $isRejected = $result['status'] === 'rejected';
        $corroborated = ! $isRejected && $confidence >= self::CORROBORATED_THRESHOLD && $confidence < self::PROMOTE_THRESHOLD
            && $this->corroborated($lead);
        $willPromote = ! $isRejected && ($confidence >= self::PROMOTE_THRESHOLD || $corroborated);

        $icon = $isRejected ? '✗' : ($willPromote ? '✓' : '~');
        $label = $isRejected ? 'rejected' : ($willPromote ? 'promoted' : 'verified');
        $note = $corroborated ? ' +FEC/record corroborated' : '';
        $this->line("  <fg=cyan>{$icon}</> {$lead->full_name} ({$lead->state}) — [{$result['verifier_key']}] {$label} ({$confidence}){$note}");

        if ($dryRun) {
            $stats[$isRejected ? 'rejected' : ($willPromote ? 'promoted' : 'verified')]++;

            return;
        }

        $lead->update([
            'verifier_key' => $result['verifier_key'],
            'confidence' => $confidence,
            'reason' => $corroborated ? trim($result['reason'].' | corroborated by FEC roster/record') : $result['reason'],
            'verified_payload' => $result['verified_payload'],
            'status' => $isRejected ? CandidateLead::STATUS_REJECTED : CandidateLead::STATUS_VERIFIED,
            'resolved_at' => $isRejected || ! $willPromote ? now() : null,
        ]);

        if ($willPromote) {
            $record = $promoter->promote($lead->fresh());
            if ($record !== null) {
                $stats['promoted']++;
            } else {
                $this->line("  <fg=red>✗</> {$lead->full_name} — blocked by name-quality guard, marked rejected");
                $stats['rejected']++;
            }
        } elseif ($isRejected) {
            $stats['rejected']++;
        } else {
            $stats['verified']++;
        }
    }

    /**
     * Promoted leads whose ECR was deleted (politicians:prune-junk-ecrs removes
     * rows dated before the current cycle) stay "promoted" with a null link and
     * are never looked at again. Re-create the record — but only for a name an
     * independent source vouches for, so purged junk is not resurrected.
     *
     * A lead cannot tell a nominee from a primary loser (Crockett, Allred and Cornyn
     * are FEC filers too), so relink a whole state only after reviewing a --dry-run;
     * otherwise target the people you know with --name.
     *
     * @param  array<string,int>  $stats
     */
    private function relink(?string $state, ?string $name, int $limit, bool $dryRun, CandidateLeadPromoter $promoter, array &$stats): void
    {
        $orphans = CandidateLead::query()
            ->where('status', CandidateLead::STATUS_PROMOTED)
            ->whereNull('election_candidate_record_id')
            ->when($state, fn ($q) => $q->where('state', $state))
            ->when($name, fn ($q) => $q->where('full_name', 'like', "%{$name}%"))
            ->orderBy('discovered_at')
            ->limit($limit)
            ->get();

        $this->info("\nRelinking: {$orphans->count()} promoted lead(s) with no record...");

        foreach ($orphans as $lead) {
            if (($lead->verified_payload['primary_result'] ?? null) === 'eliminated' || ! $this->corroborated($lead)) {
                continue;
            }

            $this->line("  <fg=cyan>↻</> {$lead->full_name} ({$lead->state}) — corroborated, re-creating record");

            if ($dryRun || $promoter->promote($lead) !== null) {
                $stats['relinked']++;
            }
        }
    }

    /** Independent confirmation (FEC roster or a non-news record) — a sitting official of another race is not enough. */
    private function corroborated(CandidateLead $lead): bool
    {
        $check = ($this->corroboration ??= new CandidateCorroboration)->checkIdentity(
            CandidateNameCanonicalizer::canonicalize($lead->full_name),
            $lead->state,
            (string) ($lead->verified_payload['political_office'] ?? $lead->office_hint),
        );

        return $check['corroborated'] && $check['source'] !== 'official';
    }
}
