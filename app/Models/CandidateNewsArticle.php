<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CandidateNewsArticle extends Model
{
    protected $fillable = [
        'politician_id',
        'candidate_name',
        'headline',
        'source_name',
        'source_url',
        'snippet',
        'image_url',
        'published_at',
        'provider',
        'verification_status',
        'verification_reason',
        'verification_confidence',
        'name_match_score',
        'context_match_score',
        'topic_key',
        'topic_confidence',
        'verified_at',
        'verification_meta',
        'source_hash',
        'scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scraped_at'   => 'datetime',
            'verified_at'  => 'datetime',
            'verification_confidence' => 'decimal:3',
            'name_match_score' => 'decimal:3',
            'context_match_score' => 'decimal:3',
            'topic_confidence' => 'decimal:3',
            'verification_meta' => 'array',
        ];
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    /** Most recent articles first, limited to a useful preview count. */
    public function scopeRecent(Builder $query, int $limit = 5): Builder
    {
        return $query->orderByDesc('published_at')->limit($limit);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified');
    }

    /** Articles for a given candidate by politician_id or name fallback. */
    public function scopeForCandidate(Builder $query, ?int $politicianId, string $candidateName): Builder
    {
        if ($politicianId !== null) {
            return $query->where('politician_id', $politicianId);
        }

        return $query->where('candidate_name', $candidateName);
    }

    /** Voters who have saved this article. */
    public function savedByVoters(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Voter::class,
            'voter_saved_articles',
            'candidate_news_article_id',
            'voter_id'
        )->withPivot('saved_at');
    }
}
