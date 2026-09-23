<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoliticianChatterItem extends Model
{
    protected $hidden = ['submitted_by_user_id', 'contributor_notes', 'source_excerpt'];

    public const PLATFORMS = [
        'x' => 'X', 'instagram' => 'Instagram', 'substack' => 'Substack',
        'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'facebook' => 'Facebook',
        'news' => 'News / blog', 'other' => 'Other',
    ];

    public const CLAIM_STATUSES = [
        'unverified' => 'Unverified', 'disputed' => 'Disputed',
        'supported' => 'Supported by evidence', 'false' => 'False',
        'satire' => 'Satire / parody',
    ];

    public const MODERATION_PENDING = 'pending';

    public const MODERATION_PUBLISHED = 'published';

    public const MODERATION_REJECTED = 'rejected';

    public const MODERATION_ARCHIVED = 'archived';

    protected $fillable = [
        'politician_id', 'platform', 'source_url', 'source_author', 'source_published_at',
        'engagement_metrics', 'headline', 'summary', 'claim_status', 'moderation_status',
        'admin_notes', 'reviewed_by_user_id', 'reviewed_at', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'engagement_metrics' => 'array', 'source_published_at' => 'datetime',
            'reviewed_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(PoliticianChatterModerationLog::class)->latest();
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('moderation_status', self::MODERATION_PUBLISHED)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }

    public function claimStatusLabel(): string
    {
        return self::CLAIM_STATUSES[$this->claim_status] ?? ucfirst($this->claim_status);
    }
}
