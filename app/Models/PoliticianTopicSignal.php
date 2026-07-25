<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidence rollup for a politician × topic — the counts and score behind an
 * inferred issue badge. Built/refreshed nightly by PoliticianTopicSignalService
 * from news articles (topic_key), viral-moment titles, and Vote Smart NPAT
 * issue positions. When total_score crosses the configured threshold,
 * BadgeService grants a profile_badges row of badge_type='inferred_discourse'.
 *
 * One row per (politician_id, topic_id) — upserted on every rollup.
 */
class PoliticianTopicSignal extends Model
{
    protected $table = 'politician_topic_signals';

    protected $fillable = [
        'politician_id',
        'topic_id',
        'news_count',
        'viral_moment_count',
        'votesmart_count',
        'total_score',
        'score_components',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'news_count'         => 'integer',
            'viral_moment_count' => 'integer',
            'votesmart_count'    => 'integer',
            'total_score'        => 'decimal:4',
            'score_components'   => 'array',
            'last_seen_at'       => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PoliticianTopic::class, 'topic_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeTopByScore(Builder $query): Builder
    {
        return $query->orderByDesc('total_score');
    }

    public function scopeForTopic(Builder $query, int $topicId): Builder
    {
        return $query->where('topic_id', $topicId);
    }
}