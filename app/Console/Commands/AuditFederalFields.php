<?php

namespace App\Console\Commands;

use App\Models\CandidateRoster;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use App\Services\StateMapListing;
use App\Services\WikipediaPrimaryResultsService;
use Illuminate\Console\Command;

/**
 * Who is in each federal race, per Wikipedia, against who the state map lists — with every
 * candidate the map is missing checked against the FEC roster.
 *
 * Federal candidates file with the FEC, so unlike a governor a missing Senate or House candidate
 * can be verified: "FEC-verified" means the roster lists the person for that chamber and state,
 * which makes it safe to add. Unverified names are usually a misspelling, a write-in or a
 * running mate the article lists under a ticket, and need a look first.
 *
 * Read-only; nothing is added to the map.
 *
 *   php artisan politicians:audit-federal-fields --office=senate
 *   php artisan politicians:audit-federal-fields --office=house --state=MI
 */
class AuditFederalFields extends Command
{
    protected $signature = 'politicians:audit-federal-fields
        {--office=senate : senate or house}
        {--state=        : Restrict to a two-letter state code}
        {--year=2026     : Election year}';

    protected $description = 'Compare each Senate/House race field on Wikipedia against the state map, FEC-verifying candidates the map is missing.';

    public function handle(): int
    {
        $office = strtolower((string) $this->option('office'));
        if (! in_array($office, ['senate', 'house'], true)) {
            $this->error("Invalid --office '{$office}'. Must be 'senate' or 'house'.");

            return self::FAILURE;
        }

        $year = (int) $this->option('year');
        $only = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $code = $office === 'senate' ? 'S' : 'H';

        $states = CandidateRoster::query()
            ->where('election_year', $year)
            ->where('office', $code)
            ->when($only, fn ($q) => $q->where('state', $only))
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->all();

        if ($states === []) {
            $this->warn("No FEC roster entries for {$year} {$office} races".($only ? " in {$only}" : '').'. Run the FEC candidate import first.');

            return self::SUCCESS;
        }

        $wikipedia = new WikipediaPrimaryResultsService();
        $corroboration = new CandidateCorroboration();
        $map = new StateMapListing();

        $rows = [];
        $details = [];
        $totals = ['verified' => 0, 'unverified' => 0, 'extra' => 0];

        foreach ($states as $state) {
            foreach ($this->races($office, $state, $code, $map) as $race) {
                $roster = $wikipedia->roster($state, $office === 'senate' ? 'Senator' : 'Representative', $year, $race['district']);
                if ($roster === null) {
                    $rows[] = [$race['label'], '—', '—', '—', '—'];

                    continue;
                }

                $expected = $roster['pending'] || $roster['advanced'] === []
                    ? array_values(array_filter($roster['declared'], fn ($n) => ! $wikipedia->contains($roster['withdrawn'], $n)))
                    : $roster['advanced'];
                ['listed' => $listed, 'running' => $running] = $race['district'] === null
                    ? $map->office($state, 'U.S. Senators')
                    : $map->house($state, (int) $race['district']);

                $missing = array_values(array_filter($expected, fn ($n) => ! $wikipedia->contains($listed, $n)));
                $extra = array_values(array_filter($running, fn ($n) => ! $wikipedia->contains($expected, $n)));

                $verified = 0;
                foreach ($missing as $name) {
                    $check = $corroboration->checkIdentity($name, $state, $office === 'senate' ? 'Senator' : 'Representative');
                    if ($check['corroborated']) {
                        $verified++;
                        $totals['verified']++;
                        $details[] = "  {$race['label']}: <fg=green>missing, verified</> — {$name} ({$check['source']})";
                    } else {
                        $totals['unverified']++;
                        $details[] = "  {$race['label']}: <fg=yellow>missing, unverified</> — {$name} — {$check['reason']}";
                    }
                }
                foreach ($extra as $name) {
                    $totals['extra']++;
                    $why = match (true) {
                        $wikipedia->contains($roster['withdrawn'], $name) => 'withdrawn per Wikipedia',
                        $wikipedia->contains($roster['eliminated'], $name) => 'eliminated per Wikipedia',
                        default => 'not on Wikipedia\'s list',
                    };
                    $details[] = "  {$race['label']}: <fg=red>should not show as running</> — {$name} ({$why})";
                }

                $rows[] = [$race['label'], count($expected), count($listed), count($missing).($missing !== [] ? " ({$verified} verified)" : ''), count($extra)];
            }
        }

        $this->table(['Race', 'Wikipedia', 'On map', 'Missing', 'Extra'], $rows);
        foreach ($details as $line) {
            $this->line($line);
        }
        $this->info(sprintf(
            '%d race(s): %d missing candidate(s) FEC-verified, %d unverified, %d shown as running but not in the field.',
            count($rows),
            $totals['verified'],
            $totals['unverified'],
            $totals['extra']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{label: string, district: ?string}>
     */
    private function races(string $office, string $state, string $code, StateMapListing $map): array
    {
        if ($office === 'senate') {
            return [['label' => "{$state} Senate", 'district' => null]];
        }

        $districts = CandidateRoster::query()
            ->where('state', $state)->where('office', $code)->whereNotNull('district')
            ->pluck('district')
            ->map(fn ($d) => (int) preg_replace('/\D/', '', substr((string) $d, (int) strrpos((string) $d, '-') + 1)))
            ->merge($map->houseDistricts($state))
            ->filter(fn ($d) => $d > 0)
            ->unique()
            ->sort()
            ->values();

        return $districts->map(fn (int $d) => ['label' => sprintf('%s-%02d', $state, $d), 'district' => (string) $d])->all();
    }
}
