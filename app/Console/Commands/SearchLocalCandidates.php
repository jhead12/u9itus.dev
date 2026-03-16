<?php

namespace App\Console\Commands;

use App\Services\LocalCandidateAggregator;
use Illuminate\Console\Command;

class SearchLocalCandidates extends Command
{
    protected $signature = 'candidates:search-local
        {--address= : Full address to search for candidates}
        {--state= : State abbreviation to search}
        {--city= : City name to search}
        {--governance-levels=* : Filter by governance level (Federal,State,County,City,School Board,Judicial)}
        {--exclude-federal : Exclude federal candidates from results}
        {--limit=20 : Maximum results to display}';

    protected $description = 'Search for local election candidates by address or location using multiple data sources';

    protected LocalCandidateAggregator $aggregator;

    public function __construct(LocalCandidateAggregator $aggregator)
    {
        parent::__construct();
        $this->aggregator = $aggregator;
    }

    public function handle()
    {
        $address = $this->option('address');
        $state = $this->option('state');
        $city = $this->option('city');
        $governanceLevels = $this->option('governance-levels') ?? [];
        $excludeFederal = $this->option('exclude-federal');
        $limit = (int) $this->option('limit');

        $candidates = collect();

        if ($address) {
            $this->info("🔍 Searching for candidates at: {$address}");
            $options = [];
            if (!empty($governanceLevels)) {
                $options['governance_levels'] = $governanceLevels;
            }
            if ($excludeFederal) {
                $options['exclude_federal'] = true;
            }
            $candidates = $this->aggregator->findByAddress($address, $options);

        } elseif ($city && $state) {
            $this->info("🔍 Searching for candidates in {$city}, {$state}");
            $candidates = $this->aggregator->findByCity($city, $state);

        } elseif ($state) {
            $this->info("🔍 Searching for candidates in {$state}");
            $candidates = $this->aggregator->findByState($state, $governanceLevels);

        } else {
            $this->error('Please provide either --address, --city with --state, or --state');
            return 1;
        }

        if ($candidates->isEmpty()) {
            $this->warn('No candidates found.');
            return 0;
        }

        $candidates = $candidates->take($limit);

        $this->info("\n✅ Found " . $candidates->count() . " candidate(s)\n");

        $rows = $candidates->map(function ($candidate) {
            return [
                'Name' => $candidate['full_name'] ?? 'N/A',
                'Office' => $candidate['political_office'] ?? 'N/A',
                'Level' => $candidate['governance_level'] ?? 'N/A',
                'State' => $candidate['state'] ?? 'N/A',
                'Party' => $candidate['party_affiliation'] ?? 'N/A',
                'Source' => $candidate['source'] ?? 'Database',
            ];
        })->toArray();

        $this->table(array_keys($rows[0] ?? []), $rows);

        return 0;
    }
}
