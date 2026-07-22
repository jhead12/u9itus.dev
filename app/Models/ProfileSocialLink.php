<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProfileSocialLink extends Model
{
    protected $table = 'profile_social_links';

    protected $fillable = [
        'profilable_type',
        'profilable_id',
        'run_id',
        'platform',
        'url',
        'handle',
        'display_name',
        'is_official',
        'source_url',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProfileEnrichmentRun::class, 'run_id');
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    /** Substack / RSS / newsletter links — feeds the newsletter post adapter. */
    public function scopeNewsletter(Builder $query): Builder
    {
        return $query->whereIn('platform', ['substack', 'rss', 'newsletter']);
    }
}