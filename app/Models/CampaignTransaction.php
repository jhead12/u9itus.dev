<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CampaignTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'politician_id',
        'transaction_type',
        'amount',
        'currency',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_refund_id',
        'status',
        'receipt_sent_at',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'receipt_sent_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (CampaignTransaction $tx) {
            if (empty($tx->uuid)) {
                $tx->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Credit ledger entries that reference this Stripe transaction.
     * Used to detect whether a payment has already been credited
     * (billing:recover-stuck and finalizePaymentIntent idempotency).
     */
    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianCredit::class, 'related_transaction_id');
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }
}
