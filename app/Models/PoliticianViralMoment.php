<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A viral / C-SPAN / popular moment clip for a politician, with raw engagement
 * counts and a computed moment_score (see App\Services\MomentScoreService).
 * One row per (politician, source, source_id) — re-fetches upsert engagement
 * instead of duplicating. is_featured marks the top eligible clip per
 * politician, mirrored onto the politicians.featured_moment_* columns.
 */
class PoliticianViralMoment extends Model
{
    use HasFactory;

    protected $table = 'politician_viral_moments';

    protected $fillable = [
        'politician_id',
        'run_id',
        'source',
        'source_id',
        'title',
        'url',
        'thumbnail_url',
        'published_at',
        'duration_seconds',
        'view_count',
        'like_count',
        'comment_count',
        'view_velocity',
        'authority_weight',
        'match_confidence',
        'moment_score',
        'score_components',
        'is_featured',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'captured_at' => 'datetime',
            'duration_seconds' => 'integer',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'view_velocity' => 'decimal:2',
            'authority_weight' => 'decimal:2',
            'match_confidence' => 'decimal:2',
            'moment_score' => 'decimal:4',
            'score_components' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ViralMomentEnrichmentRun::class, 'run_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeEligible(Builder $query, int $withinDays = 30): Builder
    {
        return $query->where('published_at', '>=', now()->subDays($withinDays));
    }

    public function scopeTopByScore(Builder $query): Builder
    {
        return $query->orderByDesc('moment_score')->orderByDesc('published_at');
    }
}