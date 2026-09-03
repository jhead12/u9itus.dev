<?php

namespace App\Jobs;

use App\Mail\StateBallotMeasuresReadyMail;
use App\Models\BallotMeasure;
use App\Models\ElectionDataBackfill;
use App\Models\StateElectionDate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * On-demand, single-state run of the civic pipeline, fired when a search
 * (WebMcpController::ballotMeasures, the map state panel) turns up no ballot
 * measures for a state. Runs the same commands the scheduler runs, but scoped
 * to one state, then records the outcome on election_data_backfills so the next
 * search can report "still gathering" / "nothing published yet" without
 * re-running — and emails anyone watching once the state has data.
 *
 * Debounced by the caller (Cache::add on "civic:backfill:<state>"); this job
 * also refuses to run if the row is already `running` or was tried very
 * recently, so overlapping dispatches are harmless.
 */
class BackfillStateElectionData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly string $state) {}

    public function handle(): void
    {
        $state = strtoupper($this->state);

        $row = ElectionDataBackfill::firstOrCreate(['state' => $state]);

        if ($row->status === ElectionDataBackfill::STATUS_RUNNING
            && $row->last_attempted_at?->gt(now()->subMinutes(10))) {
            return; // another worker already on it
        }

        $row->update([
            'status' => ElectionDataBackfill::STATUS_RUNNING,
            'attempts' => $row->attempts + 1,
            'last_attempted_at' => now(),
            'last_error' => null,
        ]);

        try {
            Artisan::call('elections:sync-dates', ['--state' => $state, '--year' => (int) now()->year]);
            Artisan::call('civic:pull-measures', ['--state' => $state, '--sleep' => 0]);
            Artisan::call('civic:scrape-measures', ['--state' => $state, '--sleep' => 0]);
        } catch (\Throwable $e) {
            Log::error('BackfillStateElectionData failed', ['state' => $state, 'error' => $e->getMessage()]);
            $row->update([
                'status' => ElectionDataBackfill::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 255),
            ]);

            return;
        }

        $measures = BallotMeasure::where('state', $state)->count();
        $elections = count(StateElectionDate::upcomingForState($state));

        $row->update([
            'measures_found' => $measures,
            'elections_found' => $elections,
            'status' => $measures > 0
                ? ElectionDataBackfill::STATUS_READY
                : ElectionDataBackfill::STATUS_UNAVAILABLE,
        ]);

        if ($measures > 0) {
            $this->notifyWatchers($row->fresh(), $state, $measures);
        }
    }

    private function notifyWatchers(ElectionDataBackfill $row, string $state, int $measures): void
    {
        $emails = $row->watcherEmails();
        if ($emails === []) {
            return;
        }

        $url = url("/map?state={$state}&panel=measures");

        foreach ($emails as $email) {
            Mail::to($email)->queue(new StateBallotMeasuresReadyMail($state, $measures, $url));
        }

        $row->update(['watch_emails' => null]);
    }
}
