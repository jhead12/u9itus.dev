<?php

namespace App\Services\PoliticianFetchers;

use App\Contracts\PoliticianFetcher;

class CongressCombinedLegislatorsFetcher implements PoliticianFetcher
{
    public function __construct(
        protected CongressCurrentLegislatorsFetcher $currentFetcher,
        protected CongressHistoricalLegislatorsFetcher $historicalFetcher,
    ) {
    }

    public function key(): string
    {
        return 'combined';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{source:string,row:array<string,mixed>}>
     */
    public function fetch(array $options = []): array
    {
        $current = $this->currentFetcher->fetch([
            'url' => $options['current_url'] ?? $options['url'] ?? null,
        ]);

        $historical = $this->historicalFetcher->fetch([
            'url' => $options['historical_url'] ?? null,
        ]);

        return array_merge($current, $historical);
    }
}
