<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One viral-moment enricher fetch run against an external source (YouTube Data
 * API, C-SPAN, news feed) for a politician. Records provenance (when, which
 * source, the query used, fetch status, counts). Fact rows
 * (PoliticianViralMoment) reference this run via run_id.
 */
class ViralMomentEnrichmentRun extends Model
{
    protected $table = 'viral_moment_enrichment_runs';

    protected $fillable = [
        'politician_id',
        'source',
        'fetch_status',
        'http_status',
        'query_string',
        'extracted_counts',
        'enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_counts' => 'array',
            'enriched_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isStale(int $hours = 48): bool
    {
        return $this->enriched_at === null
            || $this->enriched_at->lt(now()->subHours($hours));
    }
}