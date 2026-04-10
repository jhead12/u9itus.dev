<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRunSkippedItem extends Model
{
    protected $fillable = [
        'payout_run_id',
        'voter_id',
        'view_session_id',
        'reason_bucket',
        'amount',
        'processor_selected',
        'processor_executed',
        'reason_detail',
        'context',
        'force_paid_at',
        'force_paid_by_admin_id',
        'force_pay_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'context' => 'array',
            'force_paid_at' => 'datetime',
        ];
    }

    public function payoutRun(): BelongsTo
    {
        return $this->belongsTo(PayoutRun::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function viewSession(): BelongsTo
    {
        return $this->belongsTo(ViewSession::class);
    }

    public function forcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'force_paid_by_admin_id');
    }
}
