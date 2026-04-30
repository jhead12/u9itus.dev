<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks each payout dispatch attempt with a deterministic idempotency key.
 *
 * Lifecycle: pending → submitted → paid|failed
 */
class PayoutAttempt extends Model
{
    protected $fillable = [
        'voter_id',
        'idempotency_key',
        'processor',
        'status',
        'amount',
        'processor_reference',
        'session_ids',
    ];

    protected $casts = [
        'session_ids' => 'array',
        'amount'      => 'decimal:2',
    ];

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }
}
