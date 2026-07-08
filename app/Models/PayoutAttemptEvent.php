<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only event log for a single PayoutAttempt.
 *
 * Records each status transition with timestamp. No updated_at —
 * this is a financial audit trail and must never be modified.
 */
class PayoutAttemptEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payout_attempt_id',
        'status',
        'note',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PayoutAttempt::class, 'payout_attempt_id');
    }
}
