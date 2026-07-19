<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inbound Early-bank earnings ledger.
 *
 * Records what Early-bank.com reports it has paid/commissioned to a U9itus
 * member. U9itus does not accrue these until EB settles them; EB remains the
 * source of truth for the actual balance.
 */
class EarlyBankEarning extends Model
{
    /** Inbound event types we expect from Early-bank.com. */
    public const EVENT_PAYOUT_COMMISSION = 'payout.commission';
    public const EVENT_PAYOUT_BONUS      = 'payout.bonus';
    public const EVENT_MEMBER_STATUS     = 'member.status';
    public const EVENT_POLITICIAN_PURCHASED = 'politician.purchased';

    /** Lifecycle statuses. */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_FAILED  = 'failed';

    /** Subscription statuses synced via member.status events. */
    public const SUBSCRIPTION_INACTIVE   = 'inactive';
    public const SUBSCRIPTION_FREE       = 'free';
    public const SUBSCRIPTION_PAID       = 'paid';
    public const SUBSCRIPTION_SUSPENDED  = 'suspended';

    protected $table = 'earlybank_earnings';

    protected $fillable = [
        'earlybank_event_id',
        'event_type',
        'voter_id',
        'politician_id',
        'citizen_id',
        'earlybank_member_id',
        'referenced_voter_uuid',
        'referenced_politician_uuid',
        'commission_amount',
        'bonus_amount',
        'payout_amount',
        'status',
        'external_reference',
        'idempotency_key',
        'payload',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'earlybank_event_id'          => 'string',
            'earlybank_member_id'         => 'string',
            'referenced_voter_uuid'       => 'string',
            'referenced_politician_uuid'  => 'string',
            'commission_amount'           => 'decimal:4',
            'bonus_amount'                => 'decimal:4',
            'payout_amount'               => 'decimal:4',
            'payload'                     => 'array',
            'settled_at'                  => 'datetime',
        ];
    }

    // ── Relations ───────────────────────────────────────────────────────────

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForVoter($query, ?int $voterId)
    {
        return $query->where('voter_id', $voterId);
    }

    public function scopeForPolitician($query, ?int $politicianId)
    {
        return $query->where('politician_id', $politicianId);
    }

    public function scopeForEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeSettled($query)
    {
        return $query->where('status', self::STATUS_SETTLED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a deterministic idempotency key from EB event data.
     *
     * Used when EB does not supply its own idempotency key.
     */
    public static function makeIdempotencyKey(
        string $eventType,
        ?string $earlybankEventId,
        ?string $earlybankMemberId,
        float $amount,
        ?string $externalReference = null
    ): string {
        // Deterministic: the same EB event must always resolve to the same key.
        return hash('sha256', implode('|', [
            $eventType,
            $earlybankEventId ?? '',
            $earlybankMemberId ?? '',
            number_format($amount, 4, '.', ''),
            $externalReference ?? '',
        ]));
    }

    /**
     * Return a human-readable label for this earning row.
     */
    public function eventLabel(): string
    {
        return match ($this->event_type) {
            self::EVENT_PAYOUT_COMMISSION => 'Early-bank view commission',
            self::EVENT_PAYOUT_BONUS      => 'Early-bank referral bonus',
            self::EVENT_MEMBER_STATUS     => 'Early-bank membership status',
            self::EVENT_POLITICIAN_PURCHASED => 'Politician procurement commission',
            default                       => $this->event_type,
        };
    }
}
