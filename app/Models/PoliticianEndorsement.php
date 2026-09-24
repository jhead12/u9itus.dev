<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public endorsement (e.g. "Governor endorses") detected by
 * App\Services\EndorsementClassifier from candidate_news_articles text.
 * One row per (politician, group_key, endorser_key).
 *
 * Detection only checks that a title sits near an endorsement verb, so it cannot
 * tell who endorsed whom. Rows start as 'detected' and are shown publicly only once
 * an editor confirms them, as an endorsement of the candidate or of their bill.
 */
class PoliticianEndorsement extends Model
{
    protected $table = 'politician_endorsements';

    protected $fillable = [
        'politician_id',
        'group_key',
        'label',
        'endorser_key',
        'endorser_name',
        'matched_phrase',
        'confidence',
        'source_article_id',
        'source_url',
        'detected_article_ids',
        'match_count',
        'status',
        'kind',
        'bill_title',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    public const STATUS_DETECTED = 'detected';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISMISSED = 'dismissed';

    public const KIND_CANDIDATE = 'candidate';
    public const KIND_BILL = 'bill';

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'detected_article_ids' => 'array',
            'match_count' => 'integer',
            'reviewed_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** Awaiting editor review. Detection rebuilds replace only these rows. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DETECTED);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function isBill(): bool
    {
        return $this->kind === self::KIND_BILL;
    }

    /** Who endorsed, for display: the named endorser, else the group ("Governor"). */
    public function endorserLabel(): string
    {
        return $this->endorser_name ?: $this->label;
    }

    /**
     * A reviewer hint only: the phrase reads like support for a bill ("endorses
     * Congresswoman Escobar's Dignity Act"). Returns the bill title it found, or null.
     */
    public function suggestedBillTitle(): ?string
    {
        $text = (string) $this->matched_phrase.' '.(string) $this->sourceArticle?->headline;
        if (preg_match("/[’']s\s+((?:[A-Z][\w.-]*\s+){0,6}(?:Act|Bill)(?:\s+of\s+\d{4})?)\b/u", $text, $m)
            || preg_match('/\b((?:H\.?R\.?|S\.|SB|AB|HB)\s?\d{1,5})\b/', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Editor-confirmed endorsements, ready to list: named endorsers first (that is what a reader scans for),
     * then by how sure the detection was.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function listedFor(Politician $politician)
    {
        return $politician->endorsements()->confirmed()->with('sourceArticle:id,source_name')->get()
            ->sortBy([
                fn ($a, $b) => ($a->endorser_name === null) <=> ($b->endorser_name === null),
                fn ($a, $b) => $b->confidence <=> $a->confidence,
            ])
            ->values();
    }
}
