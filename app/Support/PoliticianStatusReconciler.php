<?php

namespace App\Support;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;

/**
 * Feed-agnostic parts of politician lifecycle reconciliation — deactivating
 * a stale unclaimed profile and expiring its matching ElectionCandidateRecord
 * rows — extracted from ReconcilePoliticianStatus (which pairs these with
 * the federal-only congress-legislators feed check) so
 * ReconcileStateLocalStatus can reuse them without a feed of its own.
 */
class PoliticianStatusReconciler
{
    /**
     * @param  (\Closure(string): void)|null  $onLine  Called with a human-readable line for each action taken (e.g. the issuing command's $this->line(...)).
     */
    public function __construct(private readonly ?\Closure $onLine = null)
    {
    }

    /**
     * Stamp matching ElectionCandidateRecord rows as eliminated so they are
     * excluded from the district-lookup "Running Candidates" section.
     * Matches by lower-cased full_name + upper-cased state.
     */
    public function expireEcrRows(Politician $politician, bool $dryRun): void
    {
        $name = strtolower(trim((string) $politician->full_name));
        $state = strtoupper(trim((string) ($politician->state ?? '')));

        if ($name === '' || $state === '') {
            return;
        }

        $rows = ElectionCandidateRecord::query()
            ->whereRaw('LOWER(full_name) = ?', [$name])
            ->whereRaw('UPPER(state) = ?', [$state])
            ->whereRaw("COALESCE(payload->>'$.primary_result', '') != 'eliminated'")
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->line("  └─ [ECR EXPIRED] {$politician->full_name} — marking {$rows->count()} ECR row(s) as eliminated");

        if ($dryRun) {
            return;
        }

        foreach ($rows as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $payload['primary_result'] = 'eliminated';
            $record->update(['payload' => $payload]);
        }
    }

    /**
     * Deactivate a profile only if unclaimed and has no active campaigns.
     * Never deactivates claimed (user_id set) profiles.
     */
    public function maybeDeactivate(Politician $politician, bool $dryRun): bool
    {
        if ($politician->user_id !== null) {
            return false;
        }

        $hasActiveCampaigns = $politician->campaigns()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveCampaigns) {
            return false;
        }

        $this->line("  └─ [DEACTIVATED] {$politician->full_name} — no active campaigns, unclaimed");
        if (! $dryRun) {
            $politician->update([
                'is_active' => false,
                'page_published' => false,
            ]);
        }

        return true;
    }

    private function line(string $text): void
    {
        if ($this->onLine !== null) {
            ($this->onLine)($text);
        }
    }
}
