<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Human-review queue for politicians:cleanup-workflow findings that are too
 * risky to auto-apply (merges, deactivations, unrepairable junk names) —
 * modeled on CandidateMatchReview's pending/approved/rejected pattern.
 */
class PoliticianCleanupReview extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_MERGE = 'merge';
    public const TYPE_DEACTIVATE = 'deactivate';
    public const TYPE_NAME_REJECT = 'name_reject';

    protected $fillable = [
        'review_type',
        'politician_id',
        'duplicate_politician_id',
        'payload',
        'status',
        'reason',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function duplicatePolitician(): BelongsTo
    {
        return $this->belongsTo(Politician::class, 'duplicate_politician_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Insert a pending review for (type, politician[, duplicate]) unless an
     * identical one is already pending — keeps a daily scan from re-flagging
     * the same unresolved finding every run. Not a DB constraint: NULLs
     * (e.g. no duplicate_politician_id for a deactivate/name_reject review)
     * aren't equal to each other in a unique index on any of our supported
     * drivers, so this check has to happen in code.
     */
    public static function enqueue(string $type, int $politicianId, ?int $duplicatePoliticianId, array $payload, ?string $reason = null): self
    {
        $existing = static::query()
            ->where('review_type', $type)
            ->where('politician_id', $politicianId)
            ->where('duplicate_politician_id', $duplicatePoliticianId)
            ->where('status', self::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'review_type' => $type,
            'politician_id' => $politicianId,
            'duplicate_politician_id' => $duplicatePoliticianId,
            'payload' => $payload,
            'status' => self::STATUS_PENDING,
            'reason' => $reason,
        ]);
    }
}
