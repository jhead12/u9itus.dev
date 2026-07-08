<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function events(): HasMany
    {
        return $this->hasMany(PayoutAttemptEvent::class)->orderBy('created_at');
    }

    /**
     * Append an immutable status event to the audit trail.
     */
    public function recordEvent(string $status, ?string $note = null, array $metadata = []): void
    {
        $this->events()->create([
            'status'     => $status,
            'note'       => $note,
            'metadata'   => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
