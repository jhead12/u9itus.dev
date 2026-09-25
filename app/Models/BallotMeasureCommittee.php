<?php

namespace App\Models;

use App\Support\MeasureCommitteeRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * A campaign committee's declared side on a ballot measure. Only verified links are shown
 * to voters; see MeasureCommitteeRules for how a link earns that.
 */
class BallotMeasureCommittee extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'ballot_measure_id',
        'state',
        'committee_id',
        'committee_name',
        'position',
        'source_url',
        'status',
        'integrity_flags',
        'acknowledged_flags',
        'review_note',
        'created_by_user_id',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'integrity_flags' => 'array',
            'acknowledged_flags' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Data-integrity gate: every save, from any path, meets the field rules, and a
        // link with a hard flag (e.g. its state doesn't match the measure's) is never
        // verified.
        static::saving(function (self $link): void {
            $link->state = strtoupper(trim((string) $link->state));
            $link->committee_id = trim((string) $link->committee_id);
            $link->committee_name = trim((string) $link->committee_name);
            $link->position = strtolower(trim((string) $link->position));

            $violations = MeasureCommitteeRules::violations($link->getAttributes());
            if ($violations !== []) {
                Log::warning('Ballot measure committee save blocked by data rules', ['id' => $link->id, 'violations' => $violations]);

                throw new \InvalidArgumentException('Committee link rejected: '.implode('; ', $violations));
            }

            if ($link->status === self::STATUS_VERIFIED && MeasureCommitteeRules::hasHardFlag(MeasureCommitteeRules::flags($link))) {
                throw new \InvalidArgumentException("Committee link can't be verified: its state doesn't match the measure's state.");
            }
        });
    }

    public function ballotMeasure(): BelongsTo
    {
        return $this->belongsTo(BallotMeasure::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /** Flags the reviewer didn't accept when verifying (all flags, for a pending link). */
    public function openFlags(): array
    {
        return MeasureCommitteeRules::unacknowledged($this->integrity_flags ?? [], $this->acknowledged_flags);
    }
}
