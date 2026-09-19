<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Support\CrossStateImpostors;
use App\Support\MapCandidateHygiene;
use App\Support\PoliticianDataRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds public profiles that shouldn't exist (unclaimed, unverified, not seated).
 *
 * Deactivated automatically — the evidence is a cross-reference against a real
 * sitting official, not a guess about spelling:
 *  - cross-state impostor: the name matches a sitting statewide official of ANOTHER
 *    state for the same office (Greg Abbott as NC / NY Governor).
 *  - mangled duplicate: headline text stuck on the name of the sitting official of
 *    THIS state ("Greg Abbott's" in Texas).
 *
 * Queued for admin review (Admin → Data Quality), never applied:
 *  - any other name that reads as headline text ("Hochul Agenda", "Marsha Blackburn Will"),
 *    including a sitting official's surname plus one more word — heuristics, not proof.
 *
 * Deactivating only unpublishes the profile (is_active / page_published = false);
 * nothing is deleted, and every automatic one leaves an approved review row behind.
 * Seated, verified and claimed profiles are never touched.
 */
class FlagSuspectProfiles extends Command
{
    protected $signature = 'politicians:flag-suspect-profiles
        {--apply : Deactivate confirmed impostors/duplicates and queue the rest (default: report only)}
        {--state= : Restrict to a two-letter state code}
        {--max-auto=50 : Most profiles to deactivate automatically in one run; the rest are queued}';

    protected $description = 'Deactivate profiles that duplicate a sitting official (cross-state impostors, "Greg Abbott\'s") and queue other headline-text names for review.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $maxAuto = max(0, (int) $this->option('max-auto'));
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;

        $auto = 0;
        $queued = 0;
        $found = $this->suspects($state);

        foreach ($found as [$pol, $reason, $extra, $autoEligible]) {
            $deactivate = $autoEligible && $auto < $maxAuto;
            $this->line(sprintf('  [%s] #%d %s (%s, %s) — %s', $this->label($deactivate, $apply), $pol->id, $pol->full_name, $pol->political_office, $pol->state, $reason));

            if ($deactivate) {
                $auto++;
                $apply && $this->deactivate($pol, $reason, $extra);

                continue;
            }

            $queued++;
            $apply && PoliticianCleanupReview::enqueue(PoliticianCleanupReview::TYPE_DEACTIVATE, $pol->id, null, $this->payload($pol, $extra), $reason);
        }

        $this->summary(count($found), $auto, $queued, $apply);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: Politician, 1: string, 2: array<string, mixed>, 3: bool}>  [profile, reason, payload extras, auto-deactivate?]
     */
    private function suspects(?string $state): array
    {
        $holders = CrossStateImpostors::seatedHolders();
        $surnames = CrossStateImpostors::surnameIndex($holders);
        $found = [];

        Politician::query()
            ->where('is_active', true)
            ->whereNull('user_id')
            ->where('verified_official', false)
            ->where(fn ($q) => $q->where('term_status', '!=', 'seated')->orWhereNull('term_status'))
            ->when($state, fn ($q) => $q->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state]))
            ->select(['id', 'full_name', 'political_office', 'state'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($holders, $surnames, &$found): void {
                foreach ($rows as $pol) {
                    if ($finding = $this->classify($pol, $holders, $surnames)) {
                        $found[] = [$pol, ...$finding];
                    }
                }
            });

        return $found;
    }

    /**
     * @param  array<string, array<int, array{id: int, state: string, name: string}>>  $holders
     * @param  array<string, array<string, array{id: int, state: string, name: string}>>  $surnames
     * @return array{0: string, 1: array<string, mixed>, 2: bool}|null
     */
    private function classify(Politician $pol, array $holders, array $surnames): ?array
    {
        $elsewhere = CrossStateImpostors::holderElsewhere($pol->full_name, $pol->political_office, $pol->state, $holders);
        if ($elsewhere !== null) {
            return ["Sitting {$pol->political_office} of {$elsewhere['state']} ({$elsewhere['name']} #{$elsewhere['id']}) — not a candidate in {$pol->state}", ['kept_politician_id' => $elsewhere['id'], 'kept_state' => $elsewhere['state']], true];
        }

        $problem = PoliticianDataRules::headlineFragmentViolation($pol->full_name) ?? MapCandidateHygiene::nameProblem($pol->full_name);
        if ($problem === null) {
            $stub = CrossStateImpostors::surnameStub($pol->full_name, $pol->state, $surnames);

            return $stub === null ? null : ["Reads like a headline about {$stub['name']} #{$stub['id']}: \"{$pol->full_name}\" is their surname plus another word", ['kept_politician_id' => $stub['id'], 'kept_state' => $stub['state']], false];
        }

        $real = CrossStateImpostors::holderInState($pol->full_name, $pol->political_office, $pol->state, $holders);
        if ($real !== null && $real['id'] !== $pol->id) {
            return ["Duplicate of the sitting {$pol->political_office} {$real['name']} #{$real['id']} with headline text added to the name", ['kept_politician_id' => $real['id'], 'kept_state' => $real['state']], true];
        }

        return ['Name is headline text, not a person: '.$problem, [], false];
    }

    private function label(bool $deactivate, bool $apply): string
    {
        $verb = $deactivate ? 'DEACTIVATE' : 'QUEUE';

        return $apply ? $verb : "WOULD {$verb}";
    }

    private function summary(int $total, int $auto, int $queued, bool $apply): void
    {
        $autoNote = $apply ? 'deactivated (confirmed against a sitting official)' : 'would be deactivated';
        $queueNote = $apply ? 'queued for review' : 'would be queued';

        $this->info("{$total} suspect profile(s): {$auto} {$autoNote}, {$queued} {$queueNote}.".($apply ? '' : ' Report only — use --apply to act.'));
    }

    /** @param  array<string, mixed>  $extra */
    private function deactivate(Politician $pol, string $reason, array $extra): void
    {
        DB::transaction(function () use ($pol, $reason, $extra): void {
            // Through the model, not a bulk update, so the map's per-state cache is busted.
            Politician::find($pol->id)?->update(['is_active' => false, 'page_published' => false]);

            $note = 'Auto-approved: '.$reason;
            $pending = PoliticianCleanupReview::query()
                ->where('review_type', PoliticianCleanupReview::TYPE_DEACTIVATE)
                ->where('politician_id', $pol->id)
                ->where('status', PoliticianCleanupReview::STATUS_PENDING)
                ->first();

            $fields = [
                'payload' => $this->payload($pol, $extra) + ['auto_approved' => true],
                'status' => PoliticianCleanupReview::STATUS_APPROVED,
                'reason' => $note,
                'reviewed_at' => now(),
            ];

            $pending
                ? $pending->update($fields)
                : PoliticianCleanupReview::create($fields + [
                    'review_type' => PoliticianCleanupReview::TYPE_DEACTIVATE,
                    'politician_id' => $pol->id,
                    'duplicate_politician_id' => null,
                ]);
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Politician $pol, array $extra): array
    {
        return ['source' => 'flag-suspect-profiles', 'full_name' => $pol->full_name, 'political_office' => $pol->political_office, 'state' => $pol->state] + $extra;
    }
}
