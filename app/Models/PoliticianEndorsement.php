<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A real public endorsement (e.g. "Governor endorses") detected by
 * App\Services\EndorsementClassifier from candidate_news_articles text.
 * One row per (politician, group_key) — see the migration docblock for why.
 */
class PoliticianEndorsement extends Model
{
    protected $table = 'politician_endorsements';

    protected $fillable = [
        'politician_id',
        'group_key',
        'label',
        'endorser_name',
        'matched_phrase',
        'confidence',
        'source_article_id',
        'source_url',
        'detected_article_ids',
        'match_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'detected_article_ids' => 'array',
            'match_count' => 'integer',
        ];
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(CandidateNewsArticle::class, 'source_article_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'detected');
    }
}
