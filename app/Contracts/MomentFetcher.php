<?php

namespace App\Contracts;

use App\Models\Politician;

/**
 * A source fetcher for the viral-moment pipeline (App\Services\ViralMomentEnricherService).
 *
 * Each concrete fetcher (YouTubeMomentService, CspanMomentService, ...) searches
 * one external source for clips about a politician and returns normalized clip
 * candidates. Scoring, upserting, pruning, and featuring stay in the enricher so
 * a new source only has to implement discovery + normalization.
 *
 * fetchMoments() MUST return the shape established by YouTubeMomentService:
 *
 *   [
 *     'status'      => string,        // 'ok' | 'empty' | 'failed' | 'rate_limited' | ...
 *     'http_status' => int|null,
 *     'query'       => string|null,   // the search query actually submitted
 *     'clips'       => list<array{    // normalized clip candidates
 *         'source'           => string,        // 'youtube' | 'cspan' | ...
 *         'source_id'        => string,        // external id; part of the upsert key
 *         'title'            => string,
 *         'url'              => string,        // watch URL (frontend converts to embed)
 *         'thumbnail_url'    => string|null,
 *         'published_at'     => Carbon|null,
 *         'duration_seconds' => int|null,
 *         'view_count'       => int|null,      // null when the source exposes none
 *         'like_count'       => int|null,
 *         'comment_count'    => int|null,
 *         'match_confidence' => float,         // 0–1 name/office match quality
 *     }>,
 *   ]
 *
 * isConfigured() gates the source in the command (API key set, scraper runtime
 * available, source enabled flag, ...). clearCache() forces a fresh fetch.
 */
interface MomentFetcher
{
    public function source(): string;

    public function isConfigured(): bool;

    /**
     * @return array{status: string, http_status: int|null, query: string|null, clips: array<int, array<string, mixed>>}
     */
    public function fetchMoments(Politician $politician): array;

    public function clearCache(Politician $politician): void;
}