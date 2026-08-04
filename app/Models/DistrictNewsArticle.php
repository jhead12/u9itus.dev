<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class DistrictNewsArticle extends Model
{
    protected $fillable = [
        'district_code',
        'state',
        'matched_locality',
        'headline',
        'source_name',
        'source_url',
        'snippet',
        'published_at',
        'provider',
        'source_hash',
        'verification_status',
        'verification_reason',
        'topic_key',
        'scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scraped_at'   => 'datetime',
        ];
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

    public function scopeForDistrict(Builder $query, string $districtCode): Builder
    {
        return $query->where('district_code', $districtCode);
    }
}
