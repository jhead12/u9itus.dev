<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use App\Services\Civic\Adapters\WikipediaBallotMeasuresAdapter;
use App\Support\BallotMeasureWriter;
use Illuminate\Console\Command;

class ImportWikipediaBallotMeasures extends Command
{
    protected $signature = 'ballot-measures:import-wikipedia
        {--state= : Two-letter state code (default: all states)}
        {--year= : Article year (default: configured Wikipedia year)}
        {--refresh : Overwrite non-empty measure fields, preserving original source}
        {--dry-run : Preview extracted measures without database writes}';

    protected $description = 'Extract statewide measures from Wikipedia United States ballot measures tables.';

    public function handle(BallotMeasureWriter $writer): int
    {
        $states = config('u9itus.us_states', []);
        $state = strtoupper(trim((string) $this->option('state')));
        $year = filter_var($this->option('year') ?? config('civic.wikipedia.year', 2026), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1900, 'max_range' => 2100]]);
        if (! $year || ($state !== '' && ! isset($states[$state]))) {
            $this->error('Use a valid two-letter --state and a --year between 1900 and 2100.');

            return self::FAILURE;
        }

        $original = config('civic.wikipedia');
        // A fresh adapter shares one fetched article across this run's states.
        $adapter = new WikipediaBallotMeasuresAdapter;
        config(['civic.wikipedia.year' => $year]);
        if ($this->option('year') !== null) {
            config(['civic.wikipedia.article' => "{$year} United States ballot measures"]);
        }
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $found = 0;
        $dryRun = (bool) $this->option('dry-run');

        try {
            foreach ($state !== '' ? [$state] : array_keys($states) as $code) {
                $measures = $adapter->fetchMeasures(new ElectionDataSource(['state' => $code, 'level' => 'state']));
                foreach ($measures as $measure) {
                    $attrs = BallotMeasureWriter::normalize($measure, state: $code, county: null,
                        electionDate: $measure['election_date'] ?? null, source: 'wikipedia', level: 'state');
                    if ($attrs === null) {
                        continue;
                    }
                    $result = $writer->upsert($attrs, (bool) $this->option('refresh'), $dryRun);
                    $counts[$result]++;
                    $found++;
                    $this->line("[{$code}] {$attrs['title']} | {$attrs['election_date']} | {$attrs['status']} ({$result})");
                    $this->line('  Source: '.$attrs['source_url']);
                }
            }
        } finally {
            config(['civic.wikipedia' => $original]);
        }

        if ($found === 0) {
            $this->warn('No measures extracted. The article may be unavailable, lack the selected state, or use an unsupported table layout.');

            return self::FAILURE;
        }
        $this->info("Done. Found: {$found} | Created: {$counts['created']} | Updated: {$counts['updated']} | Unchanged: {$counts['unchanged']}"
            .($dryRun ? ' (dry-run — nothing saved)' : ''));

        return self::SUCCESS;
    }
}
