<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Audit record for every outbound webhook fired to early-bank.com.
 */
class EarlyBankWebhookLog extends Model
{
    use HasFactory;

    protected $table = 'earlybank_webhook_logs';

    protected $fillable = [
        'event_type',
        'voter_uuid',
        'earlybank_member_id',
        'view_session_uuid',
        'payout_amount',
        'payload',
        'http_status',
        'error_message',
        'delivered',
        'delivered_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'delivered'    => 'boolean',
        'delivered_at' => 'datetime',
        'payout_amount' => 'decimal:4',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForVoter($query, string $voterUuid)
    {
        return $query->where('voter_uuid', $voterUuid);
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('earlybank_member_id', $memberId);
    }

    public function scopeFailed($query)
    {
        return $query->where('delivered', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Human-readable event label for display. */
    public function eventLabel(): string
    {
        return match ($this->event_type) {
            'voter.registered' => 'Registration attributed',
            'voter.referred'   => '$10 referral bonus triggered',
            'voter.earned'     => '10% view commission',
            default            => $this->event_type,
        };
    }
}
