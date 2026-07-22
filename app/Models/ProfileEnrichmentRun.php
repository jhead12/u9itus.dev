<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One ProfileEnricherService fetch run against a profilable's official /
 * campaign website. Records provenance (when, what URL, fetch status, robots
 * status, counts, Claude fallback usage). Fact rows reference this run.
 */
class ProfileEnrichmentRun extends Model
{
    protected $table = 'profile_enrichment_runs';

    protected $fillable = [
        'profilable_type',
        'profilable_id',
        'source_url',
        'fetch_status',
        'http_status',
        'robots_allowed',
        'extracted_counts',
        'used_claude_fallback',
        'enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_counts'    => 'array',
            'enriched_at'         => 'datetime',
            'robots_allowed'      => 'boolean',
            'http_status'         => 'integer',
            'used_claude_fallback' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    // Facts created by this run reach back via their run_id FK (see each fact
    // model's run()). The run itself does not own a morphMany — facts are
    // morphed to the profilable (the Politician), not to the run.

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isStale(int $hours = 48): bool
    {
        return $this->enriched_at === null
            || $this->enriched_at->lt(now()->subHours($hours));
    }
}