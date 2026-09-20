<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Support\ElectionCycle;
use App\Support\MapCandidateHygiene;
use Illuminate\Console\Command;

/**
 * Manual override for a primary result the automatic sync got wrong (it once
 * stamped Mike Rogers "eliminated" from a page about his 2024 loss). Sets the
 * result on this cycle's records for one person, and puts a linked profile back
 * in the running when the result is not "eliminated". The sync skips a record
 * that already has a result, so an override sticks.
 *
 *   php artisan candidates:set-primary-result --state=MI --name="Mike Rogers" --office=Senator --result=advanced_to_general
 *   ... add --apply to write
 */
class SetCandidatePrimaryResult extends Command
{
    protected $signature = 'candidates:set-primary-result
        {--state=   : Two-letter state code (required)}
        {--name=    : Full name (required); matched by identity, so "Ken" finds "Kenneth"}
        {--office=  : Only records whose office contains this text (e.g. Senator, Governor)}
        {--result=  : advanced_to_general | running | eliminated (required)}
        {--apply    : Write the change (default is dry-run)}';

    protected $description = 'Manually set the primary result on a candidate\'s current-cycle records (and their linked profile).';

    public function handle(): int
    {
        $state = strtoupper(trim((string) $this->option('state')));
        $name = trim((string) $this->option('name'));
        $result = (string) $this->option('result');
        $office = trim((string) $this->option('office'));

        if ($state === '' || $name === '' || ! in_array($result, ['advanced_to_general', 'running', 'eliminated'], true)) {
            $this->error('--state, --name and --result (advanced_to_general|running|eliminated) are required.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $key = MapCandidateHygiene::identityKey($name);
        $cycleStart = now()->startOfYear()->toDateString();

        $records = ElectionCandidateRecord::query()
            ->where('state', $state)
            ->when($office !== '', fn ($q) => $q->where('political_office', 'like', "%{$office}%"))
            ->where(fn ($q) => $q->whereNull('election_date')->orWhere('election_date', '>=', $cycleStart))
            ->get()
            ->filter(fn (ElectionCandidateRecord $r) => in_array($key, MapCandidateHygiene::identityKeys($r->full_name), true));

        if ($records->isEmpty()) {
            $this->warn("No current-cycle record for \"{$name}\" in {$state}.");

            return self::FAILURE;
        }

        $this->line($apply ? '<comment>[LIVE — writing changes]</comment>' : '[DRY RUN — no writes]');

        foreach ($records as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $this->line(sprintf('  #%d "%s" (%s) primary_result %s → %s', $record->id, $record->full_name, $record->political_office, $payload['primary_result'] ?? 'none', $result));

            if ($apply) {
                $payload['primary_result'] = $result;
                $payload['result_source'] = 'manual';
                unset($payload['elimination_note']);
                if ($result === 'advanced_to_general') {
                    $payload['general_date'] = ElectionCycle::generalElectionDate(ElectionCycle::year());
                }
                $record->payload = $payload;
                $record->save();
            }

            foreach (CandidateIdentityLink::where('election_candidate_record_id', $record->id)->with('politician')->get() as $link) {
                $politician = $link->politician;
                if ($politician === null || in_array($politician->term_status, ['seated', 'retired'], true)) {
                    continue;
                }

                $status = $result === 'eliminated' ? 'eliminated' : 'running';
                $this->line(sprintf('    profile #%d term_status %s → %s%s', $politician->id, $politician->term_status, $status, $politician->is_active ? '' : ' (profile is inactive — not reactivated)'));
                if ($apply) {
                    $politician->update(['term_status' => $status, 'is_running_candidate' => $result !== 'eliminated', 'status_updated_at' => now()]);
                }
            }
        }

        $this->info($apply ? 'Done.' : 'Dry run — pass --apply to write.');

        return self::SUCCESS;
    }
}
