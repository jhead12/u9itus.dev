<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Tracks referral earnings — the 10 % commission a referrer earns
 * every time a referred voter completes a qualifying view.
 */
class ReferralEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_voter_id',
        'referred_voter_id',
        'view_session_id',
        'commission_amount',
        'paid',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'decimal:2',
            'paid' => 'boolean',
            'paid_at' => 'datetime',
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
}
