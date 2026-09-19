<?php

namespace App\Support;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;

/**
 * The provenance stamp shown on candidate cards: where a row came from and how
 * fresh it is. Labels are plain-language — "News discovery (unverified)" tells
 * a voter more than "candidate_discovery".
 */
final class DataSourceLabel
{
    private const SCRAPED = [
        'seed' => 'U9itus editors',
        'manual_correction' => 'U9itus editors',
        'manual' => 'U9itus editors',
        'admin' => 'U9itus editors',
        'ballotpedia' => 'Ballotpedia',
        'fec' => 'FEC filings',
        'state_feed' => 'State election data',
        'county_feed' => 'County election data',
        'local_feed' => 'Local election data',
        'congress_legislators' => 'Congress legislators dataset',
        ElectionCandidateRecord::DISCOVERY_SOURCE => 'News discovery (unverified)',
    ];

    public static function forScrapeSource(?string $source): string
    {
        return self::SCRAPED[strtolower(trim((string) $source))] ?? 'Public records';
    }

    /**
     * @return array{source_label: string, updated_at: ?string}
     */
    public static function stamp(Politician|ElectionCandidateRecord $model): array
    {
        if ($model instanceof Politician) {
            $label = $model->verified_official ? 'Verified by the official' : 'U9itus public records';
            $at = $model->updated_at;
        } else {
            $label = self::forScrapeSource($model->source);
            $at = $model->last_seen_at ?? $model->updated_at;
        }

        return ['source_label' => $label, 'updated_at' => $at?->toIso8601String()];
    }
}
