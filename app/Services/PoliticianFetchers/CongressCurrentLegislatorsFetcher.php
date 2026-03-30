<?php

namespace App\Services\PoliticianFetchers;

use App\Contracts\PoliticianFetcher;
use App\Exceptions\PoliticianFetcherException;
use Illuminate\Support\Facades\Http;

class CongressCurrentLegislatorsFetcher implements PoliticianFetcher
{
    public function key(): string
    {
        return 'current';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{source:string,row:array<string,mixed>}>
     */
    public function fetch(array $options = []): array
    {
        $url = (string) ($options['url'] ?? 'https://unitedstates.github.io/congress-legislators/legislators-current.json');

        $response = Http::timeout(30)->get($url);
        if (! $response->ok()) {
            throw new PoliticianFetcherException('Unable to fetch current legislators feed: HTTP ' . $response->status());
        }

        $rows = $response->json();
        if (is_array($rows) && array_key_exists('legislators', $rows) && is_array($rows['legislators'])) {
            $rows = $rows['legislators'];
        }

        if (! is_array($rows)) {
            throw new PoliticianFetcherException('Current legislators feed returned an unexpected payload shape.');
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = [
                'source' => 'congress_legislators_current',
                'row' => $row,
            ];
        }

        return $result;
    }
}
