<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\StateElectionDate;
use App\Support\MapCandidateHygiene;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report-only audit of what the public map can show a voter: names that aren't
 * people, the same person listed twice, and general-election dates that
 * disagree with the state's calendar. Changes nothing — it lists the row ids so
 * they can be fixed at source (MapStateCandidatesController already hides and
 * merges these at display time, see MapCandidateHygiene).
 */
class AuditMapCandidates extends Command
{
    protected $signature = 'map:audit-candidates
        {--state= : Restrict to a two-letter state code}
        {--report= : Also write the findings as JSON to this path}
        {--fail-on-findings : Exit non-zero when anything is found}';

    protected $description = 'Report placeholder names, duplicate people, and election-date conflicts behind the public map (read-only).';

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;

        $findings = [
            'bad_names' => [],
            'duplicates' => [],
            'date_conflicts' => [],
        ];

        $states = $state ? [$state] : $this->statesWithRows();
        foreach ($states as $code) {
            $placeNames = $this->placeNames($code);
            $this->auditNames($code, $placeNames, $findings);
            $this->auditDuplicates($code, $findings);
            $this->auditDates($code, $findings);
        }

        $this->render($findings);

        if ($path = $this->option('report')) {
            file_put_contents($path, json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("Wrote {$path}");
        }

        $total = array_sum(array_map('count', $findings));

        return ($total > 0 && $this->option('fail-on-findings')) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function statesWithRows(): array
    {
        return Politician::query()
            ->whereNotNull('state')->where('state', '!=', '')
            ->distinct()->pluck('state')
            ->map(fn ($s) => strtoupper((string) $s))
            ->unique()->sort()->values()->all();
    }

    /** @return array<string, true> */
    private function placeNames(string $state): array
    {
        $names = [];
        $add = function (?string $city) use (&$names): void {
            $key = MapCandidateHygiene::placeKey($city);
            if ($key !== '') {
                $names[$key] = true;
            }
        };
        DB::table('city_demographics')->where('state', $state)->pluck('city_name')->each($add);
        Politician::query()->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereNotNull('city')->distinct()->pluck('city')->each($add);

        return $names;
    }

    /** Rows the map would show: active and not lost. */
    private function visiblePoliticians(string $state)
    {
        return Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('term_status', '!=', 'lost')->orWhereNull('term_status'))
            ->select(['id', 'full_name', 'district', 'political_office', 'city', 'term_status', 'verified_official']);
    }

    private function auditNames(string $state, array $placeNames, array &$findings): void
    {
        foreach ($this->visiblePoliticians($state)->cursor() as $pol) {
            $reason = MapCandidateHygiene::nameProblem($pol->full_name, $placeNames);
            if ($reason !== null) {
                $findings['bad_names'][] = [
                    'table' => 'politicians', 'id' => $pol->id, 'state' => $state,
                    'name' => $pol->full_name, 'office' => $pol->political_office,
                    'status' => $pol->term_status, 'reason' => $reason,
                    // Seated/verified rows are shown regardless — those need a human.
                    'hidden_on_map' => $pol->term_status !== 'seated' && ! $pol->verified_official,
                ];
            }
        }

        $records = ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->select(['id', 'full_name', 'political_office', 'source'])
            ->cursor();
        foreach ($records as $rec) {
            $reason = MapCandidateHygiene::nameProblem($rec->full_name, $placeNames);
            if ($reason !== null) {
                $findings['bad_names'][] = [
                    'table' => 'election_candidate_records', 'id' => $rec->id, 'state' => $state,
                    'name' => $rec->full_name, 'office' => $rec->political_office,
                    'status' => $rec->source, 'reason' => $reason, 'hidden_on_map' => true,
                ];
            }
        }
    }

    private function auditDuplicates(string $state, array &$findings): void
    {
        $groups = [];
        foreach ($this->visiblePoliticians($state)->cursor() as $pol) {
            $key = MapCandidateHygiene::identityKey($pol->full_name);
            if ($key === '') {
                continue;
            }
            $seat = $pol->district ?: trim($pol->political_office.'|'.$pol->city, '|');
            $groups[$seat.'#'.$key][] = ['id' => $pol->id, 'name' => $pol->full_name, 'status' => $pol->term_status];
        }

        foreach ($groups as $key => $rows) {
            if (count($rows) > 1) {
                [$seat] = explode('#', $key, 2);
                $findings['duplicates'][] = ['state' => $state, 'seat' => $seat, 'rows' => $rows];
            }
        }
    }

    private function auditDates(string $state, array &$findings): void
    {
        $official = StateElectionDate::query()
            ->where('state', $state)->whereRaw('LOWER(stage_name) = ?', ['general'])
            ->orderByDesc('election_date')->first();
        if (! $official?->election_date) {
            return;
        }
        $officialDate = $official->election_date->toDateString();

        $records = ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->select(['id', 'full_name', 'payload'])
            ->cursor();
        foreach ($records as $rec) {
            $scraped = is_array($rec->payload) ? ($rec->payload['general_date'] ?? null) : null;
            $scraped = $scraped ? substr((string) $scraped, 0, 10) : null;
            if ($scraped !== null && $scraped !== $officialDate) {
                $findings['date_conflicts'][] = [
                    'state' => $state, 'id' => $rec->id, 'name' => $rec->full_name,
                    'scraped' => $scraped, 'official' => $officialDate,
                ];
            }
        }
    }

    private function render(array $findings): void
    {
        $this->info(sprintf(
            'Map candidate audit: %d bad names, %d duplicate groups, %d date conflicts.',
            count($findings['bad_names']), count($findings['duplicates']), count($findings['date_conflicts']),
        ));

        if ($findings['bad_names']) {
            $this->newLine();
            $this->line('<fg=yellow>Names that are not people</>');
            $this->table(['table', 'id', 'st', 'name', 'reason', 'hidden'], array_map(fn ($f) => [
                $f['table'], $f['id'], $f['state'], mb_strimwidth((string) $f['name'], 0, 40, '…'),
                $f['reason'], $f['hidden_on_map'] ? 'yes' : 'NO — shown',
            ], $findings['bad_names']));
        }

        if ($findings['duplicates']) {
            $this->newLine();
            $this->line('<fg=yellow>Same person listed more than once</>');
            $this->table(['st', 'seat', 'rows'], array_map(fn ($f) => [
                $f['state'], $f['seat'],
                implode('; ', array_map(fn ($r) => "#{$r['id']} {$r['name']}", $f['rows'])),
            ], $findings['duplicates']));
        }

        if ($findings['date_conflicts']) {
            $this->newLine();
            $this->line('<fg=yellow>General-election date disagrees with the state calendar</>');
            $this->table(['st', 'id', 'name', 'scraped', 'official'], array_map(fn ($f) => [
                $f['state'], $f['id'], mb_strimwidth((string) $f['name'], 0, 34, '…'), $f['scraped'], $f['official'],
            ], $findings['date_conflicts']));
        }
    }
}
