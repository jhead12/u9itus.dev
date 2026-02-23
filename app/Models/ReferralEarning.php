<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Tracks referral earnings — two distinct commission types:
 *
 *  voter_view             : 10% of voter payout each time a referred voter
 *                           completes a qualifying view ($0.025 / view)
 *  politician_procurement : 10% of a referred politician's first credit
 *                           purchase (one-time bonus)
 */
class ReferralEarning extends Model
{
    use HasFactory;

    /** Referral commission types. */
    public const TYPE_VOTER_VIEW             = 'voter_view';
    public const TYPE_POLITICIAN_PROCUREMENT = 'politician_procurement';

    protected $fillable = [
        'referrer_voter_id',
        'referrer_politician_id',
        'referred_voter_id',
        'view_session_id',
        'commission_amount',
        'referral_type',
        'politician_id',
        'paid',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'decimal:2',
            'paid'             => 'boolean',
            'paid_at'          => 'datetime',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(Voter::class, 'referrer_voter_id');
    }

    public function referredVoter()
    {
        return $this->belongsTo(Voter::class, 'referred_voter_id');
    }

    public function viewSession()
    {
        return $this->belongsTo(ViewSession::class);
    }

    /**
     * The politician that triggered a procurement commission, if applicable.
     */
    public function politician()
    {
        return $this->belongsTo(Politician::class);
    }

    /**
     * The politician who earned this commission (politician-as-referrer).
     */
    public function referrerPolitician()
    {
        return $this->belongsTo(Politician::class, 'referrer_politician_id');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeVoterViews($query)
    {
        return $query->where('referral_type', self::TYPE_VOTER_VIEW);
    }

    public function scopeProcurements($query)
    {
        return $query->where('referral_type', self::TYPE_POLITICIAN_PROCUREMENT);
    }
}
