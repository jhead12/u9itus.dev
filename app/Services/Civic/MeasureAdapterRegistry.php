<?php

namespace App\Services\Civic;

use App\Models\ElectionDataSource;
use App\Services\Civic\Adapters\GenericHtmlMeasureAdapter;

/**
 * Resolves the right BallotMeasureAdapter for a registry row.
 *
 * Lookup order:
 *   1. election_data_sources.platform_template (an explicit adapter key)
 *   2. election_data_sources.vendor mapped through config('civic.measure_adapters')
 *   3. null — nothing to scrape this row with
 */
class MeasureAdapterRegistry
{
    /** @var array<string, BallotMeasureAdapter> */
    private array $adapters = [];

    /**
     * @param  iterable<BallotMeasureAdapter>|null  $adapters  defaults to the generic HTML adapter
     */
    public function __construct(?iterable $adapters = null)
    {
        foreach ($adapters ?? [new GenericHtmlMeasureAdapter] as $adapter) {
            $this->adapters[$adapter->key()] = $adapter;
        }
    }

    public function for(ElectionDataSource $row): ?BallotMeasureAdapter
    {
        $key = $row->platform_template
            ?: (config('civic.measure_adapters.'.(string) $row->vendor) ?: null);

        return $key ? ($this->adapters[$key] ?? null) : null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->adapters);
    }
}
