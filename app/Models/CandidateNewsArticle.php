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
        'source_hash',
        'scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scraped_at'   => 'datetime',
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

    /** Articles for a given candidate by politician_id or name fallback. */
    public function scopeForCandidate(Builder $query, ?int $politicianId, string $candidateName): Builder
    {
        if ($politicianId !== null) {
            return $query->where('politician_id', $politicianId);
        }

        return $query->where('candidate_name', $candidateName);
    }
}
