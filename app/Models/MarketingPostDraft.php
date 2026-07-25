<?php

namespace App\Models;

use App\Enums\MarketingDraftStatus;
use App\Enums\MarketingSourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Provenance + dedup row for the marketing content agent.
 *
 * One row per (politician, source) drafting attempt. `source_hash` is the
 * UNIQUE dedup key so repeated runs of marketing:draft-posts never re-draft
 * the same news article or viral moment. `post_id` points at the generated
 * Post (status = PendingApproval) when `status = posted`.
 *
 * `source()` is a plain accessor (NOT a MorphTo): there is no morphMap in this
 * repo, so a real morphTo would persist full class names into source_type.
 * Instead we switch on the MarketingSourceType enum and look the source row
 * up directly.
 */
class MarketingPostDraft extends Model
{
    protected $table = 'marketing_post_drafts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'       => MarketingDraftStatus::class,
            'source_type'  => MarketingSourceType::class,
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $draft): void {
            if (empty($draft->uuid)) {
                $draft->uuid = Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The source row this draft was generated from. Switches on the
     * source_type enum instead of a polymorphic morphTo (no morphMap is
     * registered in this repo). Returns null if the source row was deleted.
     */
    public function source(): ?Model
    {
        return match ($this->source_type) {
            MarketingSourceType::News        => CandidateNewsArticle::find($this->source_id),
            MarketingSourceType::ViralMoment => PoliticianViralMoment::find($this->source_id),
            default                           => null,
        };
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForSourceHash(Builder $query, string $sourceHash): Builder
    {
        return $query->where('source_hash', $sourceHash);
    }
}