<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutRun extends Model
{
    protected $fillable = [
        'triggered_by_admin_id',
        'trigger_source',
        'min_payout_amount',
        'fraud_hold_hours',
        'processed_count',
        'skipped_count',
        'total_paid',
        'meta',
        'status',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'min_payout_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'meta' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_admin_id');
    }

    public function skippedItems(): HasMany
    {
        return $this->hasMany(PayoutRunSkippedItem::class);
    }
}
