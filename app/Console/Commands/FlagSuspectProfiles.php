<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Support\CrossStateImpostors;
use App\Support\MapCandidateHygiene;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;

/**
 * Queues public profiles that shouldn't exist for admin review (Admin → Data
 * Quality). Nothing is deactivated here — approving a "deactivate" review is
 * the admin's call.
 *
 *  - cross-state impostors: an unclaimed, unverified "candidate" whose name
 *    matches a sitting statewide official of another state for the same
 *    office (Greg Abbott as NC / NY Governor).
 *  - headline text saved as a name ("Greg Abbott's").
 *
 * Seated, verified, and claimed profiles are never flagged.
 */
class FlagSuspectProfiles extends Command
{
    protected $signature = 'politicians:flag-suspect-profiles
        {--apply : Queue the findings for admin review (default: report only)}
        {--state= : Restrict to a two-letter state code}';

    protected $description = 'Queue cross-state impostor and headline-fragment profiles for admin review (never auto-deactivates).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;

        $holders = CrossStateImpostors::seatedHolders();
        $found = [];

        Politician::query()
            ->where('is_active', true)
            ->whereNull('user_id')
            ->where('verified_official', false)
            ->where(fn ($q) => $q->where('term_status', '!=', 'seated')->orWhereNull('term_status'))
            ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state]))
            ->select(['id', 'full_name', 'political_office', 'state'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($holders, &$found): void {
                foreach ($rows as $pol) {
                    $holder = CrossStateImpostors::holderElsewhere($pol->full_name, $pol->political_office, $pol->state, $holders);
                    if ($holder !== null) {
                        $found[] = [$pol, "Sitting {$pol->political_office} of {$holder['state']} ({$holder['name']} #{$holder['id']}) — not a candidate in {$pol->state}", ['kept_politician_id' => $holder['id'], 'kept_state' => $holder['state']]];

                        continue;
                    }

                    $name = PoliticianDataRules::headlineFragmentViolation($pol->full_name) ?? MapCandidateHygiene::nameProblem($pol->full_name);
                    if ($name !== null) {
                        $found[] = [$pol, 'Name is headline text, not a person: '.$name, []];
                    }
                }
            });

        $this->info(sprintf('%d suspect profile(s)%s.', count($found), $apply ? ' — queued for review' : ' (report only; use --apply to queue)'));

        foreach ($found as [$pol, $reason, $extra]) {
            $this->line(sprintf('  #%d %s (%s, %s) — %s', $pol->id, $pol->full_name, $pol->political_office, $pol->state, $reason));

            if ($apply) {
                PoliticianCleanupReview::enqueue(
                    PoliticianCleanupReview::TYPE_DEACTIVATE,
                    $pol->id,
                    null,
                    ['source' => 'flag-suspect-profiles', 'full_name' => $pol->full_name, 'political_office' => $pol->political_office, 'state' => $pol->state] + $extra,
                    $reason,
                );
            }
        }

        return self::SUCCESS;
    }
}
