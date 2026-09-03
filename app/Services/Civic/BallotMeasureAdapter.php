<?php

namespace App\Services\Civic;

use App\Models\ElectionDataSource;

/**
 * Pulls ballot measures from a jurisdiction's own web page when there's no
 * Voting Information Project feed for it. One adapter per vendor family
 * (`platform_template`), so writing a scraper for one voteinfo.net county
 * covers every voteinfo.net county.
 *
 * civic:scrape-measures resolves an adapter per row via MeasureAdapterRegistry,
 * calls fetchMeasures(), and hands the results to App\Support\BallotMeasureWriter.
 */
interface BallotMeasureAdapter
{
    /** Slug this adapter is registered under (matches election_data_sources.platform_template). */
    public function key(): string;

    /**
     * Scrape the row's ballot-measures / sample-ballot page. Returns [] when
     * the page has no measures or couldn't be parsed with confidence — never
     * throws for an ordinary "nothing found".
     *
     * @return list<array{title: string, measure_number?: ?string, summary?: ?string, source_url?: ?string, election_date?: ?string}>
     */
    public function fetchMeasures(ElectionDataSource $row): array;
}
