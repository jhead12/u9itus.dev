<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\Politician;
use App\Support\PoliticianStatusReconciler;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Non-federal counterpart to politicians:reconcile-status. State/local
 * officials have no equivalent to the congress-legislators feed, so this
 * uses signals already on the table instead of an external feed:
 *
 *  - RETIRED  term_status='seated' and term_ends_on has passed.
 *  - LOST     is_running_candidate=true and a linked ElectionCandidateRecord
 *             has payload.primary_result='eliminated' (the same signal
 *             politicians:sync-primary-results, CleanCrossOfficeEcrs, and
 *             politicians:reconcile-status's own expiry pass all write).
 *
 * No "seated"/win promotion here — there's no authoritative feed to confirm
 * it from; that stays handled by politicians:detect-election-wins (AI-
 * confirmed from news coverage) and the Ballotpedia-scrape-based
 * politicians:import-election-results, both of which already work for
 * state/local candidates.
 *
 * Deactivation (unclaimed + no active campaigns) reuses
 * PoliticianStatusReconciler, shared with politicians:reconcile-status.
 *
 * Usage:
 *   php artisan politicians:reconcile-status-state-local              # dry run
 *   php artisan politicians:reconcile-status-state-local --state=CA
 */
class ReconcileStateLocalStatus extends Command
{
    protected $signature = 'politicians:reconcile-status-state-local
        {--state=  : Two-letter state code — limit to one state}
        {--dry-run : Report changes without writing to DB}';

    protected $description = 'Reconcile state/local politician term status (retired, lost) using term_ends_on and primary-result signals already on the table.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $reconciler = new PoliticianStatusReconciler(fn (string $text) => $this->line($text));

        $stats = ['retired' => 0, 'lost' => 0, 'deactivated' => 0, 'skipped' => 0];

        Politician::query()
            ->whereNull('user_id')
            ->where(function ($q) {
                $q->where('governance_level', '!=', 'Federal')
                    ->orWhereNull('governance_level');
            })
            ->whereNotIn('political_office', [
                'U.S. Representative', 'U.S. Senator',
                'United States Representative', 'United States Senator',
            ])
            ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, "")) = ?', [$state]))
            ->chunkById(200, function ($politicians) use ($dryRun, $reconciler, &$stats): void {
                foreach ($politicians as $politician) {
                    $result = $this->reconcileOne($politician, $dryRun, $reconciler);
                    $stats[$result]++;
                }
            });

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            'Reconcile complete%s: %d retired | %d lost | %d deactivated | %d skipped',
            $suffix,
            $stats['retired'],
            $stats['lost'],
            $stats['deactivated'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }

    private function reconcileOne(Politician $politician, bool $dryRun, PoliticianStatusReconciler $reconciler): string
    {
        // ── Retired: was seated, term has ended ────────────────────────────
        if ($politician->term_status === 'seated' && $politician->term_ends_on !== null
            && Carbon::parse($politician->term_ends_on)->lt(now())) {
            $this->line("[RETIRED] {$politician->full_name} ({$politician->state})");

            if (! $dryRun) {
                $politician->update([
                    'term_status' => 'retired',
                    'status_updated_at' => now(),
                ]);
                $reconciler->expireEcrRows($politician, $dryRun);
            }

            $politician->refresh();

            return $reconciler->maybeDeactivate($politician, $dryRun) ? 'deactivated' : 'retired';
        }

        // ── Lost: still flagged running, but a linked ECR was eliminated ──
        if ($politician->is_running_candidate) {
            $eliminated = CandidateIdentityLink::query()
                ->where('politician_id', $politician->id)
                ->whereHas('candidateRecord', function ($q) {
                    $q->whereRaw("COALESCE(payload->>'$.primary_result', '') = 'eliminated'");
                })
                ->exists();

            if ($eliminated) {
                $this->line("[LOST] {$politician->full_name} ({$politician->state}) — linked candidate record marked eliminated");

                if (! $dryRun) {
                    $politician->update([
                        'term_status' => 'lost',
                        'is_running_candidate' => false,
                        'status_updated_at' => now(),
                    ]);
                    $reconciler->expireEcrRows($politician, $dryRun);
                }

                $politician->refresh();

                return $reconciler->maybeDeactivate($politician, $dryRun) ? 'deactivated' : 'lost';
            }
        }

        return 'skipped';
    }
}
