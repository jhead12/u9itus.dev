<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per US jurisdiction that can put a measure on a ballot, mapping its
 * OCD division ID to the official election authority and the URLs the scrapers
 * read from. See doc/CIVIC_SOURCE_REGISTRY.md.
 */
class ElectionDataSource extends Model
{
    public const LEVELS = ['state', 'county', 'municipal', 'township', 'special'];

    public const SOURCES_OF_RECORD = ['eac', 'nass', 'census', 'google_civic', 'vip', 'ballotpedia', 'manual'];

    public const SCRAPE_STATUSES = ['unverified', 'ok', 'blocked', 'dead', 'redirected'];

    protected $fillable = [
        'ocd_id',
        'level',
        'state',
        'jurisdiction_name',
        'county_fips',
        'place_fips',
        'authority_name',
        'vendor',
        'platform_template',
        'elections_home_url',
        'sample_ballot_url',
        'ballot_measures_url',
        'results_url',
        'vip_feed_url',
        'ballotpedia_url',
        'urls',
        'source_of_record',
        'robots_ok',
        'scrape_status',
        'notes',
        'last_verified_at',
        'last_scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'urls' => 'array',
            'robots_ok' => 'boolean',
            'last_verified_at' => 'datetime',
            'last_scraped_at' => 'datetime',
        ];
    }

    /** Rows whose URLs have never been verified or have gone stale. */
    public function scopeNeedsVerification(Builder $query, int $staleDays = 30): Builder
    {
        return $query->where(function (Builder $q) use ($staleDays) {
            $q->whereNull('last_verified_at')
                ->orWhere('last_verified_at', '<', now()->subDays($staleDays));
        });
    }

    /** Rows we can actually point a scraper at right now. */
    public function scopeScrapable(Builder $query): Builder
    {
        return $query->where('scrape_status', 'ok')
            ->whereNotNull('ballot_measures_url');
    }

    public function scopeForState(Builder $query, string $state): Builder
    {
        return $query->where('state', strtoupper($state));
    }
}
