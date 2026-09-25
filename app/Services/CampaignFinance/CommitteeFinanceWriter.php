<?php

namespace App\Services\CampaignFinance;

use App\Models\CommitteeDonor;
use App\Models\CommitteeFiler;
use App\Models\CommitteeFinanceSnapshot;
use App\Models\CommitteeTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saves what a state finance importer read, so every state stores committee data the same
 * way and gets the same data-quality controls. Each importer only parses its state's
 * format and hands this class normalized arrays:
 *
 *  - filer:     found, filer_name, latest_filing_on, late_contributions, late_since
 *  - snapshot:  one per statement (or one per year for states without statements)
 *  - donors:    donor_name, entity_type, donor_committee_id, employer, amount, nonmonetary, late
 *  - transfers: to_committee_id, to_committee_name, measure_reference, measure_number,
 *               measure_jurisdiction, position, amount, latest_on
 *
 * anomalies() is the Control check run after each committee is saved, and
 * recordMetrics() reports the run to politician_cleanup_run_metrics for
 * politicians:check-cleanup-health.
 */
class CommitteeFinanceWriter
{
    /** Individual donors kept per committee and year; committee donors are always kept. */
    public const TOP_DONORS = 25;

    public function __construct(
        private readonly string $state,
        private readonly string $source,
        private readonly string $metricsStep,
    ) {}

    /** @param  array<string, mixed>  $attributes */
    public function saveFiler(string $committeeId, array $attributes): void
    {
        CommitteeFiler::updateOrCreate(
            ['state' => $this->state, 'committee_id' => $committeeId],
            $attributes + ['source' => $this->source, 'checked_at' => now()],
        );
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveSnapshot(string $committeeId, string $filingId, array $attributes): void
    {
        CommitteeFinanceSnapshot::updateOrCreate(
            ['state' => $this->state, 'committee_id' => $committeeId, 'filing_id' => $filingId],
            $attributes + ['source' => $this->source],
        );
    }

    /**
     * Replaces the committee's donors for the year: the top individual donors, plus every
     * donor that is itself a committee (so transfers between linked committees can be netted).
     *
     * @param  array<array-key, array<string, mixed>>  $donors
     */
    public function saveDonors(string $committeeId, int $year, array $donors): void
    {
        CommitteeDonor::query()->where('state', $this->state)->where('committee_id', $committeeId)->where('year', $year)->delete();

        collect($donors)->sortByDesc('amount')->values()
            ->filter(fn ($donor, $i) => $i < self::TOP_DONORS || $donor['donor_committee_id'] !== null)
            ->each(fn ($donor) => CommitteeDonor::create($donor + ['state' => $this->state, 'committee_id' => $committeeId, 'year' => $year]));
    }

    /**
     * Upserts contributions the committee made to other committees, keeping any admin's
     * decision to dismiss a suggestion.
     *
     * @param  array<array-key, array<string, mixed>>  $transfers
     */
    public function saveTransfers(string $committeeId, array $transfers): void
    {
        foreach ($transfers as $transfer) {
            CommitteeTransfer::updateOrCreate(
                [
                    'state' => $this->state,
                    'from_committee_id' => $committeeId,
                    'to_committee_id' => $transfer['to_committee_id'],
                    'measure_number' => $transfer['measure_number'],
                ],
                $transfer,
            );
        }
    }

    /** Runs $save for one committee in a transaction. */
    public function transaction(callable $save): void
    {
        DB::transaction($save);
    }

    /**
     * Data anomalies for one committee after its data is saved: a later statement in the
     * same calendar year reporting less raised year to date, or itemized donors adding up to
     * more than the committee reported raising. Either usually means an amendment was
     * mis-read or the state's export changed format.
     *
     * @return list<string>
     */
    public function anomalies(?CommitteeFinanceSnapshot $previous, ?CommitteeFinanceSnapshot $latest, float $itemized): array
    {
        $anomalies = [];

        if ($previous !== null && $latest !== null
            && $previous->filing_id !== $latest->filing_id
            && $previous->period_end !== null && $latest->period_end !== null
            && $previous->period_end->year === $latest->period_end->year
            && $latest->period_end->gte($previous->period_end)
            && $previous->contributions_ytd !== null && $latest->contributions_ytd !== null
            && $latest->contributions_ytd < $previous->contributions_ytd) {
            $anomalies[] = 'total_decreased';
        }

        if ($latest?->contributions_ytd !== null && $itemized > $latest->contributions_ytd * 1.01 + 1) {
            $anomalies[] = 'itemized_exceeds_total';
        }

        return $anomalies;
    }

    public function latest(string $committeeId): ?CommitteeFinanceSnapshot
    {
        return CommitteeFinanceSnapshot::latestFor($this->state, $committeeId);
    }

    /** @param  array<string, int>  $breakdown */
    public function recordMetrics(Carbon $startedAt, int $findings, int $written, array $breakdown): void
    {
        DB::table('politician_cleanup_run_metrics')->insert([
            'step' => $this->metricsStep,
            'scope' => $this->state,
            'exit_code' => 0,
            'findings_count' => $findings,
            'auto_applied_count' => $written,
            'queued_count' => 0,
            'breakdown' => json_encode($breakdown),
            'started_at' => $startedAt,
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
